@extends('layouts.app')

@section('content')

<div class="card mb-6" style="padding: 20px;">   
  <div class="d-flex justify-content-between mb-3">
    <h3>Mesas / Pedidos</h3>
    <div>
      <a href="{{ route('orders.takeaway') }}" class="btn btn-success">Pedido para llevar</a>
      <a href="{{ route('orders.delivery.form') }}" class="btn btn-warning">Pedido a domicilio</a>
    </div>
  </div>

  <input id="searchTable" class="form-control mb-3" placeholder="Buscar mesa por nombre o número">

  <div class="row" id="tables-container">
    @foreach($tables as $t)
      <div class="col-md-3 mb-3">
        <div class="card table-card p-3 text-center" data-name="{{ strtolower($t->name) }}">
          <h4>Mesa {{ $t->name }}</h4>
          <!--<p>Asientos: {{ $t->seats }}</p>-->
        <img style="width: 110px;display: block;margin: 0 auto;" src="{{ asset('imagenes/mesa.png') }}"/>
          @if($t->status == 'ocupada')
            <span class="badge bg-danger">Ocupada</span>
          @elseif($t->status == 'reservada')
            <span class="badge bg-warning text-dark">Reservada</span>
          @else
            <span class="badge bg-success">Disponible</span>
          @endif

          <div class="mt-2">
            <a href="{{ route('orders.openTable', $t->id) }}" class="btn btn-light btn-sm">Abrir</a>
            <button class="btn btn-outline-secondary btn-sm reserve-btn" data-id="{{ $t->id }}">Reservar</button>
          </div>
        </div>
      </div>
    @endforeach
  </div>
</div>
@endsection

@push('scripts')
<script>
$(function(){
  $('#searchTable').on('keyup', function(){
    let q = $(this).val().toLowerCase();
    $('.table-card').each(function(){
      $(this).toggle($(this).data('name').includes(q));
    });
  });

  // reservar mesa AJAX
  $(document).on('click', '.reserve-btn', function(){
    const id = $(this).data('id');
    const btn = $(this);
    Swal.fire({
      title: 'Reservar mesa?',
      showCancelButton:true,
      confirmButtonText:'Sí, reservar'
    }).then((res)=>{
      if(res.isConfirmed){
        $.post('{{ route("orders.changeStatus") }}', { table_id: id, status: 'reservada', _token:'{{ csrf_token() }}' }, function(r){
          Swal.fire('Reservada','Mesa en estado reservado','success').then(()=> location.reload());
        });
      }
    });
  });

});
</script>
@endpush
