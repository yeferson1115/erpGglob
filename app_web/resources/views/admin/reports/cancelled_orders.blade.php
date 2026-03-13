@extends('layouts.app')

@section('content')
<div class="container">
  <h3>Órdenes Anuladas</h3>

  <div class="d-flex justify-content-between mb-3">
    <div>
      <a href="{{ route('reports.orders.index') }}" class="btn btn-outline-primary">
        <i class="fa-solid fa-arrow-left"></i> Volver a Reportes
      </a>
    </div>
  </div>

  <div class="card p-3 mb-3">
    <div class="row g-2">
      <div class="col-md-3">
        <label>Fecha inicio anulación</label>
        <input type="date" id="startDate" class="form-control">
      </div>
      <div class="col-md-3">
        <label>Fecha fin anulación</label>
        <input type="date" id="endDate" class="form-control">
      </div>
      <div class="col-md-4">
        <label>Buscar (ID, motivo)</label>
        <input type="text" id="searchCancelled" class="form-control" placeholder="Ej: 102, error, canceló">
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <button id="btnFilterCancelled" class="btn btn-primary me-2">Filtrar</button>
        <button id="btnClearCancelled" class="btn btn-light">Limpiar</button>
      </div>
    </div>
  </div>

  <div class="card p-3">
    <table class="table table-sm">
      <thead>
        <tr>
          <th>ID Orden</th>
          <th>Fecha Creación</th>
          <th>Fecha Anulación</th>
          <th>Cliente</th>
          <th>Monto</th>
          <th>Tipo</th>
          <th>Motivo</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody id="cancelledTableBody">
        @foreach($orders as $order)
        <tr>
          <td>{{ $order->id }}</td>
          <td>{{ $order->created_at->format('d/m/Y H:i') }}</td>
          <td>{{ $order->cancelled_at->format('d/m/Y H:i') }}</td>
          <td>{{ $order->customer ? $order->customer->name : ($order->customer_name ?: 'Cliente general') }}</td>
          <td>{{ number_format($order->total, 2) }}</td>
          <td>{{ ucfirst($order->type) }}</td>
          <td>{{ $order->cancelled_reason }}</td>
          <td>
            <button class="btn btn-sm btn-success btn-restore-order" data-id="{{ $order->id }}">
              <i class="fa-solid fa-rotate-left"></i> Restaurar
            </button>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>

    <div class="mt-3">
      {{ $orders->links() }}
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
$(function(){
  function loadCancelledOrders(page = 1) {
    $.get('{{ route("reports.orders.cancelled") }}', {
      start_date: $('#startDate').val(),
      end_date: $('#endDate').val(),
      search: $('#searchCancelled').val(),
      page: page
    }, function(res){
      $('#cancelledTableBody').html(res.html);
      $('.pagination').html(res.pagination);
    });
  }

  $('#btnFilterCancelled').on('click', function(){ 
    loadCancelledOrders(); 
  });
  
  $('#btnClearCancelled').on('click', function(){
    $('#startDate,#endDate,#searchCancelled').val('');
    loadCancelledOrders();
  });

  // Restaurar orden anulada
  $(document).on('click', '.btn-restore-order', function(){
    const orderId = $(this).data('id');
    
    if (!confirm('¿Restaurar esta orden anulada?')) return;

    $.post(`/orders/${orderId}/restore`, {
      _token: '{{ csrf_token() }}'
    }, function(res){
      if (res.success) {
        alert(res.message);
        loadCancelledOrders(); // Recargar la tabla
      } else {
        alert(res.message || 'Error al restaurar la orden');
      }
    });
  });
});
</script>
@endpush