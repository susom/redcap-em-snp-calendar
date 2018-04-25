<?php
namespace Stanford\SNP;
/** @var \Stanford\SNP\SNP $module */

use \REDCap;


$webauth_user = $_SERVER['REMOTE_USER'];
$eventList = array();


if (isset($_POST)) {
    if (isset($_POST['modified-calendar-events'])) {
        $calList = new calendarChangeEvents();
        $eventList = $calList->getModifiedCalendarEventList();
        $body = $calList->getReturnMessage();
    }
}


?>
<html>
<head>
    <title>Compare Outlook Appts to Redcap</title>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/bs-3.3.7/jqc-1.12.4/jszip-3.1.3/pdfmake-0.1.27/dt-1.10.15/b-1.3.1/b-colvis-1.3.1/b-flash-1.3.1/b-html5-1.3.1/b-print-1.3.1/datatables.min.css"/>
    <link rel="stylesheet" type="text/css" href="<?php echo $module->getUrl("pages/scheduler.css") ?>" />
    <link rel="icon" type="image/png" href="https://med.stanford.edu/etc/clientlibs/sm/images/favicon.ico">
</head>
<body>
<div class='container'>
    <div class="text-center" style="width:100%;">
        <div class="page-header">
            <h1>Check for changes in Outlook calendars</h1>
            <h6>This tool retrieves data from SNP Outlook Calendars and displays changed events from Recap PID 10062.</h6>
        </div>
    </div>
    <div class="row">
        <div class="well">
            <?php echo $body ?>
        </div>
    </div>

    <div class="row">
        <form method="POST">
            <div class="text-center">
                <button name="modified-calendar-events" value="1" type="submit" class="start btn-lg btn-primary pt18">Retrieve Modified Calendar Events</button>
            </div>
        </form>
    </div>

    <br>
    <div>
        <table style="border-collapse: separate; border: solid; border-width:thin; width:90%;">
            <?php if (!is_null($calList) && !empty($calList)) { ?>
            <tr>
                <td style="border: solid; border-width:medium; padding: 10px">Redcap Record ID</td>
                <td style="border: solid; border-width:medium; padding: 10px">Subject</td>
                <td style="border: solid; border-width:medium; padding: 10px">Start Time</td>
                <td style="border: solid; border-width:medium; padding: 10px">End Time</td>
                <td style="border: solid; border-width:medium; padding: 10px">Location</td>
                <td style="border: solid; border-width:medium; padding: 10px">Change</td>
            </tr>
            <tr>
                <?php
                foreach ($eventList

                as $event) {
                $record_id = $event['record_id'];
                $subject = $event['subject'];
                $startTime = $event['start'];
                $endTime = $event['end'];
                $location = $event['location'];
                $msg = $event['msg'];
                ?>
                <td style="border: solid; border-width:thin; padding: 10px"><?php echo $record_id ?></td>
                <td style="border: solid; border-width:thin; padding: 10px"><?php echo $subject ?></td>
                <td style="border: solid; border-width:thin; padding: 10px"><?php echo $startTime ?></td>
                <td style="border: solid; border-width:thin; padding: 10px"><?php echo $endTime ?></td>
                <td style="border: solid; border-width:thin; padding: 10px"><?php echo $location ?></td>
                <td style="border: solid; border-width:thin; padding: 10px"><?php echo $msg ?></td>
            </tr>
            <tr>
                <?php }
                }?>
            </tr>
        </table>
    </div>
    <div class='row stanford_med'></div>
</div>

<!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
<script src='https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js'></script>
<!-- Include all compiled plugins (below), or include individual files as needed -->
<script src='https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js'></script>

<script>
    $(document).ready(function () {
        // Fix issue with zooming modal dialog in Safari 9
        // http://stackoverflow.com/questions/32675849/screen-zooms-in-when-a-bootstrap-modal-is-opened-on-ios-9-safari
        if (/iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream) {
            document.querySelector('meta[name=viewport]').setAttribute(
                'content',
                'initial-scale=1.0001, minimum-scale=1.0001, maximum-scale=1.0001, user-scalable=no'
            );
        }
    });
</script>
</body>
</html>


