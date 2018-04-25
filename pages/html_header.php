<?php

// BOOTSTRAP HEADER SECTION TO BE INCLUDED BY ANOTHER SCRIPT

?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo calendarConfig::$client_app_name ?></title>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>

    <!-- Bootstrap core CSS -->
    <link href='https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css' rel='stylesheet' media='screen'>
    <!-- HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <script src='https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js'></script>
    <script src='https://oss.maxcdn.com/respond/1.4.2/respond.min.js'></script>
    <style>

        .page-header { border:none; }

        form { overflow:hidden; }

        .stanford_med {
            height:130px;
            margin:auto;
            max-width: 500px;
            background:url(../images/stanford_med.png) 50% 50% no-repeat;
            background-size:90%;
            margin-top: 30px;
        }

        .btn-primary {
            color: #fff;
            background-color: #00aae0;
            border-color: #2e6da4;
        }
    </style>
</head>

<?php

