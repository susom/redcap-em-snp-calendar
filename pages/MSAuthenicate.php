<?php
namespace Stanford\SNP;
/**
 * This is the authorization page for authenticating a microsoft account for use in the snp-schedule-assistant application
 */

global $module;

$pid = isset($_GET['pid']) && !empty($_GET['pid']) ? $_GET['pid'] : null;
$token_event_id = $module->getProjectSetting('token_event_id');

if (isset($_POST['start'])) {
    $msApi = new MSGraphAPI($pid, $token_event_id);
    if (!$msApi->authorizeUserRedirect()) {
        msApi::log("Errors with authorizedUserRedirect","DEBUG");
    };
    $body = $msApi->getErrors();
    echo "This is body " . $body . "<br>";
}

include "bs_head.php";

?>
<html>
    <head>
        <title>Authenicate to Outlook</title>
        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/bs-3.3.7/jqc-1.12.4/jszip-3.1.3/pdfmake-0.1.27/dt-1.10.15/b-1.3.1/b-colvis-1.3.1/b-flash-1.3.1/b-html5-1.3.1/b-print-1.3.1/datatables.min.css"/>
        <link rel="stylesheet" type="text/css" href="<?php echo $module->getUrl("pages/scheduler.css") ?>" />
        <link rel="icon" type="image/png" href="https://med.stanford.edu/etc/clientlibs/sm/images/favicon.ico">
    </head>
    <body>
    <div class='container'>
        <div class="text-center" style="width:100%;">
            <div class="page-header">
                <h1>SNP Calendar Authorization Page</h1>
            </div>
        </div>
        <div class="row text-center">
            <form method="POST">
                <button name="start" type="submit" class="start btn-lg btn-primary pt18">Authorize Access</button>
            </form>
            <div>
                <?php echo $body ?>
            </div>
        </div>
         <div class='row stanford_med'></div>
    </div>

    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src='https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js'></script>
    <!-- Include all compiled plugins (below), or include individual files as needed -->
    <script src='https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js'></script>
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.10.13/js/jquery.dataTables.min.js"></script>

    <script>
        $(document).ready(function() {
        });
    </script>

    </body>
    </html>

<?php