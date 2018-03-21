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

        console.log('Action: ' + action + ' on record ' + record_id);

         // If the Edit record button is selected
        if (action === "edit-appointment") {
            // If the Edit appointment button is selected
            snp.editAppt(record_id);
        } else if (action === "delete-appointment") {
            // If the Delete appointment button is selected
            var confirm_text = "Are you sure you want to delete Appt ID = ".concat(record_id).concat("?");
            var confirm = window.confirm(confirm_text);
            if (confirm) {
                snp.deleteAppt(record_id);
            }
        } else if (action === "save-appointment") {
            snp.saveAppt();
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
        }
    }).done(function (data) {

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
            modal.modal('toggle');
        }
    }).fail(function (jqXHR, textStatus, errorThrown) {
        console.log("In editAppt, record ID: ", jqXHR, textStatus, errorThrown);
    });

};

snp.deleteAppt = function (record_id) {

    // Delete the appointment in Redcap and in the Outlook calendar where it is stored.
    $.ajax({
        type: "POST",
        data: {
            "action": "deleteAppointment",
            "deleteRecord": record_id
        },
        success:function() {
            //$('tr#'.concat(record_id)).removeData;
            //location.href = location.href;
        }
    }).done(function (data) {
        if (data.result !== "success") {
            alert(data.message);
        } else {
            var message = "Record ID = ".concat(record_id).concat(" was deleted!");
            alert(message);
        }
    });
};

snp.saveAppt = function () {

    var record_id = $('#apptModal').find('[name=appt_record_id]').val();

    // Save this appointment in Outlook and then update Redcap so they are always in sync
    $.ajax({
        type: "POST",
        data: {
            "action": "saveAppointment",
            "record_id": record_id,
            "vis_ppid": $('#apptModal').find('[name=vis_ppid]').val(),
            "vis_study": $('#apptModal').find('[name=vis_study]').val(),
            "vis_name": $('#apptModal').find('[name=vis_name]').val(),
            "vis_date": $('#apptModal').find('[name=vis_date]').val(),
            "vis_start_time": $('#apptModal').find('[name=vis_start_time]').val(),
            "vis_end_time": $('#apptModal').find('[name=vis_end_time]').val(),
            "vis_room": $('#apptModal').find('[name=vis_room]').val(),
            "vis_status": $('#apptModal').find('[name=vis_status]').val(),
            "vis_note": $('#apptModal').find('[name=vis_note]').val(),
            "vis_category": $('#apptModal').find('[name=vis_category]').val()
        },
        success:function(data) {
            snp.updateFormData(data.data);
            var message = "Record ID = ".concat(record_id).concat(" was saved!");
            alert(message);
        },
        error:function(jqXhr, textStatus, errorThrown) {
            console.log("In saveAppt, record ID: ", jqXHR, textStatus, errorThrown);
            alert(errorThrown);
        }
    }).done(function (data) {
    }).fail(function (jqXHR, textStatus, errorThrown) {
        alert(data.message);
        console.log("In saveAppt, record ID: ", jqXHR, textStatus, errorThrown);
    });

    // close the modal
    var modal = $('#apptModal');
    modal.modal('toggle');

};

snp.updateFormData = function(data) {

    // Retrieve the row in the table that was updated
    var record_id = data['record_id'];
    var record_row = document.getElementById(record_id);
    var cells = record_row.getElementsByTagName("td");

    // If the participant or study was changed, remove this row from the table since it no longer belongs
    //if ((data['vis_study'] != saved_study) || (data['vis_ppid'] != saved_ppid)) {
    //    console.log("Save study name: ", saved_study, " and saved ppid ", saved_ppid);
    //    record_row.deleteRow(0);
    //} else {
        // Calculate the final duration of the appointment
        var start_time = data['vis_date'] + ' ' + data['vis_start_time'];
        var end_time = data['vis_date'] + ' ' + data['vis_end_time'];
        var datediff = (new Date(end_time)).getTime() - (new Date(start_time)).getTime();
        var datediff_hrs = Math.abs(datediff) / 3600000;

        // Update the cells on the scheduler page
        cells[1].innerHTML = data['vis_name'];
        cells[2].innerHTML = data['vis_category_label'];
        cells[3].innerHTML = datediff_hrs;
        cells[4].innerHTML = data['vis_date'];
        cells[5].innerHTML = data['vis_start_time'];
        cells[6].innerHTML = data['vis_status_label'];
    //}
};