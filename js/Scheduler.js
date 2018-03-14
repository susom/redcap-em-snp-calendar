<!-- Initialize the plugin: -->
$.fn.serializeAndEncode = function() {
    return $.map(this.serializeArray(), function(val) {
        return [val.name, encodeURIComponent(val.value)].join('=');
    }).join('&');
};

$(document).ready(function() {

    $('#study-tab-0-dt').DataTable({
        "order" : [[4, "asc"]]
    });
    $('#study-tab-1-dt').DataTable({
        "order" : [[4, "asc"]]
    });
    $('#study-tab-2-dt').DataTable({
        "order" : [[4, "asc"]]
    });
    $('#study-tab-3-dt').DataTable({
        "order" : [[4, "asc"]]
    });
    $('#study-tab-4-dt').DataTable({
        "order" : [[4, "asc"]]
    });

    //$('#study-tab-3-dt').DataTable({
    //    responsive: true
    //    processing : true,
    //    serverSide : true,
    //    ajax: "needPathName/Scheduler.php"
    //});

    // Attach button handlers
    $('.container').on('click','button.action',function () {
        console.log("In container action");
        var action=$(this).data('action');
        var record_id=$(this).data('record');
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
        } else if (action === "save-appointment"){
            console.log('Calling saveAppt for record: ' + record_id);
            snp.saveAppt();
        }

        console.log('Back from Action: ' + action + ' on record ' + record_id);

    });
} );



var snp = snp || {};
snp.editAppt= function(record_id) {

    // initialize the model before displaying
    var modal = $('#apptModal');

    // Load Appointment Details
    $.ajax({
        type: "POST",
        data: {
            "action": "getAppointment",
            "appt_record_id": record_id
        }
    }).done(function(data) {

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
    }).fail(function(jqXHR, textStatus, errorThrown) {
        console.log("In editAppt, record ID: ", jqXHR, textStatus, errorThrown);

    });
};

snp.deleteAppt= function(record_id) {

    // Delete the appointment in Redcap and in the Outlook calendar where it is stored.
    $.ajax({
        type: "POST",
        data: {"action": "deleteAppointment",
                "deleteRecord": record_id }
    }).done(function(data) {
        if (data.result !== "success") {
            alert(data.message);
        } else {
            var message = "Record ID = ".concat(record_id).concat(" was deleted!");
            alert(message);
        }
    });
};

snp.saveAppt = function() {

    console.log("In Save Appt: ");

    var record_id = $('#apptModal').find('[name=appt_record_id]').val();
    var username = "yasukawa";
    console.log("Save Appt record ID: " + record_id + ", and user: " + username);

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
            "last_update_made_by": username
        }
    }).done(function(data) {
        console.log("Returned data: " , data);

        if (data.result !== "success") {
            alert(data.message);
        } else {
            var message = "Record ID = ".concat(record_id).concat(" was saved!");
            alert(message);
        }
    }).fail(function(jqXHR, textStatus, errorThrown) {
        console.log("In saveAppt, record ID: ", jqXHR, textStatus, errorThrown);
    });

    // close the modal
    var modal = $('#apptModal');
    modal.modal('toggle');

};
