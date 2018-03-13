<?php
namespace Stanford\SNP;
/** @var \Stanford\SNP\SNP $module */
/** @var string $project_id */

/**
 * This is the authorization page for authenticating a microsoft account for use in the snp-schedule-assistant application
 */


require_once ("../classes/Util.php");
require_once ("../classes/MSGraphAPI.php");


$webauth_user = USERID; //$_SERVER['REMOTE_USER'];

$token_pid = $project_id;
$token_event_id = $module->getProjectSetting('token_event_id');
$msApi = new MSGraphAPI($token_pid,$token_event_id);


if (isset($_POST['start'])) {
    // $msApi = new msApi($webauth_user);
    if (!$msApi->authorizeUserRedirect()) {
        $msApi::log("Errors with authorizedUserRedirect","DEBUG");
    };
} elseif (!empty($_POST)) {
    // This is a post-back from Microsoft

    $msApi::log($_POST, "DEBUG", "Incoming POST");
    $result = $msApi->processAuthorizationPostBack();

    if ($result) {
        // Success
        $msApi::log($result, "DEBUG", "Authorization successful");
        redirect($msApi->home_url);
        exit();
    } else {
        // Errors
        $msApi::log($result, "DEBUG", "Authorization Errors");
    }
} else {
    // $msApi::log($_REQUEST, "DEBUG", "Incoming Non-POST");
}


include "bs_head.php";

?>
    <body>
    <div class='container'>
        <?php if ($msApi) echo $msApi->getErrors() ?>
        <?php //echo getSessionMessage() ?>
        <div class="text-center" style="width:100%;">
            <div class="page-header">
                <h1><?php print $module->getModuleName() ?></h1>
            </div>
        </div>
        <div class="row text-center">
            <h2>
                This page will redirect you to Microsoft to authorize the use of your account for scheduled calendar integration.
            </h2>
            <form method="POST">
                <button name="start" type="submit" class="start btn-lg btn-primary pt18">Authorize Access</button>
            </form>
        </div>
        <div class="row" style="margin-top: 20px;">
            <div class="text-center">
                <a href="index.php">
                    <button class="btn btn-lg btn-primary pt18">Return to Home (not working)</button>
                </a>
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