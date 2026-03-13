$(document).ready(function () {
    $('#main-form').submit(function (e) {
        e.preventDefault(); // 🚀 evitar el submit normal
        $('.missing_alert').hide();

        if ($('#main-form #name').val() === '') {
            $('#main-form #name_alert').text('Campo Obligatorio').show();
            $('#main-form #name').focus();
            return false;
        }

        if ($('#main-form #last_name').val() === '') {
            $('#main-form #last_name_alert').text('Campo Obligatorio').show();
            $('#main-form #last_name').focus();
            return false;
        }

        

        if (!$('#main-form #email').val().match(/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/)) {
            $('#main-form #email_alert').text('Ingrese correo electrónico válido').show();
            $('#main-form #email').focus();
            return false;
        }

        if (!$('#main-form #password').val().match(/^[a-zA-Z0-9\.!@#\$%\^&\*\?_~\/]{6,30}$/)) {
            $('#main-form #password_alert').text('Ingrese contraseña de al menos 06 caracteres').show();
            $('#main-form #password').focus();
            return false;
        }

        if ($('#main-form #password_confirmation').val() === '') {
            $('#main-form #password_confirmation_alert').text('Ingrese contraseña nuevamente').show();
            $('#main-form #password_confirmation').focus();
            return false;
        }

        if ($('#main-form #password_confirmation').val() !== $('#main-form #password').val()) {
            $('#main-form #password_confirmation_alert').text('Contraseñas no coinciden').show();
            $('#main-form #password_confirmation').focus();
            return false;
        }

        // 🚀 Usar FormData en lugar de serialize
        var formData = new FormData($('#main-form')[0]);

        $('#main-form input, #main-form button').attr('disabled', true);
        $('#ajax-icon').removeClass('fa fa-save').addClass('fa fa-spin fa-refresh');

        $.ajax({
            url: $('#main-form #_url').val(),
            headers: { 'X-CSRF-TOKEN': $('#main-form #_token').val() },
            type: 'POST',
            data: formData,
            processData: false, // necesario para FormData
            contentType: false, // necesario para FormData
            success: function (response) {
                // ✅ No parsees, ya es JSON
                if (response.success) {
                    $('#main-form #submit').hide();
                    _alertGeneric('success', 'Muy bien! ', 'Usuario creado correctamente', '/users');
                }

                // 🔄 siempre reactivar el formulario al final
                $('#main-form input, #main-form button').removeAttr('disabled');
                $('#ajax-icon').removeClass('fa fa-spin fa-refresh').addClass('fa fa-save');
            },
            error: function (xhr) {
              // ✅ Laravel manda errores de validación en xhr.responseJSON.errors
              if (xhr.status === 422) {
                  let errors = xhr.responseJSON.errors;

                  // 🔄 mostrar todos los errores
                  $.each(errors, function (key, value) {
                      _alertGeneric('error', 'Error de validación', value[0]); 
                  });
              } else {
                  _alertGeneric('error', 'Error inesperado', 'Intente de nuevo más tarde');
              }

              // 🔄 siempre reactivar el formulario
              $('#main-form input, #main-form button').removeAttr('disabled');
              $('#ajax-icon').removeClass('fa fa-spin fa-refresh').addClass('fa fa-save');
          }

        });

        return false;
    });
});
