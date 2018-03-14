<?php
namespace Stanford\SNP;
/** @var \Stanford\SNP\SNP $module */

use \REDCap;


// Retrieve Registry project info
$registry_pid = $module->getProjectSetting('registry_pid');
$registry_first_event = $module->getProjectSetting('registry_first_event');

// Retrieve Appointment project info
$appt_pid = PROJECT_ID;
$appt_event = $module->getProjectSetting('appt_event_id');
//$record = isset($_GET['record']) && !empty($_GET['record']) ? $_GET['record'] : null;


// Retrieve pid from calling project
$pid = isset($_GET['pid']) && !empty($_GET['pid']) ? $_GET['pid'] : null;

//Limit to project, as way of restricting users...
if (($pid <> $registry_pid) && (($pid <> $appt_pid))) {
    $msg =  "<b>ERROR:</b>$pid : This can only be run in the context the Allergy project (pid 10062).";
    echo "<p class='red' style='margin:20px 0 10px; text-align:center;'><b>$msg</b></p>";
    exit;
}


//Should the PPID and STUDY be reset here??
$ppid = '';
$study = '';
$study_label = '';
$demog_label = '';

///given the record id, should I try to get value of PPID (study_pid_current) and STUDY (study_name_current)?
//no instead just come up with complete list of available ppid and put in a dropdown (see email)
$list = array('record_id', 'demo_first_name', 'demo_last_name','demo_middle_name','demo_dob',
    'study_name_current','study_pid_current','study_name1','study_pid1',
    'study_name2','study_pid2','study_name3','study_pid3','study_name4','study_pid4',
    'room_num','vis_mdneeded___1', 'vis_comments');
$ppid_list = getPPID($registry_pid, $list, $registry_first_event);
$select = renderPPIDList($appt_pid, $ppid_list);


//if the fields are submitted then use those values for ppid and study
if(isset($_POST['lookup'])) {
    // form submitted, now we can look at the data that came through
    // the value inside the brackets comes from the name attribute of the input field. (just like submit above)
    $record = $_POST['participant'];
}


if (!empty($_POST['userData'])) {
    // Is this used?
    $userData = $_POST['userData'];
    echo "In userData: post: " . $userData . "<br>";

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
    $fields = array('record_id', 'vis_ppid', 'vis_study', 'vis_room', 'vis_name',
                    'vis_date', 'vis_start_time', 'vis_end_time', 'vis_note', 'vis_status');

    // If the record_id is null, create an entry in Redcap before continuing so there will be record_id
    if (is_null($record_id) or empty($record_id)) {
        $record_id = addAppointmentRecord($appt_pid, 'record_id', null, null,
            null, null, null, null);
    }

    // Retrieve raw form of data - not labels
    $data = Util::getData($appt_pid, $appt_event, $record_id, $fields, FALSE);
    $result = array_merge($data[0],
        array('result'=>'success',
                'message' => 'Found initial values')
    );

    // Return data for initialization
    header('Content-Type: application/json');
    print json_encode($result);
    exit();
}


if (!empty($_POST['action']) and $_POST['action'] === "deleteAppointment") {

    // This is called when the Delete button is selected for a particular appointment.
    $deleteRecord = $_POST['deleteRecord'];
    SNP::log("Record to be Deleted: $deleteRecord");

    $appt = new Appt($pid);
    $return = $appt->deleteCalendarEvent($deleteRecord, $appt_event);

    // Return the status of the delete
    $result = array('result'    => $return,
                    'message'   => 'Deleted record');

    header('Content-Type: application/json');
    print json_encode($result);

    // Refresh page
    //header('Location: '. $_SERVER['REQUEST_URI']);
    exit();
}


if (!empty($_POST['action']) and $_POST['action'] === "saveAppointment") {
    // This is called when the Save button is selected on the modal so the
    // will be saved in Outlook and Redcap
    $record = array(
        "record_id"             => $_POST['record_id'],
        "vis_ppid"              => $_POST['vis_ppid'],
        "vis_study"             => $_POST['vis_study'],
        "vis_name"              => $_POST['vis_name'],
        "vis_date"              => $_POST['vis_date'],
        "vis_start_time"        => $_POST['vis_start_time'],
        "vis_end_time"          => $_POST['vis_end_time'],
        "vis_room"              => $_POST['vis_room'],
        "vis_status"            => $_POST['vis_status'],
        "vis_note"              => $_POST['vis_note'],
        "last_update_made_by"   => $_POST['last_update_made_by']
    );

    $appt = new Appt($pid);
    $return = $appt->saveOrUpdateCalendarEvent($record);
    SNP::log("Record to be Saved: " . $record['record_id'] . ", return from save: " . $return);

    // Return the status of the Save
    $result = array('result'    => $return,
                    'message'   => 'Saved record');

    header('Content-Type: application/json');
    print json_encode($result);

    // Refresh page
    // header('Location: '. $_SERVER['REQUEST_URI']);
    // header("Refresh:0");
    exit();
}

//if $record is set then look up all the studies that this record_id is participating in.
if ($record != null) {
    $study = isset($ppid_list[$record]['study_name_current']) ? $ppid_list[$record]['study_name_current']: null;
    $ppid = isset($ppid_list[$record]['study_pid_current']) ? $ppid_list[$record]['study_pid_current']: null;
    $study_name1 = isset($ppid_list[$record]['study_name1']) ? $ppid_list[$record]['study_name1']: null;
    $study_pid1 = isset($ppid_list[$record]['study_pid1']) ? $ppid_list[$record]['study_pid1']: null;
    $study_name2 = isset($ppid_list[$record]['study_name2']) ? $ppid_list[$record]['study_name2']: null;
    $study_pid2 = isset($ppid_list[$record]['study_pid2']) ? $ppid_list[$record]['study_pid2']: null;
    $study_name3 = isset($ppid_list[$record]['study_name3']) ? $ppid_list[$record]['study_name3']: null;
    $study_pid3 = isset($ppid_list[$record]['study_pid3']) ? $ppid_list[$record]['study_pid3']: null;
    $study_name4 = isset($ppid_list[$record]['study_name4']) ? $ppid_list[$record]['study_name4']: null;
    $study_pid4 = isset($ppid_list[$record]['study_pid4']) ? $ppid_list[$record]['study_pid4']: null;

    //friendly label for study
    $study_label = Util::getLabel($appt_pid, 'vis_study', $study);

    //Name and birthdate of participant
    $demog_label = getPPIDDemographic($registry_pid, $registry_first_event, $record);

}


$nav_tab_panel = ' <ul class="nav nav-tabs" role="tablist">';
$tab_panel = '<div class="tab-content">';

//render table for study_name_current
if (($ppid != null) && ($study != null)) {

    $current_tab = "study-tab0";
    $table_id = "study-tab-0-dt";
    $current_study_label = Util::getLabel($appt_pid, 'vis_study', $study);

    $tab_panel .= '<div role="tabpanel" class="tab-pane fade it active" id="'.$current_tab.'">';
    $grid = getScheduleTable($table_id,$ppid, $study);
    $tab_panel .= $grid;
    $tab_panel .= '</div>';
    //render the navigation tab
    $nav_tab_panel .= '<li role="presentation" class="active"><a href="#'.$current_tab.'" aria-controls="'.$current_tab.'" role="tab" data-toggle="tab">'.$ppid ." in<br> " .$current_study_label.'</a></li>';
}

//iterate over all the studies and see if they exist
for ($i = 1; $i < 5; $i++) {

    if ((${"study_name".$i} != null) && (${"study_pid".$i} != null)) {
        $current_study_label = Util::getLabel($appt_pid, 'vis_study', ${"study_name".$i});
        $current_tab = "study-tab".$i;

        $table_id = "study-tab-".$i."-dt";
        $tab_panel .= '<div role="tabpanel" class="tab-pane fade" id="'.$current_tab.'">';
        $grid = getScheduleTable($table_id, ${"study_pid".$i}, ${"study_name".$i});
        $tab_panel .= $grid;
        $tab_panel .= '</div>';
        $nav_tab_panel .= '<li role="presentation"><a href="#'.$current_tab.'" aria-controls="'.$current_tab.'" role="tab" data-toggle="tab">'.${"study_pid".$i} ." in<br>" .$current_study_label.'</a></li>';

    }
}

$tab_panel .= '</div>';
$nav_tab_panel .= '</ul>';

// Build table for the selected participant
function getScheduleTable($id, $ppid, $study) {
    global $appt_pid, $appt_event;

    //get the APPOINTMENT from the appointment project
    $appts = getAppointments($appt_pid, $ppid, $study);

    // Look at the template.txt file and see if this study has a schedule template
    $this_template = getTemplate($study);

    //add the additional columns to the template table (for ex: recommended dates and windows)
    $this_schedule = calcTemplateDates($appts, $this_template, $appt_pid, $ppid, $study);

    // Set the header
    $header = array("Appt ID", "Visit Name", "Visit Category", "Dur (hrs)", "Visit Date", "Visit Time", "Status", "Recommended Date", "Up Window", "Down Window");

    // display this table with the projected appointment ranges
    $grid = Util::renderTable($id, $header, $this_schedule, $appt_pid, $appt_event, true);

    $grid .= '<button type="button" class="btn btn-med btn-primary action" data-action="edit-appointment" data-record="">Create New Appt</button>';
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
            $schedule[$visit_name]['vis_category'] = $value['vis_category'];
            $schedule[$visit_name]['vis_dur'] = $value['vis_duration'];
            $schedule[$visit_name]['vis_date'] = $value['vis_date'];
            $schedule[$visit_name]['vis_start_time'] = $value['vis_start_time'];
            $schedule[$visit_name]['vis_status'] = $value['vis_status'];
            $schedule[$visit_name]['vis_date_proj'] = $value['vis_date_proj'];
            $schedule[$visit_name]['vis_minwin'] = $value['vis_minwin'];
            $schedule[$visit_name]['vis_maxwin'] = $value['vis_maxwin'];
        }
    } else {

        // Iterate over the appts and see which ones we already have
        $visit_names = array();
        foreach ($appts as $key => $value) {
            $visit_names[$key] = $value['vis_name'];
        }

        //Iterate over the TEMPLATE and see which records are already created and create the ones we don't have.
        foreach ($this_template  as $key => $value) {

            // This event in the template already exists in the calendar so retrieve the data for this event
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

            } else {

                // Set the visit status to projected
                $vis_status = array_search('Projected', $vis_status_choices);
                SNP::log("This is the projected visit status: " . $vis_status);
                $cat_key = array_search($value['visit_category'], $vis_category_choices);

                //Appointment Redcap record is created but projected info is not saved
                $nextId = addAppointmentRecord($appt_pid, 'record_id', $vis_ppid, $vis_study, $key, $cat_key, $value, $vis_status);

                //populate with nextID and null dates and select
                $schedule[$key]['record_id'] = $nextId;
                $schedule[$key]['vis_name'] = $key;
                $schedule[$key]['vis_category'] = $vis_category_choices[$cat_key];
                $schedule[$key]['vis_dur'] = $value['vis_duration'];
                $schedule[$key]['vis_date'] = null;
                $schedule[$key]['vis_start_time'] = null;
                // Set the status to projected
                $schedule[$key]['vis_status'] = $vis_status_choices[$vis_status];
            }

            // Calculate the projected date - even if there is a scheduled date so they scheduler knows
            // if they are in the correct range.
            $offset = $value['offset'];
            $days_from_offset = $value['days_from_offset'];
            $lower_window = (is_numeric($value['lower_window'])) ? $value['lower_window'] : 0;
            $upper_window = (is_numeric($value['upper_window'])) ? $value['upper_window'] : 0;
            $todays_date = date('Y-m-d');

            // Get the date of the appointment we are calculating the offset from.
            if ($offset  == '-') {
                // This appointment is not offset from anything so set the projected date to today
                $schedule[$key]['vis_date_proj'] = $todays_date;
                $schedule[$key]['vis_minwin'] = 0;
                $schedule[$key]['vis_maxwin'] = 0;
            } else {
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
function addAppointmentRecord($pid, $id_field, $vis_ppid,$vis_study, $visit_name, $cat_key, $template_fields, $vis_status) {
    global $appt_event;

    $nextID = Util::getNextId($pid,$id_field, $appt_event, null);
    $data = array(
        'record_id' => $nextID,
        'vis_ppid' => $vis_ppid,
        'vis_study' => $vis_study,
        'vis_name' => $visit_name,
        'vis_category' => $cat_key,
        'vis_duration' => $template_fields['duration'],
        'vis_status'    => $vis_status
    );
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
    return $fname. " " . $lname . " (DOB: ". $dob .")";
}

/**
 * Get list of 'study_pid_current' from the Registry project
 * return array with record_id moved to key (or should it be study_pid_current)?
 * @return array
 */
function getPPID($pid, $target, $event = null) {

//    echo "In getPPID: pid: " . $pid . ", target: " . $target . ", event: " . $event . "  <br>";
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
//    $select =  "<select class='input-lg' name='participant' id='participant' class='x-form-text x-form-field' style='max-width:350px;'>";
    $select =  "<input class='input-lg'  list='participant' class='x-form-text x-form-field' style='max-width:350px;'>";
    $select .=  "<datalist id='participant'>";
    $select .= "<option selected disabled>Select Participant...</option>";

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
        // Just for testing
        if (($value['record_id'] == '9999') or ($value['record_id'] == '10085')) {
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
    }
//    $select .= "</select>";
    $select .= "</datalist>";

    return $select;
}

/*
function sortByOrder($a, $b) {
    return $a['demo_last_name'] - $b['demo_last_name'];
}
*/

/**
 * Get the Appointments given the participant_id and the study name
 *
 * @param unknown $project_id
 * @param unknown $vis_ppid
 * @param unknown $vis_study
 * @return unknown
 */
function getAppointments($project_id, $vis_ppid, $vis_study) {
    global $appt_pid;

    $pid = intval($project_id);
    $filter1  = "[vis_ppid] = '{$vis_ppid}'";
    $filter_2 = "[vis_study] = '{$vis_study}'";
    $q = REDCap::getData($pid, 'json', null, null, $appt_pid, null, false, false, false, $filter1 . " and " . $filter_2);

    $results = json_decode($q, true);

    return $results;

}

/**
 * Get the tamplate from the template.txt (from Dana Tupa's excel file)
 * @param int $vis_study
 * @return array
 */

function getTemplate($vis_study) {
    global $appt_pid, $module;

    $path = $module->getModulePath();
    $file = 'data/template.txt';

    $json_text = file_get_contents($path . $file);
    if ($json_text === false) {
        SNP::error("Could not read template file: " . $path . $file);
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

    $options = Util::getDictChoices($appt_pid, $fieldname);

    $html_string = '<select class="form-control" id="' . $fieldname . '" name="' . $fieldname . '">';
    foreach ($options as $value => $key) {
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

<div class="container">
    <h1>Scheduler</h1>
    <br><br>
    <form action="" method="post">

        <input list="test">
        <datalist id="test">
            <option value="1234">1234</option>
            <option value="2345">2345</option>
            <option value="3456">3456</option>
            <option value="4567">4567</option>
            <option value="5678">5678</option>
        </datalist>



        <?php echo $select?>
        <button style="margin-top: -4px;" class="btn btn-lg btn-primary" name="lookup" onclick='window.location.reload(true);'>Look up participants</button>
    </form>

    <?php
    if (isset($ppid) && !empty($ppid)) {
    ?>
        <div class="page-header">
            <h4> <?php echo $demog_label?></h4>
        </div>

        <?php  print $nav_tab_panel ?>
        <br>
        <?php  print $tab_panel   ?>
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
                <!-- Modal Header -->
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
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="record"/>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary action" data-action="save-appointment">Save</button>
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript" src="<?php echo $module->getUrl("js/Scheduler.js") ?>"></script>

</body>
</html>