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
    public $redirect_uri         = "https://redcap.stanford.edu/plugins/snp/calendar/portal/";
    public $response_mode        = "form_post";
    public $auth_scope           = "openid offline_access profile https://graph.microsoft.com/Calendar.ReadWrite";

    // SECTION FOR REQUESTING TOKENS
    public $token_endpoint       = "https://login.windows.net/396573cb-f378-4b68-9bc8-15755c0c51f3/oauth2/token";
    public $client_secret        = "pjrarUF6lZaDh69ACcSWTXG01oSeHcc8SASytqkr1iw=";
    public $resource             = "https://graph.microsoft.com";

    public $username;           // Name of the user using the application (must match the user who is authenticating if there are no valid credentials)
    public $errors;
    public $active_config;      // The active configuration
    public $active_request_id;  // This is the request that is currently in-use
    public $request;            // The actual request record

    public $token_pid;           // REDCap project PID that contains
    public $token_event_id;      // Event ID where token form is located
    public $token_event_name;


    public function __construct($token_pid, $token_event_id)
    {
        // Generate a non-auth uri for MS API Auth post-back as an external module
        // Please note that this url must also be updated in the azure portal for this application
        // Looks like we can just give it the URL that was registered and MS Graph is happy.
        $this->username = USERID;
        $this->token_pid = $token_pid;
        $this->token_event_id = $token_event_id;
        $this->token_event_name = REDCap::getEventNames(true, false, $token_event_id);
        $this->errors = null;
    }

    public function getValidToken()
    {
        // Retrieve the current stored token if it is still valid
        $token = $this->findValidToken();
        if (empty($token) or $token === false) {

            // Need to reauthorize since we cannot find a valid token
            SNP::sLog("Need to re-authorize - Please to go the Authenication Page");
            $this->errors .= "Could not retrieve a valid token - please go to the Authorization Page<br>";

            //$this->authorizeUserRedirect();
            //$token = $this->findValidToken();
            //if (empty($token) or $token === false) {
            //    $this->error("Could not retrieve a valid token even after Re-Authorization");
            //}
        }

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

            if ($now < $token_expires) {
                return $this->request['access_token'];
            } else {
                SNP::sLog("Need to refresh Token");
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
            SNP::sError("Unable to determine the last working authorization");
            $result = false;
        } else {

            $results = json_decode($q, true);
            $this_result = $results[0];

            if (empty($this_result['request_id'])) {
                // No authorization record found - reauthorize
                SNP::sError("There is no active authorization record.");
                $result = false;
            } else {
                // Found the current active record - load it to make sure the token is still valid
                $this->active_config = $this_result['record_id'];
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
            $this->errors .= "Unable to load active request id record " . $request_id . "<br>";
            SNP::sError("Unable to load active request id: " . $request_id);
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
        SNP::log("In isRequestValid");

        $result = false;

        $not_before = $this->request['not_before'];
        $expires_on = $this->request['expires_on'];
        $result = false;
        if (!empty($not_before) && !empty($expires_on) ) {
            // request has timestamps
            $now = strtotime("now");
            if (strtotime($not_before) > $now) {
                SNP::error("It is too early to use this token");
            } elseif ($now >= strtotime($expires_on) ) {
                SNP::error("This token has expired");

                // Should we try to renew it here?  Probably
                $result = $this->refreshTokens();
                SNP::log($result, "DEBUG", "Result from this->refreshTokens");

            } else {
                // This token is valid!
                $result = true;
            }
        } else {
            SNP::error("The current request has invalid timestamps");
        }
        SNP::log($result, "DEBUG", "Determining if Request is valid!");
        return $result;
    }
*/


    public function authorizeUserRedirect() {

        // Create record in REDCap to mark authentication request
        $next_id = Util::getNextId($this->token_pid, 'record_id',
                                    $this->token_event_id, null);
        $scope_verification_token = uniqid();
        $data = array(
            'record_id'                         => $next_id,
            'redcap_event_name'                 => $this->token_event_name,
            'username'                          => $this->username,
            'scope_verification_token'          => $scope_verification_token
        );
        $q = REDCap::saveData($this->token_pid, 'json', json_encode(array($data)));
        //print "These are the returned values from saveData " . implode(',', array_keys($q)) . "<br>";
        //print "These are the errors: " . implode(',', $q['errors']) . "<br>";
        //print "These are the warnings: " . implode(',',$q['warnings']) . "<br>";
        //print "These are the ids: " . implode(',',$q['ids']) . "<br>";
        //print "These are the item_count: " . $q['item_count'] . "<br>";
        if (!empty($q['errors'])) {
            // An error occurred creating the record
            SNP::sLog($q,"ERROR","Error creating new authorization record");
            $this->errors .= "Error creating new authorization record - see logs for details<br>";
            return false;
        }

        // Load and update the active config record
        if (!$this->loadActiveConfig()) {
            SNP::sLog($q,"ERROR","Error could not load active configuration");
            $this->errors .= "Error loading active configuration - see logs for details<br>";
            return false;
        }

        $this->saveActiveConfig($next_id);
        SNP::sLog($q, "DEBUG", "Created new request $next_id");

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
        SNP::sLog($url, "DEBUG", "Redirecting to Microsoft");
        redirect($url);
        exit();
    }


    /**
     * Process an authentication post-back from Microsoft to our endpoint
     */
    public function processAuthorizationPostBack() {

        // Find the active config
        $status = $this->loadActiveConfig();

        // Check for error from Microsoft
        if (isset($_POST['error'])) {
            $this->errors .= "ERROR: Error with incoming microsoft authorization<br>";
            SNP::sError($_POST, "ERROR", "Error with incoming microsoft authorization");
            return false;
        } elseif (!isset($_POST['state'])) {
            // Missing POST state
            SNP::sError($_POST, "DEBUG", "Invalid post-back - no state");
            $this->errors .= "DEBUG: Invalid post-back - no state <br>";
            return false;
        }

        $state = json_decode($_POST['state'], true);
        $type = $state['type'];
        $request_id = $state['request_id'];
        $hash = $state['hash'];

        if ($type == 'authorize') {
            // Retrieve the current active record
            $status = $this->loadRequest($request_id);
            if ($status != true) {
                $this->errors .= "Could not retrieve the Redcap token record " . $request_id . "<br>";
                SNP::sLog("Could not retrieve record $request_id " , "DEBUG", "from loadRequest");
                return false;
            }

            // Verify hash in post matches hash's unique id from database
            $scope_verification_token = $this->request['scope_verification_token'];
            if ($hash != md5($scope_verification_token)) {
                $this->errors .= "Hash mismatch with request<br>";
                SNP::sLog("Hash is $hash, scope_verification_token is $scope_verification_token, md5 of scope_verification_token is " . md5($scope_verification_token), "DEBUG", "Hash Verification Failed");
                return false;
            }

            // Store the authorization results to the REDCap database
            $id_token = isset($_POST['id_token']) ? $_POST['id_token'] : "";
            $code = isset($_POST['code']) ? $_POST['code'] : "";
            $data = array(
                'record_id'         => $request_id,
                'redcap_event_name' => $this->token_event_name,
                'ts'                => date('Y-m-d H:i:s'),
                'raw_response'      => json_encode($_POST),
                'type'              => $type,
                'id_token'          => $id_token,
                'code'              => $code
            );
            if (!empty($id_token)) {
                $parts = explode(".", $id_token);
                $p1 = json_decode(base64_decode($parts[0]), true);
                $p2 = json_decode(base64_decode($parts[1]), true);
                $data['jwt_payload'] = $p2;
            }

            // Pull new tokens using the new code
            $result = $this->getTokensFromCode($code);
            if (!is_null($result) and !empty($result)) {
                $final_result = array_merge($data, $result);

                // Save Authentication data to REDCap
                $q = REDCap::saveData($this->token_pid, 'json', json_encode(array($final_result)));
                if (!empty($q['errors'])) {
                    $this->errors .= "Error saving authorization data<br>";
                    SNP::sLog($q, "ERROR", "Error saving authorization data");
                    return false;
                }
                SNP::sLog($request_id, "DEBUG", "Authorization Saved");
            }

            SNP::sLog("DEBUG", "New authenication record is saved", $request_id, " and ACTIVE-CONFIG is updated");
            return true;
        }

        SNP::sLog("Invalid type: $type","ERROR");
        return false;
    }


    //With a new authentication code, obtain the first set of tokens
    public function getTokensFromCode($code) {

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

            $q = http_post($url,$params);
            $result = json_decode($q,true);
            SNP::sLog($params, "DEBUG", "Get Tokens for $resource Params");
            SNP::sLog($result, "DEBUG", $resource);

            $data = array(
                'not_before'        => isset($result['not_before']) ? date('Y-m-d H:i:s', $result['not_before']) : '',
                'expires_on'        => isset($result['expires_on']) ? date('Y-m-d H:i:s', $result['expires_on']) : '',
                'access_token'      => $result['access_token'],
                'refresh_token'     => $result['refresh_token'],
                'id_token'          => $result['id_token'],
                'raw_token'         => $q
            );
        } else {
            // No code
            $data = null;
        }

        return $data;
    }


    /**
     * Load the current auth record from the SNP Calednar Assistant Configuration project for this user
     */
    public function saveActiveConfig($request_id) {
        $data = array(
            'record_id'         => $this->active_config,
            'redcap_event_name' => $this->token_event_name,
            'request_id'        => 'ACTIVE-CONFIG',
            'ac_request_id'     => $request_id
        );

        $q = REDCap::saveData($this->token_pid, 'json', json_encode(array($data)));
        print "These are the returned values from saveData " . implode(',', array_keys($q)) . "<br>";
        print "These are the errors: " . implode(',', $q['errors']) . "<br>";
        print "These are the warnings: " . implode(',',$q['warnings']) . "<br>";
        print "These are the ids: " . implode(',',$q['ids']) . "<br>";
        print "These are the item_count: " . $q['item_count'] . "<br>";
        if (!empty($q['errors'])) {
            SNP::sLog($q['errors'], "ERROR", "Error at saveActiveConfig");
            $this->errors .= "Error setting saveActiveConfig<br>";
            return false;
        }
        return true;
    }


    /**
     * Refresh an existing token
     * @return bool|mixed|stdClass
     */
    public function refreshTokens() {

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

            SNP::sLog($params, "DEBUG", "Refreshing token at $url");
            $q = http_post($url,$params);

            SNP::sLog($q,"DEBUG", "RefreshToken Raw Response");
            $result = json_decode($q,true);
            if (!empty($result['error'])) {
                // Errors in response
                SNP::sLog($result, "ERROR", "Error refreshing token");
                $this->errors .= "Error refreshing token - see logs<br>";
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

            SNP::sLog($this->active_request_id, "DEBUG", "Updating Request");
            $result = $this->saveRequest();
            return ($result ? $this->request['access_token'] : $result);
        } else {
            SNP::sLog("Cannot refresh the MS token - no refresh token available");
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

        $data = $this->request;
        $q = REDCap::saveData($this->token_pid, 'json', json_encode(array($data)));
        if (!empty($q['errors'])) {
            $this->errors .= "Error saving Request<br>";
            SNP::sLog($q,"ERROR", "Error saving Request");
            return false;
        }
        return true;
    }


    // Return bootstrap error alert div if present
    public function getErrors() {
        /*
        $msg = array(); // = ""; //[] = "<div class='alert alert-info text-center mb-10 fade in' data-dismiss='alert'>";
        foreach ($this->errors as $error) {
            $msg[] = "<p><strong>" . $error . "</strong></p>";
        }

        $result = (empty($msg) ? "" :  "<div class='alert alert-info text-center mb-10 fade in' data-dismiss='alert'>" . implode("\n",$msg) . "</div>");
        return $result;
        */
        return $this->errors;
    }

    /*
    // Make an HTTP GET request
    private function http_get($url="", $timeout=null, $basic_auth_user_pass="", $headers=array())
    {
        SNP::log("In http_get");

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


}