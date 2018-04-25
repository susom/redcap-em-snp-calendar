<!-- Initialize the plugin: -->
$.fn.serializeAndEncode = function() {
    return $.map(this.serializeArray(), function(val) {
        return [val.name, encodeURIComponent(val.value)].join('=');
    }).join('&');
};


$(document).ready(function() {

    $('#study-tab-0-dt').DataTable({
        "order": [[4, "asc"]]
    });
    $('#study-tab-1-dt').DataTable({
        "order": [[4, "asc"]]
    });
    $('#study-tab-2-dt').DataTable({
        "order": [[4, "asc"]]
    });
    $('#study-tab-3-dt').DataTable({
        "order": [[4, "asc"]]
    });
    $('#study-tab-4-dt').DataTable({
        "order": [[4, "asc"]]
    });

    // Attach button handlers
    $('.container').on('click', 'button.action', function () {
        var action = $(this).data('action');
        var record_id = $(this).data('record');
        var reg_record_id = document.getElementById("registry_record_id").value;

        console.log('Action: ' + action + ' on record ' + record_id);

         // If the Edit record button is selected
        if (action === "edit-appointment") {
            // If the Edit appointment button is selected
            snp.editAppt(record_id);
//        } else if (action === "delete-appointment") {
            // If the Delete appointment button is selected
//            var confirm_text = "Are you sure you want to delete Appt ID = ".concat(record_id).concat("?");
//            var confirm = window.confirm(confirm_text);
//            if (confirm) {
//                snp.deleteAppt(record_id);
//            }
        } else if (action === "copy-appointment") {
            snp.copyAppt(record_id, reg_record_id);
        } else if (action === "save-appointment") {
            snp.saveAppt(reg_record_id);
        }

    });
});


var snp = snp || {};
snp.editAppt = function (record_id) {

    // initialize the model before displaying
    var modal = $('#apptModal');

    // Load Appointment Details
    $.ajax({
        type: "POST",
        data: {
            "action": "getAppointment",
            "appt_record_id": record_id
        },
        success:function(data) {
            if (data.result !== "success") {
                alert(data.message);
            } else {
                $('#apptModal').find('[name=appt_record_id]').val(data['record_id']);
                $('#apptModal').find('[name=vis_ppid]').val(data['vis_ppid']);
                $('#apptModal').find('[name=vis_study]').val(data['vis_study']);
                $('#apptModal').find('[name=vis_name]').val(data['vis_name']);
                $('#apptModal').find('[name=vis_date]').val(data['vis_date']);
                $('#apptModal').find('[name=vis_start_time]').val(data['vis_start_time']);
                $('#apptModal').find('[name=vis_end_time]').val(data['vis_end_time']);
                $('#apptModal').find('[name=vis_room]').val(data['vis_room']);
                $('#apptModal').find('[name=vis_note]').val(data['vis_note']);
                $('#apptModal').find('[name=vis_status]').val(data['vis_status']);
                $('#apptModal').find('[name=vis_on_calendar]').val(data['vis_on_calendar']);
                modal.modal('toggle');
                window.location = data.data['url'];
                //return false;
            }
        },
        error:function(jqXHR, exception) {
            var msg = '';
            if (jqXHR.status === 0) {
                msg = 'Not connect.\n Verify Network.';
            } else if (jqXHR.status == 404) {
                msg = 'Requested page not found. [404]';
            } else if (jqXHR.status == 500) {
                msg = 'Internal Server Error [500].';
            } else if (exception === 'parsererror') {
                msg = 'Requested JSON parse failed.';
            } else if (exception === 'timeout') {
                msg = 'Time out error.';
            } else if (exception === 'abort') {
                msg = 'Ajax request aborted.';
            } else {
                msg = 'Uncaught Error.\n' + jqXHR.responseText;
            }
            console.log("Error in getAppt: ", msg);
            alert(msg);
        }
    }).done(function (data) {
    }).fail(function (jqXHR, textStatus, errorThrown) {
        //console.log("In editAppt, record ID: ", jqXHR, textStatus, errorThrown);
        console.log("Failed in editAppt, record ID: ", textStatus);
    });

};

/*
snp.deleteAppt = function (record_id) {

    // Delete the appointment in Redcap and in the Outlook calendar where it is stored.
    $.ajax({
        type: "POST",
        data: {
            "action": "deleteAppointment",
            "deleteRecord": record_id
        },
        success:function(data) {
            snp.updateFormData(data);
            var message = "Record ID = ".concat(record_id).concat(" was deleted!");
            alert(message);
        },
        error:function(jqXhr, textStatus, errorThrown) {
            console.log("In deleteAppt, record ID: ", jqXHR, textStatus, errorThrown);
            alert(errorThrown);
        }
    }).done(function (data) {
    });
};
*/

snp.saveAppt = function (reg_record_id) {

    var record_id = $('#apptModal').find('[name=appt_record_id]').val();

    // Check the data to make sure all the necessary is available when saving data in Outlook
    var on_cal = $('#apptModal').find('[name=vis_on_calendar]').val();
    var appt_date = $('#apptModal').find('[name=vis_date]').val();
    var start_time = $('#apptModal').find('[name=vis_start_time]').val();
    var end_time = $('#apptModal').find('[name=vis_end_time]').val();
    var room = $('#apptModal').find('[name=vis_room]').val();

    // If these values aren't filled in, we can't save the appointment on the calendar so don't let
    // the user try to save unless these values are filled in.
    // Check for a valid visit date
    if ((on_cal == 1) && (appt_date === '')) {
        alert("Please enter a visit date before saving.");
        return;
    }

    var today = new Date();
    // Appt always comes back as local time and it assumes appt_date is in UTC time which it is not.
    // I can't figure out how to tell Date that appt_date is already in local time.  So, instead,
    // I am adding one day to the date diff.
    var appt = new Date(appt_date);
    var diffDays = parseInt((appt - today) / (1000 * 60 * 60 * 24)) + 1;
    if ((on_cal == 1) && (diffDays > 180)) {
        if (!confirm("Please confirm the entered visit date is greater than 180 days away.")) {
            return;
        }
    }

    // Check for valid starting times
    if ((on_cal == 1) && (start_time === '')) {
        alert("Please enter a visit starting time.");
        return;
    }
    if ((on_cal == 1) && ((start_time < '08:00') || (start_time > '18:00'))) {
        if (!confirm("Please confirm the starting time is outside of 8:00AM to 6:00PM before saving.")) {
            return;
        }
    }

    // Check for valid ending times
    if ((on_cal == 1) && (end_time === '')) {
        alert("Please enter a visit ending time.");
        return;
    }
    if ((on_cal == 1) && ((end_time < '07:00') || (end_time > '18:00'))) {
        if (!confirm("Please confirm the ending time is outside of 8:00AM to 6:00PM before saving.")) {
            return;
        }
    }

    // Check for an entered room
    if ((on_cal == 1) && (room === null)) {
        alert("Please select a room before saving.");
        return;
    }

    // Save this appointment in Outlook and then update Redcap so they are always in sync
    $.ajax({
        type: "POST",
        data: {
            "action": "saveAppointment",
            "record_id": record_id,
            "vis_ppid": $('#apptModal').find('[name=vis_ppid]').val(),
            "vis_study": $('#apptModal').find('[name=vis_study]').val(),
            "vis_name": $('#apptModal').find('[name=vis_name]').val(),
            "vis_date": appt_date,
            "vis_start_time": start_time,
            "vis_end_time": end_time,
            "vis_room": room,
            "vis_status": $('#apptModal').find('[name=vis_status]').val(),
            "vis_note": $('#apptModal').find('[name=vis_note]').val(),
            "vis_category": $('#apptModal').find('[name=vis_category]').val(),
            "vis_on_calendar": on_cal,
            "registry_record_id" : reg_record_id
        },
        success:function(data) {
            alert(data.message);
            window.location = data.data['url'];
            return false;
        },
        error:function(jqXhr, textStatus, errorThrown) {
            console.log("In saveAppt, record ID: ", jqXHR, textStatus, errorThrown);
            alert(errorThrown);
        }
    }).done(function (data) {
    }).fail(function (jqXHR, textStatus, errorThrown) {
        alert(data.message);
        console.log("Failed in saveAppt, record ID: ", jqXHR, textStatus, errorThrown);
    });

    // close the modal
    var modal = $('#apptModal');
    modal.modal('toggle');

};

snp.copyAppt = function(record_id, reg_record_id) {

    // Load Appointment Details
    $.ajax({
        type: "POST",
        data: {
            "action": "copyAppointment",
            "appt_record_id": record_id,
            "registry_record_id" : reg_record_id
        },
        success:function(data) {
            alert(data.message);
            window.location = data['url'];
            return false;
        },
        error:function(jqXhr, textStatus, errorThrown) {
            console.log("In copyAppt, record ID: ", jqXHR, textStatus, errorThrown);
            alert(errorThrown);
        }

    }).done(function (data) {
    }).fail(function (jqXHR, textStatus, errorThrown) {
        console.log("Failed in copyAppt, record ID: ", jqXHR, textStatus, errorThrown);
    });
}

