<?php
namespace Stanford\SNP;
/** @var \Stanford\SNP\SNP $module */

use \REDCap;
/**
 * Class MSGraphAPI
 * A class for managing tokens and connecting to the Microsoft Graph API
 *
 * //TODO: Add documentation about how this class really works with the MS API to get/refresh tokens in people-speak
 *
 *
 * @package Stanford\SNP
 */
class MSGraphAPI
{

    // SECTION FOR AUTHENTICATION (V1) - taken from https://portal.azure.com/#blade/Microsoft_AAD_IAM/ApplicationsListBlade
    public $authorize_endpoint   = "https://login.windows.net/396573cb-f378-4b68-9bc8-15755c0c51f3/oauth2/authorize";
    public $client_id            = "560586dc-e7cc-48a6-956a-afd62decc413";  // V2 = "adb11225-133a-4db4-aae8-029f32e40720";
    public $response_type        = "code"; // id_token for jwt
    public $redirect_uri         = "";      //https://redcap.stanford.edu/plugins/snp/calendar/portal/";
    public $response_mode        = "form_post";
    public $auth_scope           = "openid offline_access profile https://graph.microsoft.com/Calendar.ReadWrite";
    public $home_url             = "https://redcap.stanford.edu/plugins/scheduler/";

    // SECTION FOR REQUESTING TOKENS
    public $token_endpoint       = "https://login.windows.net/396573cb-f378-4b68-9bc8-15755c0c51f3/oauth2/token";
    public $client_secret        = "pjrarUF6lZaDh69ACcSWTXG01oSeHcc8SASytqkr1iw=";
    public $resource             = "https://graph.microsoft.com";

    public $username;           // Name of the user using the application (must match the user who is authenticating if there are no valid credentials)
    public $errors = null;
    public $active_config;      // The active configuration
    public $active_request_id;  // This is the request that is currently in-use
    public $request;            // The actual request record

    public $token_pid;           // REDCap project PID that contains
    public $token_event_id;      // Event ID where token form is located


    public function __construct($token_pid, $token_event_id)
    {
        // Generate a non-auth uri for MS API Auth post-back as an external module
        // Please note that this url must also be updated in the azure portal for this application
        global $module;
        $this->redirect_uri = $module->getUrl("pages/Authorize.php",true,true);

        $this->username = USERID;
        $this->token_pid = $token_pid;
        $this->token_event_id = $token_event_id;

    }

    public function getValidToken()
    {
        // Retrieve the current stored token if it is still valid
        $token = $this->findValidToken();
        if (empty($token) or $token === false) {

            // Need to reauthorize since we cannot find a valid token
            $this->log("Need to re-authorize - try to redirect page to Authorize.php");
            $this->authorizeUserRedirect();

            // $this->log("Back from re-direct");
            // // header( 'Location: https://redcap.stanford.edu/plugins/scheduler/authorize.php' );
            //
            // $token = $this->findValidToken();
            // if (empty($token) or $token === false) {
            //     $this->error("Could not retrieve a valid token even after Re-Authorization");
            // }
        }

        // $this->log("Returning from getValidToken with token: $token");
        return $token;

    }

    private function findValidToken ()
    {
        // Find the record that holds the active configuration and determine which record we should use for authorization
        $result = $this->loadActiveConfig();
        if ($result === false) {
            // No active config - go to authenication page
            return $result;
        } else {
            // Loaded the active config record - now see if the token is still valid
            // $tz = new DateTimeZone("America/Los_Angeles");
            // $now = new DateTime('NOW', $tz);

            date_default_timezone_set('America/Los_Angeles');
            $now = strtotime("now");
            $token_expires = strtotime($this->request['expires_on']);

            $string = "This is now: " . $now . ", and this is expires on: " . $this->request['expires_on'] . ", strtotime: " . $token_expires;
            $this->log($string);

            if ($now < $token_expires) {
                return $this->request['access_token'];
            } else {
                $this->log("Need to refresh Token");
                $result = $this->refreshTokens();
                return $result;
            }
        }
    }


    /**
     * Uses a static record ($config_active_record) that stores the ID of the 'main' record that was last used
     * Returns the request_id or FALSE if there is an issue
     */
    private function loadActiveConfig()
    {
        // We are looking for the record that has the ACTIVE-CONFIG in the request_id (doesn't seem like it's needed)
        $filter = '[request_id] = "ACTIVE-CONFIG"';
        $q = REDCap::getData($this->token_pid, 'json', null, null, $this->token_event_id,
            null, false, false, false, $filter);
        if (empty($q)) {
            // There is no active record - set result to false so reauthorization can take place
            $this->error("Unable to determine the last working authorization");
            $result = false;
        } else {

            $results = json_decode($q, true);
            $this_result = $results[0];

            if (empty($this_result['request_id'])) {
                // No authorization record found - reauthorize
                $this->error("There is no active authorization record.");
                $result = false;
            } else {
                // Found the current active record - load it to make sure the token is still valid
                $this->active_config = $this_result['request_id'];
                $this->active_request_id = $this_result['ac_request_id'];
                $result = $this->loadRequest($this->active_request_id);
            }
        }

        return $result;
    }


    /**
     * Loads the actual request from the database
     * returns true or false based on success
     */
    private function loadRequest($request_id)
    {
        // Only load record if the inactive flag is not set and authorized user has a record.
        $filter = '';
        // $filter = '[inactive(1)] <> 1 and [username] === ' . $this->username;

        $q = REDCap::getData($this->token_pid, 'json', $request_id, null, $this->token_event_id,
            null, false, false, false, $filter);

        if (empty($q)) {
            // Unable to find record
            $this->error("Unable to load active request id: " . $request_id);
            return false;
        }

        $results = json_decode($q,true);
        $this_result = $results[0];
        $this->request = $this_result;
        return true;
    }

    /**
     * Determine if request is valid and return true/false
     */
    /*
    public function isRequestValid() {
        $this->log("In isRequestValid");

        $result = false;

        $not_before = $this->request['not_before'];
        $expires_on = $this->request['expires_on'];
        $result = false;
        if (!empty($not_before) && !empty($expires_on) ) {
            // request has timestamps
            $now = strtotime("now");
            if (strtotime($not_before) > $now) {
                $this->error("It is too early to use this token");
            } elseif ($now >= strtotime($expires_on) ) {
                $this->error("This token has expired");

                // Should we try to renew it here?  Probably
                $result = $this->refreshTokens();
                $this->log($result, "DEBUG", "Result from this->refreshTokens");

            } else {
                // This token is valid!
                $result = true;
            }
        } else {
            $this->error("The current request has invalid timestamps");
        }
        $this->log($result, "DEBUG", "Determining if Request is valid!");
        return $result;
    }
*/

    /**
     * Called to redirect and authorize the user
     */
    public function authorizeUserRedirect()
    {
        // Create record in REDCap to mark authentication request
        $next_id = Util::getNextId($this->token_pid,"record_id", $this->token_event_id, null);
        $this->log($next_id, "DEBUG", "Next record number: $next_id");

        $scope_verification_token = uniqid();
        $this->log($scope_verification_token, "DEBUG", "New scope verification value:");
        $this->log(md5($scope_verification_token), "DEBUG", "Hash of New scope verification value:");

        $data = array(
            $next_id => array(
                $this->token_event_id => array(
                    'username'                          => $this->username,
                    'scope_verification_token'          => $scope_verification_token
                )
            )
        );
//            calendarConfig::$config_pk_field    => $next_id,
//             'record_id'                         => $next_id,
//             'redcap_event_name'                 => $this->token_event_id,
//             'username'                          => $this->username,
//             'scope_verification_token'          => $scope_verification_token
//         );
//         $q = REDCap::saveData(calendarConfig::$config_pid, 'json', json_encode(array($data)));
        $q = REDCap::saveData($this->token_pid, 'array', $data);

        $this->log($q, "DEBUG", "Created new request $next_id");

        if (!empty($q['errors'])) {
            // An error occurred creating the record
            $this->log($q,"ERROR","Error creating new authorization record");
            $this->error("Error creating new authorization record - see logs for details");
            return false;
        }

        // Prepare to redirect to Microsoft
        $state = json_encode(array(
            'type'          => 'authorize',
            'request_id'    => $next_id,
            'hash'          => md5($scope_verification_token)));

        // If the response_type is id_token - then you get a JWT token
        // If the response_type is a 'code' then you receive a 'code'
        $params = array(
            'client_id'         => $this->client_id,
            'response_type'     => $this->response_type,
            'redirect_uri'      => $this->redirect_uri,
            'response_mode'     => $this->response_mode,
            'scope'             => $this->auth_scope,
            'state'             => $state,
            'nonce'             => '1234567890'
        );
        $qs = http_build_query($params);
        $url = $this->authorize_endpoint . "?" . $qs;
        $this->log($url, "DEBUG", "Redirecting to Microsoft");
        redirect($url);
        exit();
    }



    /**
     * Process an authentication post-back from Microsoft to our endpoint
     */
    public function processAuthorizationPostBack()
    {
        // Check for error from Microsoft
        if (isset($_POST['error'])) {
            $this->log($_POST, "ERROR", "Error with incoming microsoft authorization");
            return false;
        } elseif (!isset($_POST['state'])) {
            // Missing POST state
            $this->log($_POST, "DEBUG", "Invalid post-back - no state");
            return false;
        }

        $state = json_decode($_POST['state'], true);
        $type = $state['type'];
        $request_id = $state['request_id'];
        $hash = $state['hash'];

        $this->log(implode(',', $state), "DEBUG", "Returned state: ");

        if ($type == 'authorize') {

            // Update the activeRequest
            //$this->saveActiveConfig($request_id);
            if ($this->loadRequest($request_id) === false) {
                // This request does not exist
                $this->error("Unable to load postback request $request_id");
                return false;
            };
            $this->log($request_id, "DEBUG", "Loaded Request");

            /*
             * Verify hash in post matches hash's unique id from database
             */
            $scope_verification_token = $this->request['scope_verification_token'];
            $this->log($scope_verification_token, "DEBUG", "This is scope verification token: $scope_verification_token for record_id: $request_id");

            if ($hash != md5($scope_verification_token)) {
                $this->error("Hash mismatch with request");
                $this->log("Hash is $hash, scope_verification_token is $scope_verification_token, md5 of scope_verification_token is " . md5($scope_verification_token), "DEBUG", "Hash Verification Failed");
                return false;
            }

            /**
             * Store the authorization results to the REDCap database
             */
            $id_token = isset($_POST['id_token']) ? $_POST['id_token'] : "";
            $code = isset($_POST['code']) ? $_POST['code'] : "";
            $data = array(
//                calendarConfig::$config_pk_field => $request_id,
//                 'record_id' => $request_id,
//                 'redcap_event_name' => calendarConfig::$token_event,
                'ts' => date('Y-m-d H:i:s'),
                'raw_response' => json_encode($_POST),
                'type' => $type,
                'id_token' => $id_token,
                'code' => $code
            );

            // If we are doing JWT
            if (!empty($id_token)) {
                $parts = explode(".", $id_token);
                $p1 = json_decode(base64_decode($parts[0]), true);
                $p2 = json_decode(base64_decode($parts[1]), true);
                $data['jwt_payload'] = $p2;
            }
            //$this->log($data, "DEBUG", "Data In");


            $data = array(
                array(
                    $request_id => array(
                        $this->token_event_id => $data
                    )
                )
            );

            // Save Authentication to REDCap
            // $q = REDCap::saveData(calendarConfig::$config_pid, 'json', json_encode(array($data)));
            $q = REDCap::saveData($this->token_pid, 'array', $data);
            if (!empty($q['errors'])) {
                error("Error saving authorization data");
                $this->log($q, "ERROR", "Error saving authorization data");
                return false;
            }
            $this->log($request_id, "DEBUG", "Authorization Saved");

            // Merge response into the cached request object (so we don't need to re-query the database)
            foreach ($data as $k => $v) {
                $this->request[$k] = $v;
            }

            // Pull new tokens using the new code
            $result = $this->getTokensFromCode();

            if ($result) {
                // Set the ACTIVE CONFIG to this record
                $result = $this->saveActiveConfig($request_id);
            }
            return $result;
        }

        $this->log("Invalid type: $type","ERROR");
        return false;
    }


    /**
     * Load the current auth record from the SNP Calednar Assistant Configuration project for this user
     */
    public function saveActiveConfig($request_id) {

        $data = array(
            'record_id'         => $this->active_config,
            'redcap_event_name' => $this->token_event_id,
            'request_id'        => 'ACTIVE-CONFIG',
            'ac_request_id'     => $request_id
        );

        $q = REDCap::saveData($this->token_pid, 'json', json_encode(array($data)));
        if (!empty($q['errors'])) {
            $this->log("$q", "ERROR", "Error at saveActiveConfig");
            $this->error("Error setting saveActiveConfig");
            return false;
        }
        return true;
    }



    /**
     * With a new authentication code, obtain the first set of tokens
     */
    public function getTokensFromCode() {
        $this->log("In getTokensFromCode");

        $code = $this->request['code'];
        if ($code) {
            $url = $this->token_endpoint;
            $resource = $this->resource;

            $params = array(
                'grant_type'    => 'authorization_code',
                'redirect_uri'  => $this->redirect_uri,   // Not sure how this is used here
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
                'code'          => $code,
                'resource'      => $resource
            );

            //$this->log($params, "DEBUG", "Get Tokens for $resource Params");
            $q = http_post($url,$params);
            $result = json_decode($q,true);
            //$this->log($result, "DEBUG", $resource);

            $data = array(
                'not_before'        => isset($result['not_before']) ? date('Y-m-d H:i:s', $result['not_before']) : '',
                'expires_on'        => isset($result['expires_on']) ? date('Y-m-d H:i:s', $result['expires_on']) : '',
                'access_token'      => $result['access_token'],
                'refresh_token'     => $result['refresh_token'],
                'id_token'          => $result['id_token'],
                'raw_token'         => $q
            );

            foreach ($data as $k => $v) $this->request[$k] = $v;

            $this->log($this->request, "DEBUG", "Saving Request");
            $result = $this->saveRequest();
        } else {
            // No code
            $result = false;
        }
        return $result;
    }


    /**
     * Refresh an existing token
     * @return bool|mixed|stdClass
     */
    public function refreshTokens() {


        $string = "In refreshTokens" . implode(',', $this->request);
        $this->log($string);

        $refresh_token = $this->request['refresh_token'];
        if (!empty($refresh_token)) {
            $url = $this->token_endpoint;
            $resource = $this->resource;

            $params = array(
                'grant_type'    => 'refresh_token',
                'redirect_uri'  => $this->redirect_uri,
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
                'refresh_token' => $refresh_token,
                'resource'      => $resource
            );

            $this->log($params, "DEBUG", "Refreshing token at $url");
            $q = http_post($url,$params);

            $this->log($q,"DEBUG", "RefreshToken Raw Response");
            $result = json_decode($q,true);
            if (!empty($result['error'])) {
                // Errors in response
                $this->log($result, "ERROR", "Error refreshing token");
                $this->error("Error refreshing token - see logs");
                return false;
            }

            $data = array(
                'not_before'        => isset($result['not_before']) ? date('Y-m-d H:i:s', $result['not_before']) : '',
                'expires_on'        => isset($result['expires_on']) ? date('Y-m-d H:i:s', $result['expires_on']) : '',
                'access_token'      => $result['access_token'],
                'refresh_token'     => $result['refresh_token'],
                'id_token'          => $result['id_token'],
                'raw_token'         => $q
            );
            foreach ($data as $k => $v) $this->request[$k] = $v;

            $this->log($this->active_request_id, "DEBUG", "Updating Request");
            $result = $this->saveRequest();
            return ($result ? $this->request['access_token'] : $result);
        } else {
            $this->log("Cannot refresh the MS token - no refresh token available");
            // No code
            $result = false;
        }
        return $result;
    }


    /**
     * Returns an authentication header for API requests
     * @return array
     */
    public function getAuthHeader() {
        //Authorization: Bearer eyJ0eX...5iEmbDp-Q
        return array("Authorization: Bearer " . $this->request['access_token'], "Content-Type: application/json");
    }


    /**
     * Save the current request to REDCap
     */
    public function saveRequest() {
        $this->log("In saveRequest");

        $data = $this->request;
        $q = REDCap::saveData($this->token_pid, 'json', json_encode(array($data)));
        if (!empty($q['errors'])) {
            $this->error("Error saving Request");
            $this->log($q,"ERROR", "Error saving Request");
            return false;
        }
        $this->log("Request Saved", "DEBUG", "");
        return true;
    }


    // Add error to object and log it
    public function error($message) {
        $this->errors[] = $message;
        $this->log($message, "ERROR", "");
    }


    // Return bootstrap error alert div if present
    public function getErrors() {
        $msg = array(); // = ""; //[] = "<div class='alert alert-info text-center mb-10 fade in' data-dismiss='alert'>";
        foreach ($this->errors as $error) {
            $msg[] = "<p><strong>" . $error . "</strong></p>";
        }

        $result = (empty($msg) ? "" :  "<div class='alert alert-info text-center mb-10 fade in' data-dismiss='alert'>" . implode("\n",$msg) . "</div>");
        return $result;
    }

    /*
    // Make an HTTP GET request
    private function http_get($url="", $timeout=null, $basic_auth_user_pass="", $headers=array())
    {
        $this->log("In http_get");

        // Try using cURL first, if installed
        if (function_exists('curl_init'))
        {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_AUTOREFERER, true);
            curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($curl, CURLOPT_VERBOSE, 0);
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_HTTPGET, true);
            if (!sameHostUrl($url)) {
                curl_setopt($curl, CURLOPT_PROXY, PROXY_HOSTNAME); // If using a proxy
                curl_setopt($curl, CURLOPT_PROXYUSERPWD, PROXY_USERNAME_PASSWORD); // If using a proxy
            }
            curl_setopt($curl, CURLOPT_FRESH_CONNECT, 1); // Don't use a cached version of the url
            if (is_numeric($timeout)) {
                curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $timeout); // Set timeout time in seconds
            }
            // If using basic authentication (username:password)
            if ($basic_auth_user_pass != "") {
                curl_setopt($curl, CURLOPT_USERPWD, $basic_auth_user_pass);
            }

            if (!empty($headers)) {
                curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            }

            $response = curl_exec($curl);
            $info = curl_getinfo($curl);
            curl_close($curl);
            // If returns an HTTP 404 error, return false
            if (isset($info['http_code']) && $info['http_code'] == 404) return false;
            if ($info['http_code'] != '0') return $response;
        }
        return false;
    }
*/


// Send HTTP Post request and receive/return content
    private function http_post($url="", $params=array(), $timeout=null, $content_type='application/x-www-form-urlencoded', $basic_auth_user_pass="", $headers=array())
    {
        $this->log("In http_post");
        // If params are given as an array, then convert to query string format, else leave as is
        if ($content_type == 'application/json') {
            // Send as JSON data
            $param_string = (is_array($params)) ? json_encode($params) : $params;
        } elseif ($content_type == 'application/x-www-form-urlencoded') {
            // Send as Form encoded data
            $param_string = (is_array($params)) ? http_build_query($params, '', '&') : $params;
        } else {
            // Send params as is (e.g., Soap XML string)
            $param_string = $params;
        }

        // Build header
        if ($content_type != 'application/x-www-form-urlencoded') {
            array_push($headers, "Content-Type: $content_type");
            array_push($headers,"Content-Length: " . strlen($param_string));
        }

        // Check if cURL is installed first. If so, then use cURL instead of file_get_contents.
        if (function_exists('curl_init'))
        {
            // Use cURL
            $curlpost = curl_init();
            curl_setopt($curlpost, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($curlpost, CURLOPT_VERBOSE, 0);
            curl_setopt($curlpost, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curlpost, CURLOPT_AUTOREFERER, true);
            curl_setopt($curlpost, CURLOPT_MAXREDIRS, 10);
            curl_setopt($curlpost, CURLOPT_URL, $url);
            curl_setopt($curlpost, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curlpost, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($curlpost, CURLOPT_POSTFIELDS, $param_string);
            if (!sameHostUrl($url)) {
                curl_setopt($curlpost, CURLOPT_PROXY, PROXY_HOSTNAME); // If using a proxy
                curl_setopt($curlpost, CURLOPT_PROXYUSERPWD, PROXY_USERNAME_PASSWORD); // If using a proxy
            }
            curl_setopt($curlpost, CURLOPT_FRESH_CONNECT, 1); // Don't use a cached version of the url
            if (is_numeric($timeout)) {
                curl_setopt($curlpost, CURLOPT_CONNECTTIMEOUT, $timeout); // Set timeout time in seconds
            }
            // If using basic authentication (username:password)
            if ($basic_auth_user_pass != "") {
                curl_setopt($curlpost, CURLOPT_USERPWD, $basic_auth_user_pass);
            }
//            // If not sending as x-www-form-urlencoded, then set special header
//            if ($content_type != 'application/x-www-form-urlencoded') {
//                curl_setopt($curlpost, CURLOPT_HTTPHEADER, array("Content-Type: $content_type", "Content-Length: " . strlen($param_string)));
//            }
            if (!empty($headers)) {
                curl_setopt($curlpost, CURLOPT_HTTPHEADER, $headers);
            }

            $response = curl_exec($curlpost);
            $info = curl_getinfo($curlpost);
            curl_close($curlpost);
            // If returns an HTTP 404 error, return false
            if (isset($info['http_code']) && $info['http_code'] == 404) return false;
            if ($info['http_code'] != '0') return $response;
        }
        // Return false
        return false;
    }


    // Static log function for the MSApi Method
    public static function log() {
        $args = func_get_args();
        call_user_func('SNP::log', $args);
    }


}