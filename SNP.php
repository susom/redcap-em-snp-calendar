<?php

namespace Stanford\SNP;


require_once("classes/Appt.php");
require_once("classes/MSGraphAPI.php");
require_once("classes/Util.php");
require_once("classes/calendarChangeEvents.php");


class SNP extends \ExternalModules\AbstractExternalModule
{

    public function __construct()
    {
        parent::__construct();

        // Project-specific constructor
        global $project_id;
        if ($project_id > 0) {
            self::sLog("In project $project_id");




        } else {
            self::sLog("Not in project");
        }

    }


    public static function sLog($obj = "Here", $detail = null, $type = "INFO") {
        self::writeLog($obj, $detail, $type);
    }

    public static function sDebug($obj = "Here", $detail = null, $type = "DEBUG") {
        self::writeLog($obj, $detail, $type);
    }

    public static function sError($obj = "Here", $detail = null, $type = "ERROR") {
        self::writeLog($obj, $detail, $type);
        //TODO: BUBBLE UP ERRORS FOR REVIEW!
    }

    public static function writeLog($obj, $detail, $type) {
        $plugin_log_file = \ExternalModules\ExternalModules::getSystemSetting('snp_calendar','log_path');

        // Get calling file using php backtrace to help label where the log entry is coming from
        $bt = debug_backtrace();
        $calling_file = $bt[1]['file'];
        $calling_line = $bt[1]['line'];
        $calling_function = $bt[3]['function'];
        if (empty($calling_function)) $calling_function = $bt[2]['function'];
        if (empty($calling_function)) $calling_function = $bt[1]['function'];
        // if (empty($calling_function)) $calling_function = $bt[0]['function'];

        // Convert arrays/objects into string for logging
        if (is_array($obj)) {
            $msg = "(array): " . print_r($obj,true);
        } elseif (is_object($obj)) {
            $msg = "(object): " . print_r($obj,true);
        } elseif (is_string($obj) || is_numeric($obj)) {
            $msg = $obj;
        } elseif (is_bool($obj)) {
            $msg = "(boolean): " . ($obj ? "true" : "false");
        } else {
            $msg = "(unknown): " . print_r($obj,true);
        }

        // Prepend prefix
        if ($detail) $msg = "[$detail] " . $msg;

        // Build log row
        $output = array(
            date( 'Y-m-d H:i:s' ),
            empty($project_id) ? "-" : $project_id,
            basename($calling_file, '.php'),
            $calling_line,
            $calling_function,
            $type,
            $msg
        );

        // Output to plugin log if defined, else use error_log
        if (!empty($plugin_log_file)) {
            file_put_contents(
                $plugin_log_file,
                implode("\t",$output) . "\n",
                FILE_APPEND
            );
        }
        if (!file_exists($plugin_log_file)) {
            // Output to error log
            error_log(implode("\t",$output));
        }
    }

}