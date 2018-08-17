<?php
namespace Stanford\SNP;
/** @var \Stanford\SNP\SNP $module */

use \REDCap;
use \Records;

//require_once ('./MSGraphAPI.php');

class Appt
{
    // Data Dictionary selections for studies
    private $studies_options = array();
    private $room_options = array();
    private $status_options = array();

    // Appointment project pid and event arms
    private $appt_pid;
    private $appt_event_id;
    private $calendar_event_id;
    private $token_event_id;

    // Resource URLs
    private $requestURL = "https://graph.microsoft.com";
    private $redcapURL = "https://redcap.stanford.edu/redcap_v8.1.0/DataEntry/index.php?pid=10062&id=";
    private $redcapURLend = '&page=appointment';

    public function __construct($pid) {
        global $module;

        $this->appt_pid = $pid;
        $this->appt_event_id = $module->getProjectSetting('appt_event_id');
        $this->calendar_event_id = $module->getProjectSetting('calendar_event_id');
        $this->token_event_id = $module->getProjectSetting('token_event_id');

        // We need the data dictionary for the study list and study room so we can translate
        // the raw value to the label to store in the Outlook calendar
        $fields = array('vis_study', 'vis_room', 'vis_status');
        $dd_studies = REDCap::getDataDictionary($this->appt_pid, 'array', TRUE, $fields);

        // Create the list of studies
        $choice_list = $dd_studies['vis_study']['select_choices_or_calculations'];
        $exploded = explode('|',$choice_list);
        foreach ($exploded as $value) {
            $temp = explode(',',$value);
            $this->studies_options[trim($temp[0])]= trim($temp[1]);
        }

        // Create the list of rooms
        $choice_list = $dd_studies['vis_room']['select_choices_or_calculations'];
        $exploded = explode('|',$choice_list);
        foreach ($exploded as $value) {
            $temp = explode(',',$value);
            $this->room_options[trim($temp[0])]= trim($temp[1]);
        }
        // Create the list of appointment statuses
        $status_list = $dd_studies['vis_status']['select_choices_or_calculations'];
        $exploded = explode('|',$status_list);
        foreach ($exploded as $value) {
            $temp = explode(',',$value);
            $this->status_options[trim($temp[0])]= trim($temp[1]);
        }
    }

    public function saveOrUpdateCalendarEvent($record)
    {
        SNP::sLog("Saving record: " . $record['record_id']);
        // Make sure we have a valid token before making a request
        $msGraph = new msGraphApi($this->appt_pid, $this->token_event_id);
        $token = $msGraph->getValidToken();
        if (is_null($token) or empty($token)) {
            $return_msg = "Valid token not found - cannot update Outlook";
            SNP::sLog($return_msg);
            $response = array("status" => false,
                            "message" => $return_msg);
            return $response;
        }

        $header = array('Authorization: Bearer ' . $token,
                        'Content-Type: application/json');

        // Retrieve REDCap record that we are saving or updating
        $saved_record_data = $this->getEventFromRedcap($record['record_id']);

        // Retrieve the calendar ID for this appointment. The visit room coded value is the same number as the calendar record_id
        if (is_null($record['vis_room']) or empty($record['vis_room'])) {
            $return_msg = "Appointment cannot be saved in Outlook until a Room is selected!";
            SNP::sLog($return_msg);
            $response = array("status" => false,
                            "message" => $return_msg);
            return $response;
        }

        // Retrieve the calendar id and format the data to Outlook API
        $cal_id = $this->getCalendar($record['vis_room']);
        $outlook_record = $this->formatForOutlook($record);

        // If the Event ID is not null (Outlook ID), then this is an update otherwise it is a new appointment
        if (is_null($saved_record_data['eventid']) or empty($saved_record_data['eventid'])) {

            // New appointment to save to Outlook
            $request = $this->requestURL . '/v1.0/me/calendars/' . $cal_id . '/events';
            $response = $this->post_request("save", $request, $header, $outlook_record);

            if ($response === FALSE) {
                $return_msg = "Could not save Redcap record " . $record['record_id'] . " in Outlook calendar!";
                $return = false;
            } else {
                $return_msg = "Saved Redcap record " . $record['record_id'] . " in Outlook calendar!";
                SNP::sLog($return_msg);

                // Update the saved data with the new data entered on the modal
                foreach ($record as $field => $value) {
                    $saved_record_data[$field] = $value;
                }

                // Now save the Outlook fields in Redcap
                $rc_response = $this->saveRedcapAppt($response, $saved_record_data);
                $return = $rc_response["status"];
                $return_msg = $rc_response["message"];
            }
            SNP::sLog($return_msg);

        } else {
            // Update existing Outlook appointment
            // First check to see if this appointment has switched rooms which means it needs to switch calendars
            if (!is_null($saved_record_data['vis_room']) and ($record['vis_room'] != $saved_record_data['vis_room'])) {

                // Delete this appointment from the previous calendar
                $request = $this->requestURL . '/v1.0/me/events/' . $saved_record_data['eventid'];
                $response = $this->post_request("delete", $request, $header);

                if (!is_null($response)) {

                    // The delete from previous calendar was successful, so save this appointment to the new calendar
                    $request = $this->requestURL . '/v1.0/me/calendars/' . $cal_id . '/events';
                    $response = $this->post_request("save", $request, $header, $outlook_record);
                    //$response  = $this->saveOutlookEvent($request, $header, $outlook_record);
                    if (!is_null($response)) {
                        // Update the saved data with the new data entered on the modal
                        foreach ($record as $field => $value) {
                            $saved_record_data[$field] = $value;
                        }

                        $rc_response = $this->saveRedcapAppt($response, $saved_record_data);
                        $return = $rc_response["status"];
                        $return_msg = $rc_response["message"];
                    } else {
                        $return_msg = "Save to new Outlook calendar was not successful for record ID: " . $record['record_id'];
                        $return = false;
                    }
                } else {
                    $return_msg = "Could not delete event from Outlook calendar so no updates were performed for record ID: ". $record['record_id'];
                    $return = false;
                }
                SNP::sLog($return_msg);

            } else {
                // This appointment is just updated in the room so update this appointment in Outlook
                $request = $this->requestURL . '/v1.0/me/events/' . $saved_record_data['eventid'];
//                $response = $this->updateOutlookEvent($request, $header, $outlook_record);
                $response = $this->post_request("update", $request, $header, $outlook_record);

                if ($response === FALSE) {
                    $return_msg = "Did not update Outlook event " . $record['record_id'] . ". REDCAP and Outlook are out of sync!!!";
                    $return = false;
                } else {
                    $return_msg = "Updated Outlook event for record " . $record['record_id'];
                    SNP::sLog($return_msg);

                    // Now save the Outlook fields in Redcap
                    $rc_response = $this->saveRedcapAppt($response, $record);
                    $return_msg = $rc_response["message"];
                    $return = $rc_response["status"];
              }

               SNP::sLog($return_msg);
            }
        }
        $response = array("status" => $return,
                            "message" => $return_msg);
        return $response;
    }


    public function deleteCalendarEvent($record_id, $event_id, $user=null) {

        $return = false;
        // Retrieve record so we know where to delete from
        $record = $this->getEventFromRedcap($record_id);

        // Make sure we have a valid token before making a request
        if (!is_null($record['icaluid']) and !empty($record['icaluid'])) {
            $msGraph = new msGraphApi($this->appt_pid, $this->token_event_id);
            $token = $msGraph->getValidToken();
            if (is_null($token) or empty($token)) {
                $return_msg = "Valid token not found - cannot delete Outlook event for record " . $record_id;
                SNP::sLog($return_msg);
                exit();
            }

            $header = array('Authorization: Bearer ' . $token);
            $request = $this->requestURL . '/v1.0/me/events/' . $record['eventid'];
            $response = $this->post_request("delete", $request, $header);

            if ($response === false) {
                $return_msg = "Did not delete Outlook event " . $record_id . ". REDCAP is not up-to-date!!!";
            } else {
                $return_msg = "Deleted Outlook event for record: " . $record_id;
                SNP::sLog($return_msg);

                // Now delete the Redcap record
                $rc_response = $this->deleteRedcapAppt($record_id, $event_id, $user);
                if ($rc_response === false) {
                    $return_msg = "Did not delete Redcap record " . $record_id . ". REDCAP does not match Outlook!!!";
                } else {
                    $return_msg = "Deleted Redcap record " . $rc_response;
                    $return = true;
                }
            }
        } else {
            $return_msg = "Record ID " . $record_id . " was not on a calendar so nothing was deleted.";
            $return = true;
        }
        SNP::sLog($return_msg);
        $response = array("status" => $return,
                            "message" => $return_msg);
        return $response;
    }


    private function post_request($request, $requestURL, $header, $body=null) {

        if ($request === "save") {
            $success_return_code = 201;
            $which_post_request = "POST";
        } else if ($request === "update") {
            $success_return_code = 200;
            $which_post_request = "PATCH";
        } else if ($request === "delete") {
            $success_return_code = 204;
            $which_post_request = "DELETE";
        } else {
            SNP::sError("APPT:", "Bad post request: " . $request);
            exit();
        }

        SNP::sLog("This is the header: " . json_encode($header));
        SNP::sLog("This is the request: " . $request);
        SNP::sLog("This is the request URL: " . $requestURL);
        SNP::sLog("This is the body: " . $body);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $requestURL);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $which_post_request);

        $json_response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $http_description = self::translateHTTPCode($http_code);
        SNP::sLog("This is the return value from post: " . $http_code);

        if ($http_code === $success_return_code) {
            return json_decode($json_response, true);
        } else {
            return false;
        }
    }

    private function getCalendar($cal_record_id) {

        // Retrieve the calendar ID for the calendar specified on the webpage (arm 2 in project 10062)
        $calendar_id = REDCAP::getData($this->appt_pid, 'array', $cal_record_id, array('cal_id'), $this->calendar_event_id);
        return $calendar_id[$cal_record_id][$this->calendar_event_id]['cal_id'];
    }

    private function getEventFromRedcap($record_id)
    {
        // Retrieve the event from Redcap that we are going to save in Outlook.
        // Retrieve coded values as labels instead of raw data
        $event = REDCAP::getData($this->appt_pid, 'array', $record_id, null, $this->appt_event_id, null, null, null, null, null, TRUE);
        $details = $event[$record_id][$this->appt_event_id];
        return $details;
    }

    private function deleteRedcapAppt($record_id, $event_id, $user)
    {
        global $Proj;
        //$arm_id = $Proj->eventInfo[$event_id]['arm_id'];
        //$results = Records::deleteRecord($record_id, $Proj->table_pk,$Proj->multiple_arms,$Proj->project['randomization'], $Proj->project['status'],
        //    $Proj->project['require_change_reason'], $arm_id, " (SNP::Delete Appointment)");

        // When we delete the appointment from MS calendar, don't delete the redcap record.
        // We are just deleting the calendar specific information since it is no longer on the calendar and
        // making a note on who deleted it.
        $field_names = REDCap::getValidFieldsByEvents ($this->appt_pid, $this->appt_event_id, false);
        $data = Util::getData($this->appt_pid, $this->appt_event_id, $record_id, null, false);
        $data_to_save = array_intersect_key($data[0], array_flip($field_names));
        $status = array_search('Deleted', $this->status_options);

        $data_to_save['vis_status']              = $status;         // 500 = deleted status
        $data_to_save['vis_on_calendar']         = "0";             // not stored on MS calendar
        $data_to_save['last_update_made_by']     = $user;
        $data_to_save['eventid']                 = null;
        $data_to_save['createddatetime']         = null;
        $data_to_save['lastmodifieddatetime']    = null;
        $data_to_save['icaluid']                 = null;
        $data_to_save['changekey']               = null;
        $data_to_save['appointment_complete']    = null;
        $data_to_save['cal_weblink']             = null;
        $redcap_data[$record_id][$this->appt_event_id] = $data_to_save;

        $rc_response = REDCap::saveData($this->appt_pid, 'array', $redcap_data, 'overwrite');
        SNP::sLog("Error responses from Delete Redcap Data: " . implode(',',$rc_response['errors']));
        SNP::sLog("Warning responses from Delete Redcap Data: " . implode(',',$rc_response['warnings']));
        SNP::sLog("IDs from Delete Redcap Data: " . implode(',',$rc_response['ids']));
        SNP::sLog("Item count from Delete Redcap Data: " . $rc_response['item_count']);

        $string = "Changed status to Delete for Redcap appt record ID: " . $record_id;
        SNP::sLog($string);

        return $record_id;
    }

    public function saveRedcapAppt($outlook_data, $record)
    {
        $redcap_data = array();
        if (!is_null($outlook_data) and !empty($outlook_data)) {
            $create_date = $outlook_data['createdDateTime'];
            $last_mod = $outlook_data['lastModifiedDateTime'];
            $event_id = $outlook_data['id'];
            $event_in_hex = bin2hex($outlook_data['id']);
            $icalid = $outlook_data['iCalUId'];
            $change_key = $outlook_data['changeKey'];
            $weblink = $outlook_data['webLink'];
        } else {
            $create_date = null;
            $last_mod = null;
            $event_id = null;
            $icalid = null;
            $change_key = null;
            $weblink = null;
        }

        $redcap_data[$record['record_id']][$this->appt_event_id] =
            array(
                'eventid'               => $event_id,
                'event_id_hex'          => $event_in_hex,
                'createddatetime'       => $this->toDateTimeObject($create_date,'Y-m-d H:i:s'),
                'lastmodifieddatetime'  => $this->toDateTimeObject($last_mod,'Y-m-d H:i:s'),
                'icaluid'               => $icalid,
                'changekey'             => $change_key,
                'vis_ppid'              => $record['vis_ppid'],
                'vis_date'              => $record['vis_date'],
                'vis_start_time'        => $record['vis_start_time'],
                'vis_end_time'          => $record['vis_end_time'],
                'vis_study'             => $record['vis_study'],
                'vis_name'              => $record['vis_name'],
                'vis_note'              => $record['vis_note'],
                'vis_room'              => $record['vis_room'],
                'vis_status'            => $record['vis_status'],
                'cal_weblink'           => $weblink,
                'vis_on_calendar'       => $record['vis_on_calendar'],
                'last_update_made_by'   => $record['last_update_made_by'],
                'email'                 => $record['email']
            );

        $rc_response = REDCap::saveData($this->appt_pid, 'array', $redcap_data, 'overwrite');

        $string = "Redcap Errors: " . implode(',', $rc_response['errors']) . ", or " . $rc_response['errors'];
        SNP::sLog($string);
        $string = "Redcap Warnings: " . implode(',', $rc_response['warnings']) . ", or " . $rc_response['warnings'];
        SNP::sLog($string);
        $string = "Redcap IDs: " . implode(',', $rc_response['ids']) . ", or " . $rc_response['ids'];
        SNP::sLog($string);
        $string = "Redcap item_count: " . $rc_response['item_count'];
        SNP::sLog($string);

        if ($rc_response['item_count'] > 0) {
            $response = array("status" => true,
                                "message" => "Saved record " . implode(',', $rc_response['ids']) . " in redcap");
            return $response;
        } else {
            $response = array("status" => false,
                            "message" => "Could not save record " . $record['record_id']);
            return $response;
        }
    }

    /*
     * Format the new appointment data into Outlook format
     */
    private function formatForOutlook($new_data) {

        $new_appt_status = $this->status_options[$new_data['vis_status']];
        // Add together the subject line that will be put in Outlook calendar and put together the Redcap URL
        if ($new_appt_status == 'Cancelled') {
            $subject = "CX | ";
        } else if ($new_appt_status == 'No-show') {
            $subject = "NS | ";
        } else if ($new_appt_status == 'Re-scheduled') {
            $subject = "RS | ";
        } else {
            $subject = "";
        }

        $subject .= $new_data['vis_start_time'] . " | " . $new_data['vis_ppid'] . " | " .
                    $this->studies_options[$new_data['vis_study']] . " | " .
                    $new_data['vis_name'] . " | " . $new_data['vis_note'];
        //$redcap_url = substr(APP_PATH_WEBROOT_FULL, 0,  -1) . APP_PATH_WEBROOT . 'DataEntry/index.php?pid=' . $this->appt_pid . '&id=' . $new_data['record_id'] . '&event_id=' . $this->appt_event_id . '&page=appointment';

        // Convert the timestamps to local time

        $eventStartDateTime = $new_data['vis_date'] . ' ' . $new_data['vis_start_time'];
        $eventEndDateTime = $new_data['vis_date'] . ' ' . $new_data['vis_end_time'];

        // In the body of the calendar item, we are putting in any notes that are added and also
        // the Redcap URL to this record and who updated it last

        date_default_timezone_set('America/Los_Angeles');
        $now = strtotime("now");
        $body_content = "Notes: " . $new_data['vis_body'] . "\n\n" .
                        "Redcap_URL: " . $new_data['url'] . "\n\n" .
                        "Last Update Made By: " . $new_data['last_update_made_by'] . " @ " . date('Y-m-d H:i:s', $now);

        $appt_details = array(
            'subject'       =>  $subject,
            'isCancelled'   =>  ($new_appt_status == 'Cancelled' ? TRUE : FALSE),
            'start'         =>  array("dateTime" => $eventStartDateTime,
                                        "timeZone" => "PST8PDT"),
            'end'           =>  array("dateTime" => $eventEndDateTime,
                                        "timeZone" => "PST8PDT"),
            'location'      =>  array("displayName" => $this->room_options[$new_data['vis_room']],
                                        "address" => null),
            'body'          =>  array("contentType"     => "Text",
                                        "content"       => $body_content)
        );

        return json_encode($appt_details);
    }


    public function toDateTimeObject($date_string, $final_format) {
        $format = 'Y-m-d H:i:s';

        // Date string coming back from Outlook is in the form 'yyyy-mm-ddThh:mi:ss.zzzzzzzZ'
        // and we want to reformat it to yyyy-mm-dd hh:mi:ss' in local time.
        if(strlen($date_string) > 19) {
            $new_date_string = substr($date_string, 0, 19) . ' UTC';
        } else {
            $new_date_string = $date_string . ' UTC';
        }

        date_default_timezone_set('America/Los_Angeles');
        $local_time = strtotime($new_date_string);
        return date($final_format, $local_time);

    }


    private function translateHTTPCode($http_code) {

        $http_decode = array(
            200=>'OK',
            201=>'Created',
            202=>'Accepted',
            204=>'No content',
            301=>'Moved permanently',
            302=>'Found',
            303=>'See Other',
            304=>'Not modified',
            307=>'Temporary Redirect',
            400=>'Bad Request',
            401=>'Unauthorized',
            403=>'Forbidden',
            404=>'Not Found',
            405=>'Method Not Allowed',
            406=>'Not Acceptable',
            412=>'Precondition Failed',
            415=>'Unsupported Media Type',
            500=>'Internal Server Error',
            501=>'Not implemented'
        );

        return $http_decode[$http_code];
    }

}
