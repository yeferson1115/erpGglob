@extends('layouts.app')

@section('content')
<div class="container">
  <h3>Reporte de Pedidos</h3>

  <!--<div class="d-flex justify-content-between mb-3">
    <div>
      <a href="{{ route('reports.orders.cancelled') }}" class="btn btn-outline-danger">
        <i class="fa-solid fa-ban"></i> Ver Órdenes Anuladas
      </a>
    </div>
  </div>-->

  <div class="card p-3 mb-3">
    <div class="row g-2">
      <div class="col-md-3">
        <label>Fecha inicio</label>
        <input type="date" id="startDate" class="form-control">
      </div>
      <div class="col-md-3">
        <label>Fecha fin</label>
        <input type="date" id="endDate" class="form-control">
      </div>
      <div class="col-md-3">
        <label>Buscar (ID, mesa, cliente)</label>
        <input type="text" id="searchOrders" class="form-control" placeholder="Ej: 102, mesa 5, Juan">
      </div>
      <div class="col-md-3 d-flex align-items-end">
        <!--<div class="form-check me-2">
          <input type="checkbox" id="includeCancelled" class="form-check-input">
          <label for="includeCancelled" class="form-check-label">Incluir anuladas</label>
        </div>-->
        <button id="btnFilterOrders" class="btn btn-primary me-2">Filtrar</button>
        <button id="btnClearFilters" class="btn btn-light">Limpiar</button>
      </div>
    </div>
  </div>

  <div class="card p-3">
    <table class="table table-sm">
      <thead>
        <tr>
          <th>ID</th>
          <th>Fecha / Hora</th>
          <th>Cliente</th>
          <th>Monto</th>
          <th>Método de Pago</th>
          <th>Tipo</th>
          <th>Mesa</th>
          <th>Estado</th>
          <th>Pagado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody id="ordersTableBody">
        {{-- AJAX cargará rows --}}
      </tbody>
    </table>

    <div id="ordersPagination"></div>
  </div>
</div>

<!-- Modal para anular orden -->
<div class="modal fade" id="cancelOrderModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Anular Orden</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="cancelOrderId">
        <div class="mb-3">
          <label for="cancelReason" class="form-label">Motivo de anulación *</label>
          <textarea id="cancelReason" class="form-control" rows="3" 
                    placeholder="Ej: Error en el pedido, cliente canceló, etc."></textarea>
        </div>
        <div class="form-check">
          <input type="checkbox" class="form-check-input" id="cancelConfirm">
          <label class="form-check-label" for="cancelConfirm">
            Confirmo que deseo anular esta orden
          </label>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" id="btnConfirmCancel">Anular Orden</button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
$(function(){
  function loadOrders(page = 1) {
    $.get('{{ route("reports.orders.data") }}', {
      start_date: $('#startDate').val(),
      end_date: $('#endDate').val(),
      search: $('#searchOrders').val(),
      include_cancelled: $('#includeCancelled').is(':checked') ? 1 : 0,
      page: page
    }, function(res){
      $('#ordersTableBody').html(res.html);
      $('#ordersPagination').html(res.pagination);
      
      if (res.summary) {
        if (!$('#ordersSummary').length) {
          $('#ordersPagination').before('<div id="ordersSummary" class="mb-2 text-muted small"></div>');
        }
        $('#ordersSummary').html(res.summary);
      }
    });
  }

  $('#btnFilterOrders').on('click', function(){ loadOrders(); });
  $('#btnClearFilters').on('click', function(){
    $('#startDate,#endDate,#searchOrders').val('');
    $('#includeCancelled').prop('checked', false);
    loadOrders();
  });
  
  $('#includeCancelled').on('change', function(){ loadOrders(); });

  // paginación
  $(document).on('click', '#ordersPagination a', function(e){
    e.preventDefault();
    const page = $(this).attr('href').split('page=')[1];
    loadOrders(page);
  });

  // cargar inicialmente
  loadOrders();

  // Anular orden
  $(document).on('click', '.btn-cancel-order', function(){
    const orderId = $(this).data('id');
    $('#cancelOrderId').val(orderId);
    $('#cancelReason').val('');
    $('#cancelConfirm').prop('checked', false);
    new bootstrap.Modal('#cancelOrderModal').show();
  });

$('#btnConfirmCancel').on('click', async function(){
    const orderId = $('#cancelOrderId').val();
    const reason = $('#cancelReason').val().trim();
    const confirmChecked = $('#cancelConfirm').is(':checked');

    // Validación
    if (!reason) {
        await Swal.fire('Atención', 'Debe ingresar un motivo de anulación', 'warning');
        return;
    }

    if (!confirmChecked) {
        await Swal.fire('Atención', 'Debe confirmar la anulación', 'info');
        return;
    }

    // Mostrar carga mientras se procesa
    Swal.fire({
        title: 'Anulando orden...',
        text: 'Por favor espere',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        const response = await $.post(`/orders/${orderId}/cancel`, {
            reason: reason,
            confirm: confirmChecked,
            _token: '{{ csrf_token() }}'
        });

        // Cerrar SweetAlert de carga
        Swal.close();

        if (response.success) {
            // Cerrar modal de Bootstrap
            const modal = bootstrap.Modal.getInstance(document.getElementById('cancelOrderModal'));
            modal.hide();
            
            // Resetear formulario
            $('#cancelReason').val('');
            $('#cancelConfirm').prop('checked', false);
            
            // Mostrar éxito y recargar
            await Swal.fire({
                icon: 'success',
                title: '¡Anulado!',
                text: response.message,
                timer: 1500,
                showConfirmButton: false
            });
            
            loadOrders();
            
        } else {
            await Swal.fire('Error', response.message || 'Error al anular la orden', 'error');
        }
        
    } catch (error) {
        Swal.close();
        await Swal.fire('Error', error.responseJSON?.message || 'Error en el servidor', 'error');
    }
});

  // Restaurar orden anulada
 // Restaurar orden anulada - USING SweetAlert2 for confirmation
$(document).on('click', '.btn-restore-order', function(){
    const orderId = $(this).data('id');

    // 3. Replace the native confirm() with a Promise-based SweetAlert2
    Swal.fire({
        title: '¿Restaurar esta orden?',
        text: "La orden volverá a estar activa.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754', // Bootstrap success color
        cancelButtonColor: '#6c757d',  // Bootstrap secondary color
        confirmButtonText: 'Sí, restaurar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.post(`/orders/${orderId}/restore`, {
                _token: '{{ csrf_token() }}'
            })
            .done(function(res){
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Restaurada',
                        text: res.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                    loadOrders(); // Recargar la tabla
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            })
            .fail(function(xhr){
                Swal.fire('Error', 'Error en el servidor', 'error');
            });
        }
    });
});
});
</script>
@endpush