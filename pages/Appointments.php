<?php
namespace Stanford\SNP;
/** @var \Stanford\SNP\SNP $module */

use \REDCap;
use \Exception;

$module->sLog("Starting Appointment Report");
$event_name = $module->getProjectSetting('appt_event_id');
$pid = 10062;

////////////// DONT EDIT BELOW HERE //////////////
$fields = array('record_id', 'vis_ppid', 'vis_study', 'vis_name', 'vis_category',
                'vis_date', 'vis_start_time', 'vis_end_time', 'vis_room', 'vis_note');


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
        SNP::sLog("next id is $next_id");

        $data['record_id']=$next_id;

        $result = REDCap::saveData($pid, 'json', json_encode(array($data)), 'overwrite');
        SNP::sLog("Creating new record $next_id in main project: " . print_r($result,true), "DEBUG");
    }


}

function getAllAppointments()
{
    global $pid, $event_name, $fields;
    //display appointment table
    try {
        $appointment_table = Util::displayAppointments($pid, $fields, $event_name);
    } catch (Exception $e) {
        echo $e;
        exit;
    }
    return $appointment_table;
}

######## HTML PAGE ###########

?>

<html>
<head>
    <title>Appointment</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <link rel="stylesheet" type="text/css" href="<?php echo $module->getUrl("pages/scheduler.css") ?>"/>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css">

</head>
<body>
    <br>
    <div align="center">
        <h1>Appointments</h1>
        <!--
        <table id="appt" class="display" style="width:100%">
        -->
            <?php echo getAllAppointments() ?>
        <!--
        </table>
        -->
    </div>

    <script type="text/javascript" language="javascript" src="https://code.jquery.com/jquery-1.12.4.js"></script>
    <script type="text/javascript" language="javascript" src="https://cdn.datatables.net/1.10.16/js/jquery.dataTables.min.js"></script>

    <script type="text/javascript" language="javascript" >
        <!-- Initialize the plugin: -->
        $(document).ready(function() {
            $('#appt').DataTable({
                "order" : [[4, "asc"]]
            });
        } );
    </script>

</body>
</html>
