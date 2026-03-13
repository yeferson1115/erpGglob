$(document).ready(function () {
    $('#main-form').submit(function (e) {
        e.preventDefault();

        $('.missing_alert').hide();

        var formData = new FormData(this); // recoge todos los inputs, incluido el file

        $('#main-form input, #main-form button').attr('disabled', true);
        $('#ajax-icon').removeClass('fa fa-edit').addClass('fa fa-spin fa-refresh');

        $.ajax({
            url: $('#main-form #_url').val(),
            headers: { 'X-CSRF-TOKEN': $('#main-form #_token').val() },
            type: 'POST', // PUT no funciona bien con FormData en jQuery, mejor forzar POST y spoof method
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                var json = $.parseJSON(response);
                if (json.success) {
                    _alertGeneric('success', 'Muy bien!', 'Usuario actualizado correctamente', '/users');
                }
            },
            error: function (data) {
                var errors = data.responseJSON;
                $.each(errors.errors, function (key, value) {
                    toastr.error(value);
                    return false;
                });
                $('#main-form input, #main-form button').prop('disabled', false);
                $('#ajax-icon').removeClass('fa fa-spin fa-refresh').addClass('fa fa-save');
            }
        });

        return false;
    });
});
