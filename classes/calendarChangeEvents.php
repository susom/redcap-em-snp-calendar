<?php
namespace Stanford\SNP;
/** @var \Stanford\SNP\SNP $module */

use \REDCap;


class calendarChangeEvents
{
    private $calendarPID;
    private $event_id;
    private $eventsArm;
    private $calendar_id;
    private $token_id;

    private $studies_options;
    private $status_options;

    private $event_list = array();
    private $return_msg;

    // This checks SNP outlook calendars against appointments in Redcap PID 10062

    public function __construct() {

        global $project_id, $module;
        // Calendar appointments

        SNP::sLog("*** Checking for differences between Outlook calendars and Redcap ****");
        $this->calendarPID = $project_id;
        $this->event_id = $module->getProjectSetting('appt_event_id');
        $this->calendar_id = $module->getProjectSetting('calendar_event_id');
        $this->token_id = $module->getProjectSetting('token_event_id');
        $this->eventsArm = REDCap::getEventNames(true, false, $this->event_id);

        $this->return_msg = null;

        $fields = array('vis_study', 'vis_status');
        $data_dict = REDCap::getDataDictionary($this->calendarPID, 'array', TRUE, $fields);

        // Create the list of studies
        $choice_list = $data_dict['vis_study']['select_choices_or_calculations'];
        $exploded = explode('|',$choice_list);
        foreach ($exploded as $value) {
            $temp = explode(',',$value);
            $this->studies_options[trim($temp[0])]= trim($temp[1]);
        }
        // Create the list of statuses
        $choice_list = $data_dict['vis_status']['select_choices_or_calculations'];
        $exploded = explode('|',$choice_list);
        foreach ($exploded as $value) {
            $temp = explode(',',$value);
            $this->status_options[trim($temp[0])]= trim($temp[1]);
        }

    }

    public function getModifiedCalendarEventList() {

        // See how long this takes
        $now = date('Y-m-d H:i:s');
        SNP::sLog("Start time: $now");

        // Retrieve all calendars that have the cal_in_use flag set
        $all_modified_events = array();
        $filter = "[cal_in_use] = '1'";
        $calendars = REDCAP::getData($this->calendarPID, 'array', null, null, $this->calendar_id, null, null, null, null, $filter);

        foreach($calendars as $key => $value) {
            $this_cal = $value[$this->calendar_id];
            SNP::sLog( "Calendar: " . $this_cal['record_id'] . " and values: " . implode(',', $this_cal));
            if (is_null($this_cal['delta_url']) or empty($this_cal['delta_url'])) {
                // Setup a post request to retrieve the changes to this calendar. Since no delta URL has been
                // saved, we are assuming this is the first time it is used.  We are setting the end time to be way
                // out in the future so we can keep using this delta URL which tracks the last time it was run.
                //$request_url = 'https://graph.microsoft.com/v1.0/me/calendars/' . $this_cal['cal_id'] . '/calendarView/delta?startDateTime=2015-01-01T00:00:00&endDateTime=2050-12-31T23:59:59';
                $request_url = 'https://graph.microsoft.com/v1.0/me/calendars/' . $this_cal['cal_id'] . '/calendarView/delta?startDateTime=2018-04-01T00:00:00&endDateTime=2018-04-20T23:59:59';
            } else {
                // We have a delta URL for this calendar so we just need to post it to retrieve changes since the last run
                $request_url = $this_cal['delta_url'];
            }

            // Make sure we have a valid token before making a request
            $authToken = new MSGraphAPI($this->calendarPID, $this->token_id);
            $token = $authToken->getValidToken();
            if (is_null($token) or empty($token)) {
                $this->return_msg .= "<br>Cannot retrieve a valid token";
                SNP::sLog("Cannot retrieve a valid token");
                return null;
            }

            $modified_events = self::getModifiedEvents($request_url, $token, $this_cal['cal_name']);
            $all_modified_events = array_merge($all_modified_events, $modified_events);
        }

        $now = date('Y-m-d H:i:s');
        SNP::sLog("Finished retrieving differences time: $now");

        return $all_modified_events;
    }


    private function getModifiedEvents($request_url, $token, $calendar_name) {

        $header = array('Authorization: Bearer ' . $token);
        $delta_link = null;

        while (!is_null($request_url)) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $request_url);
            curl_setopt($ch, CURLOPT_HTTPGET, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_VERBOSE, true);

            $jsonResponse = curl_exec($ch);
            $info = curl_getinfo($ch);
            curl_close($ch);

            $http_code = $info['http_code'];
            SNP::sLog("This is the post return code for calendar " . $http_code);
            if ($http_code == 200) {
                $this->return_msg .= "This is the return code from POST " . $http_code . "<br>";
                $eventList = json_decode($jsonResponse, true);
                $events = $eventList['value'];
                $request_url = $eventList['@odata.nextLink'];
                $delta_link = $eventList['@odata.deltaLink'];
                SNP::sLog("request url " . $request_url . " delta link " . $delta_link);
                $format = 'Y-m-d H:i';

                for ($ncount = 0; $ncount < count($events); $ncount++) {
                    $deleted = (is_null($events[$ncount]['@removed']) || empty($events[$ncount]['@removed']) ? 0 : 1);
                    $filter = "[event_id_hex] = '" . bin2hex($events[$ncount]['id']) . "'";
                    $this_event = REDCAP::getData($this->calendarPID, 'array', null, null, $this->event_id, null, null, null, null, $filter);
                    if (is_null($this_event) or empty($this_event)) {
                        $record = null;
                        $saved_record_id = null;
                    } else {
                        $key = array_keys($this_event);
                        $record = $this_event[$key[0]][$this->event_id];
                        $saved_record_id = $record['record_id'];
                    }

                    $orig_start_time = $events[$ncount]['start']['dateTime'];
                    $start_time = substr(str_replace('T', ' ', $orig_start_time), 0, strpos($orig_start_time, '.'));
                    $orig_end_time = $events[$ncount]['end']['dateTime'];
                    $end_time = substr(str_replace('T', ' ', $orig_end_time), 0, strpos($orig_end_time, '.'));

                    if (!is_null($start_time) and !empty($start_time)) {
                        $appt_date = $this->toDateTimeObject($orig_start_time, 'Y-m-d');
                        $appt_start_time = $this->toDateTimeObject($orig_start_time, 'H:i');
                        $start_date_time = $this->toDateTimeObject($orig_start_time, 'Y-m-d H:i');
                    } else {
                        $start_date_time = null;
                    }
                    if (!is_null($end_time) and !empty($end_time)) {
                        $appt_end_time = $this->toDateTimeObject($end_time, 'H:i');
                        $end_date_time = $this->toDateTimeObject($end_time, 'Y-m-d H:i');
                    } else {
                        $end_date_time = null;
                    }

                    if ($deleted and (is_null($record) or empty($record) or ($this->status_options[$this_event['vis_status']] !== 'Deleted'))) {
                        // This appointment was deleted from Outlook but it is okay because Redcap either doesn't
                        // have the appointment or the status is Deleted - so they match.

                    } else if ($deleted) {
                        // Outlook deleted this appointment but Redcap still has it and the status is not set to Deleted.
                        $this->addModifiedEvent($events[$ncount], $start_date_time, $end_date_time, $calendar_name, $saved_record_id, "Deleted from Outlook and REDCap status is not set to Deleted");
                    } else {
                        if (is_null($record) or empty($record)) {
                            // This event was modified but we don't have it in redcap.  Someone must have created it directly
                            // On the calendar
                            $this->addModifiedEvent($events[$ncount], $start_date_time, $end_date_time, $calendar_name, $saved_record_id, "This event is in Outlook but not in REDCap");
                        } else if ($events[$ncount]['changeKey'] != $record['changekey']) {
                            // This appointment was modified so make sure matches Redcap
                            // Parse the subject line to see if the participant, study or note has changed
                            $subject_parsed = explode('|', $events[$ncount]['subject']);
                            // There might be CX (cancelled), RS (rescheduled) or NS (no-show) in the first position
                            // If so, the second position holds participant
                            $statuses = array('CX', 'RS', 'NS');
                            $is_status = array_intersect(array(trim($subject_parsed[0])), $statuses);
                            if (is_null($is_status) or empty($is_status)) {
                                $ppid = trim($subject_parsed[1]);
                                $study = trim($subject_parsed[2]);
                                $visit = trim($subject_parsed[3]);
                                $note = trim($subject_parsed[4]);
                            } else {
                                $ppid = trim($subject_parsed[2]);
                                $study = trim($subject_parsed[3]);
                                $visit = trim($subject_parsed[4]);
                                $note = trim($subject_parsed[5]);
                            }

                            if ($appt_date != $record['vis_date']) {
                                $msg = "The appt date in Outlook is " . $appt_date . " and in Redcap " . $record['vis_date'];
                                $this->addModifiedEvent($events[$ncount], $start_date_time, $end_date_time, $calendar_name, $saved_record_id, $msg);
                                //print "This appt date was modified and does not match Redcap (Subject: " . $events[$ncount]['subject'] . ") <br>" . "Outlook date: " . $appt_date . " and Redcap date: " . $record['vis_date'] . "<br>";
                            } else if ($appt_start_time != $record['vis_start_time']) {
                                $msg = "The appt start time in Outlook is " . $appt_start_time . " and in Redcap " . $record['vis_start_time'];
                                $this->addModifiedEvent($events[$ncount], $start_date_time, $end_date_time, $calendar_name, $saved_record_id, $msg);
                                //print "This appt start time was modified and does not match Redcap (Subject: " . $events[$ncount]['subject'] . ") <br>";
                            } else if ($appt_end_time != $record['vis_end_time']) {
                                $msg = "The appt end time in Outlook is " . $appt_end_time . " and in Redcap " . $record['vis_end_time'];
                                $this->addModifiedEvent($events[$ncount], $start_date_time, $end_date_time, $calendar_name, $saved_record_id, $msg);
                                //print "This appt end time was modified and does not match Redcap (Subject: " . $events[$ncount]['subject'] . ") <br>";
                            } else if ($ppid != $record['vis_ppid']) {
                                $msg = "The appt participant in Outlook is " . $ppid . " and in Redcap " . $record['vis_ppid'];
                                $this->addModifiedEvent($events[$ncount], $start_date_time, $end_date_time, $calendar_name, $saved_record_id, $msg);
                                //print "This appt participant was modified and does not match Redcap (Subject: " . $events[$ncount]['subject'] . ") <br>";
                            } else if ($study != $this->studies_options[$record['vis_study']]) {
                                $msg = "The appt study in Outlook is " . $study . " and in Redcap " . $this->studies_options[$record['vis_study']];
                                $this->addModifiedEvent($events[$ncount], $start_date_time, $end_date_time, $calendar_name, $saved_record_id, $msg);
                                //print "This appt study was modified and does not match Redcap (Subject: " . $events[$ncount]['subject'] . ") <br>";
                            } else if ($visit != $record['vis_name']) {
                                $msg = "The appt study type in Outlook is " . $visit . " and in Redcap " . $record['vis_name'];
                                $this->addModifiedEvent($events[$ncount], $start_date_time, $end_date_time, $calendar_name, $saved_record_id, $msg);
                                //print "This appt study type was modified and does not match Redcap (Subject: " . $events[$ncount]['subject'] . ") <br>";
                            } else if ($note != $record['vis_note']) {
                                $msg = "The appt note in Outlook is " . $note . " and in Redcap " . $record['vis_note'];
                                $this->addModifiedEvent($events[$ncount], $start_date_time, $end_date_time, $calendar_name, $saved_record_id, $msg);
                                //print "This appt note was modified and does not match Redcap (Subject: " . $events[$ncount]['subject'] . ") <br>";
                            } else {
                                $msg = "Something has changed with this appointment but not sure what.";
                                $this->addModifiedEvent($events[$ncount], $start_date_time, $end_date_time, $calendar_name, $saved_record_id, $msg);
                            }
                        }
                    }
                }

            } else {
                $this->return_msg .= "<br>The return code from POST message is " . $http_code . ". Was not able to retrieve Outlook appointments.";
                SNP::sLog("The return code from POST message is " . $http_code . ". Was not able to retrieve Outlook appointments.");
                return null;
            }


            // Save the delta Link
            /*
            if (!is_null($delta_link) and !empty($delta_link)) {
                $data = array($this_event['record_id']);
                REDCap::saveData($this->calendarPID, 'array', array($data));
            }
            */
        }

        // Finally return the events that we have
        if (is_null($this->event_list) or empty($this->event_list)) {
            $this->return_msg .= "<br><b>There are no discrepancies between Outlook and REDCap</b>";
            return null;
        } else {
            return $this->event_list;
        }

    }

    public function getReturnMessage() {
        return $this->return_msg;
    }

    private function addModifiedEvent($event, $startdatetime, $enddatetime, $calendar_name, $rc_record_id, $msg) {

        $event_details['subject'] = $event['subject'];
        $event_details['createdDateTime'] = $event['createdDateTime'];
        $event_details['changeKey'] = $event['changeKey'];
        $event_details['iCalUId'] = $event['iCalUId'];
        $event_details['isCancelled'] = $event['isCancelled'];
        $event_details['webLink'] = $event['webLink'];
        $event_details['start'] = $startdatetime;
        $event_details['end'] = $enddatetime;
        // If the location is empty, put in the Room based on the calendar
        if (is_null($event['location']['displayName']) or empty($event['location']['displayName'])) {
            $event_details['location'] = $calendar_name;
        } else {
            $event_details['location'] = $event['location']['displayName'];
        }
        $event_details['record_id'] = $rc_record_id;
        $event_details['msg'] = $msg;

        $nevents = count($this->event_list);
        $this->event_list[$nevents] = $event_details;
    }

    public function toDateTimeObject($date_string, $return_format) {
        $dateTime = strtotime($date_string.' UTC');
        $dateInLocal = date($return_format, $dateTime);
        return $dateInLocal;
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

?>
