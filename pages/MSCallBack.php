<?php
namespace Stanford\SNP;
/** @var \Stanford\SNP\SNP $module */


// $module::log("In  " . __FILE__);
// require_once("../classes/MSGraphAPI.php");

global $project_id;

$msg = "Invalid";

if (!empty($_POST)) {
    $msApi = new MSGraphAPI($project_id, $module->getProjectSetting('token_event_id'));
    $module::log($_POST, "DEBUG", "Authorization Page: Incoming POST");
    $result = $msApi->processAuthorizationPostBack();


    print "<pre>" . print_r($result,true) . "</pre>";

    if ($result) {
        // Success
        $module::log($result, "DEBUG", "Authorization successful");
        redirect($module->getUrl("pages/Scheduler.php"));
        exit();
    } else {
        // Errors
        $module::log($result, "ERROR", "Errors with authorization");

        $msg = $msApi->getErrors();
    }
}

include "html_header.php";

?>
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
                <?php echo $msg; ?>
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