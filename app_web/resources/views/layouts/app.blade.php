<!doctype html>

<html
  lang="en"
  class=" layout-navbar-fixed layout-menu-fixed layout-compact "
  dir="ltr"
  data-skin="default"
  data-bs-theme="light"
  data-assets-path="{{ asset('assets/') }}"
  data-template="horizontal-menu-template">
  <head>
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />
    <meta name="robots" content="noindex, nofollow" />
    <title>{{ config('app.name', 'MAria Bonita') }}</title>

    <meta name="description" content="" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/favicon/favicon.ico') }}" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&ampdisplay=swap"
      rel="stylesheet" />

    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/iconify-icons.css') }}" />

    <script src="{{ asset('assets/vendor/libs/@algolia/autocomplete-js.js') }}"></script>

    <!-- Core CSS -->
    <!-- build:css assets/vendor/css/theme.css  -->

    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/node-waves/node-waves.css') }}" />

    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/pickr/pickr-themes.css') }}" />

    <link rel="stylesheet" href="{{ asset('assets/vendor/css/core.css') }}" />
    <link rel="stylesheet" href="{{ asset('assets/css/demo.css') }}" />

    <!-- Vendors CSS -->

    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css') }}" />

    <!-- endbuild -->

    
    <link rel="stylesheet" href="{{ asset('assets/vendor/fonts/flag-icons.css') }}" />

    <!-- Page CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/css/bootstrap-select.min.css">
    <!-- Helpers -->
    <script src="{{ asset('assets/vendor/js/helpers.js') }}"></script>
    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->

    <!--? Template customizer: To hide customizer set displayCustomizer value false in config.js.  -->
    

    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
    <style>
      .layout-navbar{
            z-index: 100 !important;
      }
    </style>
    <script src="{{ asset('assets/js/config.js') }}"></script>
     @stack('styles')
  </head>

  <body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-navbar-full layout-horizontal layout-without-menu">
      <div class="layout-container">
        <!-- Navbar -->

       @include('layouts.header')

        <!-- / Navbar -->

        <!-- Layout container -->
        <div class="layout-page">
          <!-- Content wrapper -->
          <div class="content-wrapper">
            <!-- Menu -->
             @include('layouts.navigation')            
            <!-- / Menu -->

            <!-- Content -->
            <div class="container-xxl flex-grow-1 container-p-y">
              <div class="row g-6">   
                    @if(session('success'))
                      <div class="alert alert-success">{{ session('success') }}</div>
                    @endif             
                   @yield('content')
              </div>
            </div>
            <!--/ Content -->

            <!-- Footer -->
            <footer class="content-footer footer bg-footer-theme">
              <div class="container-xxl">
                <div
                  class="footer-container d-flex align-items-center justify-content-between py-4 flex-md-row flex-column">
                  <div class="text-body">
                    &#169;
                    <script>
                      document.write(new Date().getFullYear());
                    </script>
                    , made with ❤️ by <a href="https://yefersonsossa.com/" target="_blank" class="footer-link">yefersonsossa.com</a>
                  </div>
                  
                </div>
              </div>
            </footer>
            <!-- / Footer -->

            <div class="content-backdrop fade"></div>
          </div>
          <!--/ Content wrapper -->
        </div>

        <!--/ Layout container -->
      </div>
    </div>

    <!-- Overlay -->
    <div class="layout-overlay layout-menu-toggle"></div>

    <!-- Drag Target Area To SlideIn Menu On Small Screens -->
    <div class="drag-target"></div>

    <!--/ Layout wrapper -->

    <!-- Core JS -->
    <!-- build:js assets/vendor/js/theme.js  -->

    <script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>

    <script src="{{ asset('assets/vendor/libs/popper/popper.js') }}"></script>
    <script src="{{ asset('assets/vendor/js/bootstrap.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/node-waves/node-waves.js') }}"></script>

    <script src="{{ asset('assets/vendor/libs/pickr/pickr.js') }}"></script>

    <script src="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>

    <script src="{{ asset('assets/vendor/libs/hammer/hammer.js') }}"></script>

    <script src="{{ asset('assets/vendor/libs/i18n/i18n.js') }}"></script>

    <script src="{{ asset('assets/vendor/js/menu.js') }}"></script>

    <!-- endbuild -->

    <!-- Vendors JS -->
   

    <!-- Main JS -->

    <script src="{{ asset('assets/js/main.js') }}"></script>

    <!-- Page JS -->
    <script src="{{ asset('assets/js/dashboards-crm.js') }}"></script>

       <script src="https://cdn.jsdelivr.net/npm/sweetalert2@9"></script>

    <!-- DataTables -->
    <script src="{{ asset('assets/plugins/DataTables/datatables.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/DataTables/Buttons-1.7.1/js/dataTables.buttons.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/DataTables/JSZip-2.5.0/jszip.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/DataTables/pdfmake-0.1.36/pdfmake.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/DataTables/pdfmake-0.1.36/vfs_fonts.js') }}"></script>
    <script src="{{ asset('assets/plugins/DataTables/Buttons-1.7.1/js/buttons.html5.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/DataTables/Buttons-1.7.1/js/buttons.print.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/DataTables/owGroup-1.1.3/js/dataTables.rowGroup.min.js') }}"></script>
    <script src="https://cdn.datatables.net/datetime/1.2.0/js/dataTables.dateTime.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.17.1/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/js/bootstrap-select.min.js"></script>
<script>
      function _alertGeneric(type, title, text, reload = null, autoClose = false, timer = 3000) {
        Swal.fire({
          icon: type,
          title: title,
          text: text,
          confirmButtonText: 'Aceptar',
          timer: autoClose ? timer : null
        }).then((result) => {
          if (reload != '' && reload != null && reload != 1) {
            window.location.href = reload;
          }
          if (reload === 1) location.reload();
          if (reload === 2) window.history.go(-1);
        });
      }

      $(document).ready(function() {
        $('#datatables').DataTable({
          language: {
            sProcessing: "Procesando...",
            sLengthMenu: "Mostrar _MENU_ registros",
            sZeroRecords: "No se encontraron resultados",
            sEmptyTable: "Ningún dato disponible en esta tabla",
            sInfo: "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
            sInfoEmpty: "Mostrando registros del 0 al 0 de un total de 0 registros",
            sInfoFiltered: "(filtrado de un total de _MAX_ registros)",
            sSearch: "Buscar:",
            oPaginate: {
              sFirst: "Primero",
              sLast: "Último",
              sNext: "Siguiente",
              sPrevious: "Anterior"
            },
          },
          destroy: true,
          dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6 d-flex justify-content-center justify-content-md-end"f>>t<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
        });
      });

      $(document).ajaxStart(() => $("#preloader").fadeIn());
      $(document).ajaxStop(() => $("#preloader").fadeOut('slow'));

      window.onload = function() {
      // Ocultar el preloader con un suave efecto de fadeOut
      $('#preloader').fadeOut('slow');
    };

$(document).ready(function() {
        $('.selectsearh').selectpicker();
    });
$(document).on('click', '.btnDelete', function(e) {
    e.preventDefault();

    let id = $(this).data('id');
    let url = $(this).data('url');

    Swal.fire({
        title: '¿Estás seguro?',
        text: "¡Esta acción no se puede deshacer!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {

        if (result.isConfirmed) {

            $.ajax({
                url: url,
                type: 'POST',
                data: {
                    _token: "{{ csrf_token() }}",
                    _method: "DELETE"
                },
                success: function(res) {

                    Swal.fire(
                        'Eliminado',
                        res.message,
                        'success'
                    );

                    // Si quieres recargar la tabla
                    setTimeout(() => location.reload(), 800);
                },
                error: function(xhr) {
                    Swal.fire(
                        'Error',
                        'No se pudo eliminar el registro.',
                        'error'
                    );
                }
            });

        }
    });
});

$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': '{{ csrf_token() }}'
    }
});
</script>


    

    @stack('scripts')

  </body>
</html>