<?php
/**
 * Created by PhpStorm.
 * User: andy123
 * Date: 3/8/18
 * Time: 1:06 PM
 */

namespace Stanford\SNP;

use \REDCap;

class Util
{

    /**
     * Determine the next ID
     * If no prefix is supplied, it is a auto-number generator within the supplied arm_event
     * If a prefix is supplied, it returns the prefix or appends -2, -3, ... if it already exists
     * @param int $pid              The project
     * @param string $id_field      The PK field
     * @param null $arm_event int   The arm_event if longitudinal
     * @param string $prefix        If specified, this will become the next ID or -2,-3,-4.. will be added if it already exists
     * @return int|string
     */
    public static function getNextId($pid, $id_field, $arm_event = NULL, $prefix = '') {
        $q = REDCap::getData($pid,'array',NULL,array($id_field), $arm_event);

        if ( !empty($prefix) ) {
            // A prefix is supplied - first check if it is used
            if ( !isset($q[$prefix]) ) {
                // Just use the plain prefix as the new record name
                return $prefix;
            } else {
                // Lets start numbering at 2 until we find an open record id:
                $i = 2;
                do {
                    $next_id = $prefix . "-" . $i;
                    $i++;
                } while (isset($q[$next_id]));
                return $next_id;
            }
        } else {
            // No prefix
            $new_id = 1;
            foreach ($q as $id=>$event_data) {
                if (is_numeric($id) && $id >= $new_id) $new_id = $id + 1;
            }
            return $new_id;
        }
    }

    public static function getData($pid, $event_arm, $record_id, $fields, $labels=TRUE) {

        // Allow the option to return the data back in raw form instead of labels.
        $q = REDCap::getData($pid, 'json', $record_id, $fields, $event_arm, null, null, null, null, null, $labels);
        $results = json_decode($q, true);

        // Take out the arm info
        $fields_no_event = array();
        foreach ($results as $appt) {
            unset($appt['redcap_event_name']);
            array_push($fields_no_event, $appt);
        }
        return $fields_no_event;
    }

    public static function getDictChoices($pid, $field)
    {

        static $data_dictionary = array();
        static $pid_saved = null;

        if (empty($field)) {
            SNP::error("The variable list is undefined so cannot retrieve data dictionary options.");
        }

        // Retrieve data dictionary for this project
        if ($pid !== $pid_saved or is_null($data_dictionary) or empty($data_dictionary)) {
            $data_dictionary = REDCap::getDataDictionary($pid, 'array');
            $pid_saved = $pid;
        }

        $choice_list = $data_dictionary[$field]['select_choices_or_calculations'];
        $exploded = explode('|',$choice_list);

        $choices = array();
        foreach ($exploded as $value) {
            $temp = explode(',',$value);
            $choices[trim($temp[0])]= trim($temp[1]);
        }

        return $choices;
    }


    public static function getLabel($pid, $field, $value) {
        $vis_study_choices  =  Util::getDictChoices($pid, $field);
        $label = $vis_study_choices[$value];
        return $label;
    }

    public static function renderCalTable($id, $header = array(), $results, $pid, $event=null, $icons, $get_appt_id=null, $update_rights=false) {
        //Render table
        $grid = '<table id="' . $id . '" class="display" style="width: 100%">';
        // If we are adding Edit, Delete and Copy buttons use the calendar functions
        $grid .= self::renderCalHeaderRow($header, 'thead', $update_rights);
        $grid .= self::renderCalTableRows($results, $pid, $event, $icons, $get_appt_id, $update_rights);
        $grid .= '</table>';

        return $grid;
    }

    private static function renderCalHeaderRow($header = array(), $tag, $update_rights) {
        $row = '<'.$tag.'><tr>';
        foreach ($header as $col_key => $this_col) {
            if ($this_col == 'Appt ID') {
                $row .= '<th class="no-print">' . $this_col . '</th>';
            } else {
                $row .= '<th>' . $this_col . '</th>';
            }
        }
        // If the person has Save/Update permissions, add an extra column to the header for the Update and Copy buttons
        if ($update_rights) {
            $row .= '<th class="no-print"> </th>';
        }

        $row .= '</tr></' . $tag . '>';

        return $row;
    }

    private static function renderCalTableRows($row_data=array(), $appt_pid, $appt_event, $icons, $get_appt_id, $update_rights) {
        $rows = '';

        foreach ($row_data as $row_key=>$this_row) {
            if ($get_appt_id == $this_row['record_id']) {
                $rows .= '<tr style="color: red" id="' . $this_row['record_id'] . '">';
            } else {
                $rows .= '<tr id="' . $this_row['record_id'] . '">';
            }
            foreach ($this_row as $col_key=>$this_col) {
                // Don't display the record_id because we are using the link instead
                if($col_key === 'record_id') {
                    $url = APP_PATH_WEBROOT . "DataEntry/index.php?page=appointment&pid=" . $appt_pid . "&id=" . $this_row[$col_key] .
                        "&=event=" . $appt_event;
                    $rows .= '<td class="no-print"><a href=' . $url . '>' .$this_col . '</a><br>';

                    if (!is_null($this_row['vis_note']) and !empty($this_row['vis_note'])) {
                        $rows .= '<img class="show-image" id="note' . $this_row['record_id'] . '" src="' . $icons["note"] . '" alt="Visit Note Available" >&nbsp;&nbsp;';
                    } else {
                        $rows .= '<img class="noshow-image" id="note' . $this_row['record_id'] . '" src="' . $icons["note"] . '" alt="Visit Note Available" >&nbsp;&nbsp;';
                    }

                    if ($this_row['vis_on_calendar'] == 1)  {
                        $rows .= '<img class="show-image" id="note' . $this_row['record_id'] . '" src="' . $icons["calendar"] . '" alt="Appointment Saved on Calendar" >&nbsp;&nbsp;';
                    } else {
                        $rows .= '<img class="noshow-image" id="note' . $this_row['record_id'] . '" src="' . $icons["calendar"] . '" alt="Appointment Saved on Calendar" >&nbsp;&nbsp;';
                    }
                    $rows .= '</td>';
                } else if (is_array($this_col)) {
                    switch ($this_col[1]) {
                        case "DATE":
                            $rows .= '<td><input type="date" id="' . $this_row['record_id'] . '*' . $col_key . '" name="' . $this_row['record_id'] . '*' . $col_key . '" value="' . $this_col[0] . '"></td>';
                            break;
                        case "SELECT":
                            $rows .= self::renderSelectRow($this_row['record_id'] . '*' . $col_key, trim($this_col[0]), $this_col[2]);
                            break;
                        default:
                            $rows .= '<td><input type="text" id="' . $this_row['record_id'] . '*' . $col_key . '" name="' . $this_row['record_id'] . '*' . $col_key . '" value="' . $this_col[0] . '">$this_col[1]</td>';
                    }
                } else if (($col_key !== 'vis_note') && ($col_key !== 'vis_on_calendar')) {
                    $rows .= '<td>' . $this_col . '</td>';
                }
            }

            // Add the Save/Delete buttons
            if ($update_rights) {
                $rows .= '<td class="no-print">';
                $record_identifier = 'data-record="' . $this_row['record_id'] . '"';
                $rows .= '<button type="button" class="btn btn-xs btn-primary action" data-action="edit-appointment" ' . $record_identifier . '><span class="glyphicon glyphicon-pencil"></span> Edit</button>';
                $rows .= '<button type="button" class="btn btn-xs btn-primary action" data-action="copy-appointment" ' . $record_identifier . '><span class="glyphicon glyphicon-copy"></span> Copy</button>';
                $rows .= '</td>';
            }

            // End row
            $rows .= '</tr>';
        }

        return $rows;
    }

    public static function renderApptTable($id, $header = array(), $results, $pid, $event=null) {
        //Render table
        // If this a display of all appointments, use the appointment functions
        $grid = '<table id="' . $id . '" class="display" style="width: 100%">';
        $grid .= self::renderApptHeaderRow($header, 'thead');
        $grid .= self::renderApptTableRows($results, $pid, $event);
        $grid .= '</table>';

        return $grid;
    }

    private static function renderApptHeaderRow($header = array(), $tag) {
        $row = '<'.$tag.'><tr>';
        foreach ($header as $col_key => $this_col) {
            $row .=  '<th>'.$this_col.'</th>';
        }
        $row .= '</tr></'.$tag.'>';
        return $row;
    }

    private static function renderApptTableRows($row_data=array(), $appt_pid, $appt_event) {

        $rows = '';

        foreach ($row_data as $row_key=>$this_row) {
            $rows .= '<tr id="' . $this_row['record_id']. '">';
            foreach ($this_row as $col_key=>$this_col) {
                // Don't display the record_id because we are using the link instead
                if($col_key === 'record_id') {
                    $url = APP_PATH_WEBROOT . "DataEntry/index.php?page=appointment&pid=" . $appt_pid . "&id=" . $this_row[$col_key] .
                        "&=event=" . $appt_event;
                    $rows .= '<td><a href=' . $url . '>' . $this_col . '</a></td>';
                } else if (is_array($this_col)) {
                    switch ($this_col[1]) {
                        case "DATE":
                            $rows .= '<td><input type="date" id="' . $this_row['record_id'] . '*' . $col_key . '" name="' . $this_row['record_id'] . '*' . $col_key . '" value="' . $this_col[0] . '"></td>';
                            break;
                        case "SELECT":
                            $rows .= self::renderSelectRow($this_row['record_id'] . '*' . $col_key, trim($this_col[0]), $this_col[2]);
                            break;
                        default:
                            $rows .= '<td><input type="text" id="' . $this_row['record_id'] . '*' . $col_key . '" name="' . $this_row['record_id'] . '*' . $col_key . '" value="' . $this_col[0] . '">$this_col[1]</td>';
                    }
                } else {
                    $rows .= '<td>' . $this_col . '</td>';
                }
            }

            // End row
            $rows .= '</tr>';
        }

        return $rows;
    }

    private static function renderSelectRow($name, $selected = null,  $options = array()) {
        $rows = '<td><select size="1" id="'.$name.'" name="'.$name.'">';
        foreach ($options as $col_key=>$this_col) {
            $rows .= '<option value="'.$col_key.'"';
            if ($selected == $col_key) {
                $rows .= 'selected="selected"';
            }
            $rows .='>'.$this_col.'</option>';
        }
        $rows .= '</select></td>';
        return $rows;
    }


    public static function addDaysToDate($old_date, $days){
        $date = strtotime("+". $days ." days", strtotime($old_date));
        return  date("Y-m-d", $date);
    }


    public static function displayAppointments($pid = null, $fields, $event_name) {

        try {
            // We are getting records for all appointments so do not specify a record_id
            $results = Util::getData($pid, $event_name, null, $fields, true);
            $headers = Util::getHeader($pid, $fields);
            $grid = Util::renderApptTable('appt', $headers, $results, $pid, $event_name);
            return $grid;
        } catch (Exception $e) {
            throw $e;
        }
    }

    private static function getHeader($pid, $fields) {

        if (empty($fields)) {
            throw new Exception("The variable list is undefined.");
        }

        // Get the data dictionary
        $data_dictionary = REDCap::getDataDictionary($pid, 'array');

        //lookup each field in data dictionary
        $header = array();
        foreach($fields as $item) {
            $header[] = $data_dictionary[$item]['field_label'];
        }
        return $header;
    }

}