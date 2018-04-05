<?php
namespace Stanford\SNP;
/** @var \Stanford\SNP\SNP $module */

use \REDCap;


// Retrieve Registry project info
$registry_pid = $module->getProjectSetting('registry_pid');
$registry_first_event = $module->getProjectSetting('registry_first_event');

// Retrieve Appointment project info
$appt_event = $module->getProjectSetting('appt_event_id');

// Retrieve pid from calling project
$appt_pid = isset($_GET['pid']) && !empty($_GET['pid']) ? $_GET['pid'] : null;

$template_path = $module->getModulePath() . "data/template.txt";
$icon_urls = array( "note"      => APP_PATH_IMAGES . "document_edit.png",
                    "calendar"  => APP_PATH_IMAGES . "date.png"
                    );
SNP::log("Path to images: " . $icon_urls['note']);

//Should the PPID and STUDY be reset here??
$ppid = '';
$study = '';
$study_label = '';
$demog_label = '';
$record = '';
$appt_id = '';
$active_tab = false;

///given the record id, should I try to get value of PPID (study_pid_current) and STUDY (study_name_current)?
//no instead just come up with complete list of available ppid and put in a dropdown (see email)
$list = array('record_id', 'demo_first_name', 'demo_last_name','demo_middle_name','demo_dob',
    'study_name_current','study_pid_current','study_name1','study_pid1',
    'study_name2','study_pid2','study_name3','study_pid3','study_name4','study_pid4',
    'room_num','vis_mdneeded___1', 'vis_comments', 'vis_on_calendar');
$ppid_list = getPPID($registry_pid, $list, $registry_first_event);
$select = renderPPIDList($appt_pid, $ppid_list);

//if the fields are submitted then use those values for ppid and study
if(isset($_POST['lookup']) or isset($_GET['lookup'])) {
    // form submitted, now we can look at the data that came through
    // the value inside the brackets comes from the name attribute of the input field. (just like submit above)
    $record = $_POST['participant'];
    if (is_null($record) or empty($record)) {
        $record = $_GET['participant'];
        $appt_id = $_GET['apptid'];
    }
}

if (!empty($_POST['userData'])) {
    // Is this used?
    $userData = $_POST['userData'];

//    persistUserChanges($userData, $appt_pid);

    //TODO refresh the screen here
    //header("Location:  ".$_SERVER['PHP_SELF']);
    header('Location: '. $_SERVER['REQUEST_URI']);
    exit();
}

if (!empty($_POST['action']) and $_POST['action'] === "getAppointment") {
    // This is called when an appointment is being edited through the model.
    // Before displaying the modal, get the current state of the appointment
    // to initialize the modal.
    $record_id = $_POST['appt_record_id'];

    // If the record_id is null, create an entry in Redcap before continuing so there will be record_id
    if (is_null($record_id) or empty($record_id)) {
        $record_id = addAppointmentRecord($appt_pid, 'record_id', null);
    }

    // Retrieve raw form of data - not labels
    $fields = array('record_id', 'vis_ppid', 'vis_study', 'vis_room', 'vis_name',
                    'vis_date', 'vis_start_time', 'vis_end_time', 'vis_note', 'vis_status',
                    'vis_on_calendar');
    $data = Util::getData($appt_pid, $appt_event, $record_id, $fields, FALSE);
    $result = array_merge($data[0],
        array('result'=>'success',
                'message' => 'Found initial values')
    );

    // Return data for initialization
    header('Content-Type: application/json');
    SNP::log("return value: ", json_encode($result));
    print json_encode($result);
    exit();
}

if (!empty($_POST['action']) and $_POST['action'] === "copyAppointment") {
    $record_id = $_POST['appt_record_id'];
    $registry_record_id = $_POST['registry_record_id'];

    // Retrieve the appointment that we are cloning
    $fields_to_clone = array('vis_ppid', 'vis_study', 'vis_name', 'vis_category', 'vis_duration',
                             'vis_date', 'vis_start_time', 'vis_end_time', 'vis_room', 'vis_note',
                             'vis_body', 'vis_status', 'vis_on_calendar', 'vis_comments');
    $data = Util::getData($appt_pid, $appt_event, $record_id, $fields_to_clone, FALSE);
    $data[0]['vis_on_calendar'] = 0;
    $data[0]['last_update_made_by'] = USERID;
    $new_record_id = addAppointmentRecord($appt_pid, 'record_id', $data[0]);
    if (!is_null($new_record_id) and !empty($new_record_id)) {
        $return_msg = "Created new record number " . $new_record_id . " by copying record id " . $record_id;
        $return_status = 'success';
    } else {
        $return_msg = "Could not create new record by copying record id " . $record_id;
        $return_status = 'error';
    }
    SNP::log($return_msg);

    $result = array('result'    => $return_status,
                    'data'      => $data[0],
                    'message'   => $return_msg,
                    'url'       => $module->getUrl("pages/Scheduler.php") . "&lookup=1&participant=" . $registry_record_id . "&apptid=" . $new_record_id
    );

    // Return data for initialization
    header('Content-Type: application/json');
    SNP::log("return value: ", json_encode($result));
    print json_encode($result);
    exit();

}

/*
if (!empty($_POST['action']) and $_POST['action'] === "deleteAppointment") {

    // This is called when the Delete button is selected for a particular appointment.
    $deleteRecord = $_POST['deleteRecord'];
    SNP::log("Record to be Deleted: $deleteRecord");

    $appt = new Appt($appt_pid);
    $return = $appt->deleteCalendarEvent($deleteRecord, $appt_event);

    // Return the status of the delete
    $result = array('result'        => $return,
                    'record_id'     =>  $deleteRecord,
                    'display_off'   => 1,
                    'message'       => 'Deleted record');
    header('Content-Type: application/json');
    print json_encode($result);
    exit();
}
*/

if (!empty($_POST['action']) and $_POST['action'] === "saveAppointment") {
    // This is called when the Save button is selected on the modal so the
    // will be saved in Outlook and Redcap
    $record_id = $_POST['record_id'];
    $registry_record_id = $_POST['registry_record_id'];
    $fields = array('record_id', 'vis_ppid', 'vis_study', 'icaluid');
    $data = Util::getData($appt_pid, $appt_event, $record_id, $fields, FALSE);
    $change_record = array(
        "record_id"             => $record_id,
        "vis_ppid"              => $_POST['vis_ppid'],
        "vis_study"             => $_POST['vis_study'],
        "vis_name"              => $_POST['vis_name'],
        "vis_date"              => $_POST['vis_date'],
        "vis_start_time"        => $_POST['vis_start_time'],
        "vis_end_time"          => $_POST['vis_end_time'],
        "vis_room"              => $_POST['vis_room'],
        "vis_status"            => $_POST['vis_status'],
        "vis_note"              => $_POST['vis_note'],
        "vis_category"          => $_POST['vis_category'],
        "vis_on_calendar"       => $_POST['vis_on_calendar'],
        "url"                   => $module->getUrl("pages/Scheduler.php") . "&lookup=1&participant=" . $registry_record_id . "&apptid=" . $record_id,
        "last_update_made_by"   => USERID
    );

    $appt = new Appt($appt_pid);
    if ($change_record['vis_on_calendar'] == 1) {
        // The user wants to save or update this appointment
        $return = $appt->saveOrUpdateCalendarEvent($change_record);
    } else if (!is_null($data[0]['icaluid']) and !empty($data[0]['icaluid']) and ($change_record['vis_on_calendar'] == 0)) {
        // This appointment was stored on a calendar and the user wants to delete it
        $return = $appt->deleteCalendarEvent($record_id, $appt_event, USERID);
    } else if ((is_null($data[0]['icaluid']) or empty($data[0]['icaluid'])) and ($change_record['vis_on_calendar'] == 0)) {
        // This appointment was not stored on a calendar and the user wants to update the record
        $return = $appt->saveRedcapAppt(null, $change_record);
    }

    // Return the status of the Save
    $result = array('result'    => $return,
                    'data'      => $change_record,
                    'message'   => 'Saved or updated record ' . $record_id);

    header('Content-Type: application/json');
    print json_encode($result);
    exit();
}

//if $record is set then look up all the studies that this record_id ids participating in.
if ($record != null) {
    $study = isset($ppid_list[$record]['study_name_current']) ? $ppid_list[$record]['study_name_current'] : null;
    $ppid = isset($ppid_list[$record]['study_pid_current']) ? $ppid_list[$record]['study_pid_current'] : null;
    $study_name1 = isset($ppid_list[$record]['study_name1']) ? $ppid_list[$record]['study_name1'] : null;
    $study_pid1 = isset($ppid_list[$record]['study_pid1']) ? $ppid_list[$record]['study_pid1'] : null;
    $study_name2 = isset($ppid_list[$record]['study_name2']) ? $ppid_list[$record]['study_name2'] : null;
    $study_pid2 = isset($ppid_list[$record]['study_pid2']) ? $ppid_list[$record]['study_pid2'] : null;
    $study_name3 = isset($ppid_list[$record]['study_name3']) ? $ppid_list[$record]['study_name3'] : null;
    $study_pid3 = isset($ppid_list[$record]['study_pid3']) ? $ppid_list[$record]['study_pid3'] : null;
    $study_name4 = isset($ppid_list[$record]['study_name4']) ? $ppid_list[$record]['study_name4'] : null;
    $study_pid4 = isset($ppid_list[$record]['study_pid4']) ? $ppid_list[$record]['study_pid4'] : null;

    //friendly label for study
    $study_label = Util::getLabel($appt_pid, 'vis_study', $study);

    //Name and birthdate of participant
    $demog_label = getPPIDDemographic($registry_pid, $registry_first_event, $record);
}

$nav_tab_panel = ' <ul class="nav nav-tabs" role="tablist" id="studytabs">';
$tab_panel = '<div class="tab-content">';

//render table for study_name_current
if (($ppid != null) && ($study != null)) {
    $current_tab = "study-tab0";
    $table_id = "study-tab-0-dt";
    $current_study_label = Util::getLabel($appt_pid, 'vis_study', $study);

    $grid = getScheduleTable($table_id, $ppid, $study);

    //render the navigation tab
    if ((is_null($appt_id) or empty($appt_id)) or ($active_tab !== false)) {
        $tab_panel .= '<div role="tabpanel" class="tab-pane fade it active in" id="' . $current_tab . '">';
        $nav_tab_panel .= '<li role="presentation" class="active"><a data-toggle="tab" href="#' . $current_tab . '" aria-controls="' . $current_tab . '" role="tab">' . $ppid . " in<br> " . $current_study_label . '</a></li>';
        $active_tab = false;
    } else {
        $tab_panel .= '<div role="tabpanel" class="tab-pane fade" id="' . $current_tab . '">';
        $nav_tab_panel .= '<li role="presentation"><a data-toggle="tab" href="#' . $current_tab . '" aria-controls="' . $current_tab . '" role="tab">' . $ppid . " in<br> " . $current_study_label . '</a></li>';
    }
    $tab_panel .= $grid;
    $tab_panel .= '</div>';
}

//iterate over all the studies and see if they exist
for ($i = 1; $i < 5; $i++) {

    if ((${"study_name" . $i} != null) && (${"study_pid" . $i} != null)) {
        $current_study_label = Util::getLabel($appt_pid, 'vis_study', ${"study_name" . $i});
        $current_tab = "study-tab" . $i;
        $table_id = "study-tab-" . $i . "-dt";

        $grid = getScheduleTable($table_id, ${"study_pid" . $i}, ${"study_name" . $i});

        if ($active_tab === false) {
            $tab_panel .= '<div role="tabpanel" class="tab-pane fade it" id="' . $current_tab . '">';
            $nav_tab_panel .= '<li role="presentation"><a data-toggle="tab" href="#' . $current_tab . '" aria-controls="' . $current_tab . '" role="tab" data-toggle="tab">' . ${"study_pid" . $i} . " in<br>" . $current_study_label . '</a></li>';
        } else {
            $tab_panel .= '<div role="tabpanel" class="tab-pane fade it active in" id="' . $current_tab . '">';
            $nav_tab_panel .= '<li role="presentation" class="active"><a data-toggle="tab" href="#' . $current_tab . '" aria-controls="' . $current_tab . '" role="tab" data-toggle="tab">' . ${"study_pid" . $i} . " in<br>" . $current_study_label . '</a></li>';
            $active_tab = false;
        }
        $tab_panel .= $grid;
        $tab_panel .= '</div>';

    }
}

$tab_panel .= '</div>';
$nav_tab_panel .= '</ul>';



// Build table for the selected participant
function getScheduleTable($id, $ppid, $study) {
    global $appt_pid, $appt_event, $icon_urls, $appt_id, $active_tab;

    //get the APPOINTMENT from the appointment project
    $appts = getAppointments($appt_pid, $ppid, $study);

    // Figure out if the appointment we are interested in is on this tab so we can make it active
    if (!is_null($appt_id) and !empty($appt_id)) {
        $list_of_record_ids = array_column($appts, 'record_id');
        $active_tab = array_search($appt_id, $list_of_record_ids);
    }

    // Look at the template.txt file and see if this study has a schedule template
    $this_template = getTemplate($study);

    //add the additional columns to the template table (for ex: recommended dates and windows)
    $this_schedule = calcTemplateDates($appts, $this_template, $appt_pid, $ppid, $study);

    // Set the header
    $header = array("Appt ID", "Visit Name", "Visit Category", "Dur (hrs)", "Visit Date", "Visit Time", "Status", "Target Date", "Up Window", "Down Window");

    // display this table with the projected appointment ranges
    $grid = Util::renderCalTable($id, $header, $this_schedule, $appt_pid, $appt_event, $icon_urls, $appt_id);

    $grid .= '<button type="button" class="btn btn-med btn-primary action no-print" data-action="edit-appointment" data-record="">Create New Appt</button>';
    return $grid;
}


/**
 *
 * @param array $this_template
 * @param unknown $pid : pid of project where appointments are to be added
 * @return NULL|string[]|unknown[]
 */
function calcTemplateDates($appts, $this_template, $pid, $vis_ppid, $vis_study) {
    global $appt_pid;

    //get the dropdown list for status
    $vis_status_choices  =  Util::getDictChoices($appt_pid, 'vis_status');
    $vis_category_choices  =  Util::getDictChoices($appt_pid, 'vis_category');
    $schedule = array();

    // If the template is null, just display the current appointments since we don't know what the schedule should be
    if (is_null($this_template) or empty($this_template)) {
        foreach ($appts as $key => $value) {
            $visit_name = $value['record_id'];
            $schedule[$visit_name]['record_id'] = $visit_name;
            $schedule[$visit_name]['vis_name'] = $value['vis_name'];
            $schedule[$visit_name]['vis_category'] = $vis_category_choices[$value['vis_category']];
            $schedule[$visit_name]['vis_dur'] = $value['vis_duration'];
            $schedule[$visit_name]['vis_date'] = $value['vis_date'];
            $schedule[$visit_name]['vis_start_time'] = $value['vis_start_time'];
            $schedule[$visit_name]['vis_status'] = $vis_status_choices[$value['vis_status']];
            $schedule[$visit_name]['vis_date_proj'] = $value['vis_date_proj'];
            $schedule[$visit_name]['vis_minwin'] = $value['vis_minwin'];
            $schedule[$visit_name]['vis_maxwin'] = $value['vis_maxwin'];
            $schedule[$visit_name]['vis_note'] = $value['vis_note'];
            $schedule[$visit_name]['vis_on_calendar'] = $value['vis_on_calendar'];
        }
    } else {

        // Iterate over the appts and see which ones we already have
        $visit_names = array();
        foreach ($appts as $key => $value) {
            $visit_names[$key] = $value['vis_name'];
        }

        //Iterate over the TEMPLATE and see which records are already created and create the ones we don't have.
        $calc_projected = false;
        $record_id_list = array();
        foreach ($this_template  as $key => $value) {

            // This event is in the template already so retrieve the data for this event
            if (in_array($key, $visit_names)) {
                // Find the key in the appts record that holds this event
                $appt_key = array_search($key, $visit_names);
                $schedule[$key]['record_id'] = $appts[$appt_key]['record_id'];

                $schedule[$key]['vis_name'] = $appts[$appt_key]['vis_name'];
                $schedule[$key]['vis_category'] = $vis_category_choices[$appts[$appt_key]['vis_category']];

                $schedule[$key]['vis_dur'] = $appts[$appt_key]['vis_duration'];
                $schedule[$key]['vis_date'] = $appts[$appt_key]['vis_date'];
                $schedule[$key]['vis_start_time'] = $appts[$appt_key]['vis_start_time'];
                $schedule[$key]['vis_status'] = $vis_status_choices[$appts[$appt_key]['vis_status']];

                $schedule[$key]['vis_date_proj'] = $appts[$appt_key]['vis_date_proj'];
                $schedule[$key]['vis_minwin'] = $appts[$appt_key]['vis_minwin'];
                $schedule[$key]['vis_maxwin'] = $appts[$appt_key]['vis_maxwin'];
                $schedule[$key]['vis_note'] = $appts[$appt_key]['vis_note'];
                $schedule[$key]['vis_on_calendar'] = $appts[$appt_key]['vis_on_calendar'];

            } else {

                // Set the visit status to projected
                $cat_key = array_search($value['visit_category'], $vis_category_choices);

                //Appointment Redcap record is created but projected info is not saved
                $data = array(  'vis_ppid' => $vis_ppid,
                                'vis_study' => $vis_study,
                                'vis_name' => $key,
                                'vis_category' => $cat_key,
                                'vis_duration' => $value['vis_duration'],
                                'vis_status' => null,
                                'vis_on_calendar' => 0
                            );
                $nextId = addAppointmentRecord($appt_pid, 'record_id', $data);

                //populate with nextID and null dates and select
                $schedule[$key]['record_id'] = $nextId;
                $schedule[$key]['vis_name'] = $key;
                $schedule[$key]['vis_category'] = $vis_category_choices[$cat_key];
                $schedule[$key]['vis_dur'] = $value['vis_duration'];
                $schedule[$key]['vis_date'] = null;
                $schedule[$key]['vis_start_time'] = null;
                // Set the status to projected
                $schedule[$key]['vis_status'] = null;
                $schedule[$key]['vis_note'] = $value['vis_note'];
                $schedule[$key]['vis_on_calendar'] = 0;
            }

            // Only calculate the projected date if a scheduled data exists for the first appointment
            $offset = $value['offset'];
            array_push($record_id_list, $schedule[$key]['record_id']);

            // Get the date of the appointment we are calculating the offset from.
            if (($offset  == '-') and !is_null($schedule[$key]['vis_start_time']) and !empty($schedule[$key]['vis_start_time'])) {
                $calc_projected = true;
            }

            if (($calc_projected === true) and ($offset != '-')) {
                $days_from_offset = $value['days_from_offset'];
                $lower_window = (is_numeric($value['lower_window'])) ? $value['lower_window'] : 0;
                $upper_window = (is_numeric($value['upper_window'])) ? $value['upper_window'] : 0;
                $todays_date = date('Y-m-d');

                $vis_status = array_search('Projected', $vis_status_choices);
                $schedule[$key]['vis_status'] = $vis_status_choices[$vis_status];

                // Find the date that we are offseting from.  If a scheduled date does not exist, use the
                // projected date or if that doesn't exist, use today's date
                $scheduled_visit_date = $schedule[$offset]['vis_date'];

                if (is_null($scheduled_visit_date) || empty($scheduled_visit_date)) {
                    $scheduled_visit_date = (is_null($schedule[$offset]['vis_date_proj']) ? $todays_date: $schedule[$offset]['vis_date_proj']);
                }
                $target_date = Util::addDaysToDate($scheduled_visit_date, $days_from_offset);

                $schedule[$key]['vis_date_proj'] = $target_date;
                $schedule[$key]['vis_minwin'] = Util::addDaysToDate($target_date, -($lower_window));
                $schedule[$key]['vis_maxwin'] = Util::addDaysToDate($target_date, $upper_window);
            } else {
                $schedule[$key]['vis_date_proj'] = null;
                $schedule[$key]['vis_minwin'] = null;
                $schedule[$key]['vis_maxwin'] = null;
            }
        }

        // Check to see if there are extra appointments already scheduled that are not in the template and add them to the list
        foreach ($appts as $key => $value) {
            if (array_search($value['record_id'], $record_id_list) === false) {
                $visit_name = $value['record_id'];
                $schedule[$visit_name]['record_id'] = $visit_name;
                $schedule[$visit_name]['vis_name'] = $value['vis_name'];
                $schedule[$visit_name]['vis_category'] = $vis_category_choices[$value['vis_category']];
                $schedule[$visit_name]['vis_dur'] = $value['vis_duration'];
                $schedule[$visit_name]['vis_date'] = $value['vis_date'];
                $schedule[$visit_name]['vis_start_time'] = $value['vis_start_time'];
                $schedule[$visit_name]['vis_status'] = $vis_status_choices[$value['vis_status']];
                $schedule[$visit_name]['vis_date_proj'] = $value['vis_date_proj'];
                $schedule[$visit_name]['vis_minwin'] = $value['vis_minwin'];
                $schedule[$visit_name]['vis_maxwin'] = $value['vis_maxwin'];
                $schedule[$visit_name]['vis_note'] = $value['vis_note'];
                $schedule[$visit_name]['vis_on_calendar'] = $value['vis_on_calendar'];
            }
        }
    }

    return $schedule;
}


/**
 *
 * @param int $pid
 * @param string $id_field
 * @param string $visit_name
 * @param array $template_fields
 * @return int
 */
//function addAppointmentRecord($pid, $id_field, $vis_ppid,$vis_study, $visit_name, $cat_key, $template_fields, $vis_status) {
function addAppointmentRecord($pid, $id_field, $data) {
    global $appt_event;

    $nextID = Util::getNextId($pid,$id_field, $appt_event, null);

    $data['record_id'] = $nextID;

    $results = REDCap::saveData($pid,'json', json_encode(array($data)), 'overwrite', 'YMD');
    if (! empty($results['errors'])) {
        $msg = "Error creating record - ask administrator to review logs: " . json_encode($results);
        SNP::error($msg);
        return null;
    }
    return $nextID;
}


/**
 * Get display label of participant name and DOB
 * @param unknown $pid
 * @param unknown $event
 * @param unknown $record
 * @return string
 */
function getPPIDDemographic($pid, $event = null, $record) {

    $target = array('demo_first_name', 'demo_last_name','demo_dob');
    $q = REDCap::getData($pid, 'json', $record, $target, $event);
    $results = json_decode($q, true);

    $lname = $results[0]['demo_last_name'];
    $fname = $results[0]['demo_first_name'];
    $dob = $results[0]['demo_dob'];
    $label = '<div>' . $fname. " " . $lname . " (DOB: ". $dob .")";

    // Make a button so the scheduler can go directly to the registry record for this person
    //$button = '<button style="margin-top: -4px; margin-left: 10px" class="btn btn-med btn-primary no-print">';
    //$button = '<button margin-left: 18px" class="btn btn-lg btn-primary no-print">';
    $button = '<button class="btn btn-med btn-primary no-print">';
    $button .= '<a target="_blank" style="text-decoration: none; color: white" href="' . substr(APP_PATH_WEBROOT_FULL, 0,  -1) . APP_PATH_WEBROOT . 'DataEntry/record_home.php?pid=' . $pid . '&arm=1&id=' . $record . '">Go to registry</a>';
    $button .= '</button></div>';
    $button .= '<div style="display: none;">';
    $button .= '<input type="text" id="registry_record_id" name="registry_record_id" value="' . $record . '">';
    $button .= '</div>';
    return $label . "&nbsp;&nbsp;" . $button;
}

/**
 * Get list of 'study_pid_current' from the Registry project
 * return array with record_id moved to key (or should it be study_pid_current)?
 * @return array
 */
function getPPID($pid, $target, $event = null) {

    $q = REDCap::getData($pid,'json', null, $target, $event);
    $results = json_decode($q, true);

    $ppid_list = array();
    foreach ($results as $value) {
        $ppid_list[$value['record_id']] = $value;
    }

    return $ppid_list;
}

/**
 * Input is
 * array('record_id', 'demo_first_name', 'demo_last_name','demo_middle_name','demo_dob',
'study_name_current','study_pid_current','study_name1','study_pid1',
'study_name2','study_pid2','study_name3','study_pid3','study_name4','study_pid4');
 * return html select tag
 * @param array $ppid_list
 */
function renderPPIDList($pid, $ppid_list) {
    /**
     * RECORD SELECTION DROP-DOWN
     */
    $select =  "<input class='input-lg' name='participant' list='participant' class='x-form-text x-form-field' style='max-width:500px;'>";
    $select .=  "<datalist id='participant'>";

    // sort first by last name
    // usort($ppid_list, function($a, $b) {
    // return $a['demo_last_name'] - $b['demo_last_name'];
    // });
    //usort($ppid_list, 'sortByOrder');
    $tmp = Array();
    foreach($ppid_list as &$ma) {
        $tmp[] = &$ma["demo_last_name"];
    }
    array_multisort($tmp, $ppid_list);

    foreach ($ppid_list as $this_record => $value)
    {
        // Check for custom labels
        $rec_id = $value['record_id'];
        $last_name = $value['demo_last_name'];
        $first_name = $value['demo_first_name'];
        $dob = $value['demo_dob'];
        $study_name_current = $value['study_name_current'];
        $study_pid_current = $value['study_pid_current'];
        $study_label = Util::getLabel($pid, 'vis_study', $study_name_current);

        //Render drop-down options
        $select .= "<option value='{$rec_id}'>{$last_name}, {$first_name} |ID: {$rec_id} |DOB: {$dob} | {$study_label} - {$study_pid_current}</option>";
    }
    $select .= "</datalist>";

    return $select;
}

/**
 * Get the Appointments given the participant_id and the study name
 *
 * @param unknown $project_id
 * @param unknown $vis_ppid
 * @param unknown $vis_study
 * @return unknown
 */
function getAppointments($project_id, $vis_ppid, $vis_study) {
    global $appt_event;

    $pid = intval($project_id);
    $filter1  = "[vis_ppid] = '{$vis_ppid}'";
    $filter_2 = "[vis_study] = '{$vis_study}'";
    $q = REDCap::getData($pid, 'json', null, null, $appt_event, null, false, false, false, $filter1 . " and " . $filter_2);

    $results = json_decode($q, true);

    return $results;

}

/**
 * Get the tamplate from the template.txt (from Dana Tupa's excel file)
 * @param int $vis_study
 * @return array
 */

function getTemplate($vis_study) {
    global $appt_pid, $template_path;

    $json_text = file_get_contents($template_path);
    if ($json_text === false) {
        SNP::error("Could not read template file: " . $template_path);
    }

    $template = json_decode($json_text, true);

    //decode the $vis_study to text so that we can match it to the template
    $vis_study_choices  =  Util::getDictChoices($appt_pid, 'vis_study');
    $this_study = $vis_study_choices[$vis_study];
    $this_template = $template[$this_study];

    return $this_template;
}


function getDictionaryOptions($fieldname) {
    global $appt_pid;

    $choices = Util::getDictChoices($appt_pid, $fieldname);
    $html_string = '<select class="form-control" id="' . $fieldname . '" name="' . $fieldname . '">';

    foreach ($choices as $value => $key) {
        $html_string .= '<option value="' . trim($value) . '">' . trim($key) . '</option>';
    }

    $html_string .= '</select>';

    return $html_string;
}

?>
<html>
<head>
    <title>Scheduler</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>

    <!-- Bootstrap core CSS -->
<!--
    <link href='https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css' rel='stylesheet' media='screen'>
-->
    <!--  from https://datatables.net/download/  -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/bs-3.3.7/jqc-1.12.4/jszip-3.1.3/pdfmake-0.1.27/dt-1.10.15/b-1.3.1/b-colvis-1.3.1/b-flash-1.3.1/b-html5-1.3.1/b-print-1.3.1/datatables.min.css"/>
    <link rel="stylesheet" type="text/css" href="<?php echo $module->getUrl("pages/scheduler.css") ?>" />
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css" media="screen">
    <!-- HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
    <script src='https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js'></script>
    <script src='https://oss.maxcdn.com/respond/1.4.2/respond.min.js'></script>
    <![endif]-->
    <link rel="icon" type="image/png" href="https://med.stanford.edu/etc/clientlibs/sm/images/favicon.ico">
</head>
<body>
<style type="text/css">
    #template {
        margin: 0px 20px;
    }
</style>

<div class="topnav no-print">
    <table width="100%">
        <tr>
            <th style="colspan=2">
                <h2 style="color: white">Scheduler</h2>
            </th>
            <th>
            </th>
        </tr>
        <tr>
            <td>
                <div class="left">
                    <h4>Select a participant:</h4>
                    <form action="" method="post">
                        <?php echo $select?>
                        <button class="btn btn-med btn-primary" name="lookup" onclick='window.location.reload(true);'>Look up studies</button>
                    </form>
                </div>
            </td>
            <td>
                <div class="right">
                    <h3><?php echo $demog_label?></h3>
                </div>
            </td>
        </tr>
    </table>
</div>
<br>
<div class="container">
    <?php
    if (isset($ppid) && !empty($ppid)) {
    ?>
    <?php  print $nav_tab_panel ?>
    <br>
    <?php  print $tab_panel ?>
    <?php
    }
    ?>

    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src='https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js' type="text/javascript"></script>
    <!-- Include all compiled plugins (below), or include individual files as needed -->
    <script src='https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js' type="text/javascript"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/v/bs-3.3.7/jqc-1.12.4/jszip-3.1.3/pdfmake-0.1.27/dt-1.10.15/b-1.3.1/b-colvis-1.3.1/b-flash-1.3.1/b-html5-1.3.1/b-print-1.3.1/datatables.min.js"></script>

    <!-- Modal to Edit/Delete Appointment -->
    <div class="modal fade" id="apptModal" tabindex="-1" role="dialog" aria-labelledby="apptModal" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <!-- Modal Header and start of form-->
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">
                        <span aria-hidden="true">&times;</span>
                        <span class="sr-only">Close</span>
                    </button>
                    <h4 class="modal-title">
                        Edit Appointment
                    </h4>
                </div>

                <div class="modal-body">
                    <div class="panel panel-primary">
                        <div class="panel-heading"><strong>Appointment</strong></div>
                        <div class="panel-body">
                            <div class="input-group">
                                <div style="visibility: hidden; display: inline;">
                                    <input type="text" class="form-control" id="appt_record_id" name="appt_record_id">
                                </div>
                                <div class="form-group">
                                    <label for="vis_ppid" class="col-sm-4 control-label">Participant:</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="vis_ppid" name="vis_ppid" placeholder="Participant:">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="vis_study" class="col-sm-4 control-label">Study:</label>
                                    <div class="col-sm-8">
                                        <?php echo getDictionaryOptions('vis_study') ?>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="vis_name" class="col-sm-4 control-label">Visit Name:</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="vis_name" name="vis_name" placeholder="Visit Name:">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="vis_category" class="col-sm-4 control-label">Visit Category:</label>
                                    <div class="col-sm-8">
                                        <?php echo getDictionaryOptions('vis_category') ?>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="vis_date" class="col-sm-4 control-label">Visit Date:</label>
                                    <div class="col-sm-8">
                                        <input type="date" class="form-control" id="vis_date" name="vis_date">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="vis_start_time" class="col-sm-4 control-label">Visit Start Time:</label>
                                    <div class="col-sm-8">
                                        <input type="time" class="form-control" id="vis_start_time" name="vis_start_time">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="vis_end_time" class="col-sm-4 control-label">Visit End Time:</label>
                                    <div class="col-sm-8">
                                        <input type="time" class="form-control" id="vis_end_time" name="vis_end_time">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="vis_room" class="col-sm-4 control-label">Visit Room</label>
                                    <div class="col-sm-8">
                                        <?php echo getDictionaryOptions('vis_room') ?>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="vis_status" class="col-sm-4 control-label">Visit Status</label>
                                    <div class="col-sm-8">
                                        <?php echo getDictionaryOptions('vis_status') ?>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="vis_note" class="col-sm-4 control-label">Pre-Visit Note</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="vis_note" name="vis_note" placeholder="Pre-Visit Note">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="vis_on_calendar" class="col-sm-4 control-label">Display on Calendar</label>
                                    <div class="col-sm-8">
                                        <?php echo getDictionaryOptions('vis_on_calendar') ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="record"/>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary action" data-action="save-appointment" >Save</button>
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript" src="<?php echo $module->getUrl("js/Scheduler.js") ?>"></script>

</body>
</html>