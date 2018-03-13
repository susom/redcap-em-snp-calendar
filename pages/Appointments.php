<?php
namespace Stanford\SNP;
/** @var \Stanford\SNP\SNP $module */

use \REDCap;

// Include required files
require_once (dirname(__DIR__) . "/classes/Util.php");

$module->log("Starting Appointment Report");
$event_name = $module->getProjectSetting('appt_event_id');
$pid = 10062;

////////////// DONT EDIT BELOW HERE //////////////
$fields = array('record_id', 'vis_ppid', 'vis_study', 'vis_name', 'vis_category',
                'vis_date', 'vis_start_time', 'vis_end_time', 'vis_room', 'vis_status', 'vis_note');


// check if variable is set and Add Customer Button pressed.
if(isset($_POST["submit"])=="Add Customer") {
    //Plugin::log("this is POST ".print_r($_POST, true), "DEBUG");

    //check if the array is empty
    $empty = true;
    //iterate over fields array and save Data to Appointments project
    $data = array();
    foreach ($fields as $item) {
        if (empty($_POST[$item]) ) {
            continue;
        }
        $empty = false;
        $data[$item] = $_POST[$item];
    }

    if (!$empty) {
        $next_id = Util::getNextId($pid, 'record_id', $event_name);
        Plugin::log("next id is $next_id");

        $data['record_id']=$next_id;

        $result = REDCap::saveData($pid, 'json', json_encode(array($data)), 'overwrite');
        Plugin::log("Creating new record $next_id in main project: " . print_r($result,true), "DEBUG");
    }


}


//display appointment table
try {
    Util::displayAppointments($pid, $fields, $event_name);
} catch (Exception $e) {
    echo $e;
    exit;
}
######## HTML PAGE ###########

?>

<br><br>
<!-- Button trigger modal -->
<button class="btn btn-primary btn-lg" data-toggle="modal" data-target="#myModalNorm">
    Add a Visit
</button>

<!-- Modal -->
<div class="modal fade" id="myModalNorm" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">
                    <span aria-hidden="true">&times;</span>
                    <span class="sr-only">Close</span>
                </button>
                <h4 class="modal-title" id="myModalLabel">
                    Add an Appointment
                </h4>
            </div>

            //<?php
            //remove appointment_id (auto-generated pk for project)
            //$fields_no_id = array_diff($fields, array('record_id'));
            //$modal_form = $appt_dthelper->renderApptForm($fields_no_id, 'Add an Appointment');
            //$modal_form = Util::renderApptForm($fields, 'Add an Appointment');
            //print($modal_form);

            //?>

        </div>
    </div>
</div>
<link rel="stylesheet" type="text/css" href="<?php echo $module->getUrl("pages/scheduler.css") ?>"/>

<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>

<script type="text/javascript" src="https://code.jquery.com/jquery-1.12.4.js"></script>
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.13/css/jquery.dataTables.min.css"/>
<script type="text/javascript" src="https://cdn.datatables.net/1.10.13/js/jquery.dataTables.min.js"></script>

<script type="text/javascript">
    <!-- Initialize the plugin: -->
    $(document).ready(function() {
        $('#appt').DataTable();
    } );
</script>


<h3>Appointments</h3>

<a href="<?php echo $auth_url?>">Authorize</a>

