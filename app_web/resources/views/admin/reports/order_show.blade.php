@extends('layouts.app')

@section('content')
<div class="container">
  <h4>Pedido #{{ $order->id }} — {{ ucfirst($order->type) }}</h4>

  <div class="row">
    <div class="col-md-8">
      <div class="card p-3 mb-3">
        <h6>Items</h6>
        <div id="orderItemsList">
          @foreach($order->items as $it)
            <div class="mb-2 d-flex justify-content-between align-items-start" data-id="{{ $it->id }}">
              <div>
                <strong>{{ $it->product->name }}</strong>
                <div class="small">Nota: {{ $it->note }}</div>
                @if($it->addons->count())
                  <ul class="small">
                    @foreach($it->addons as $ad)
                      <li>{{ $ad->addon->name }} (+{{ number_format($ad->addon->price,2) }})</li>
                    @endforeach
                  </ul>
                @endif
              </div>

              <div class="text-end">
                <div>Qty: <span class="badge bg-light text-dark">{{ $it->quantity }}</span></div>
                <div class="item-subtotal" data-value="{{ $it->price * $it->quantity + $it->addons->sum(fn($a) => $a->addon->price) * $it->quantity }}">
                  Subtotal: {{ number_format($it->price * $it->quantity + $it->addons->sum(fn($a) => $a->addon->price) * $it->quantity,2) }}
                </div>
                <div class="mt-2">
                  <button class="btn btn-sm btn-outline-secondary btn-edit-item" data-id="{{ $it->id }}"><i class="fa-solid fa-pen-to-square"></i></button>
                  <button class="btn btn-sm btn-danger btn-delete-item" data-id="{{ $it->id }}"><i class="fa-solid fa-trash-can"></i></button>
                </div>
              </div>
            </div>
            <hr>
          @endforeach
        </div>

        <div class="mb-3">
          <label>Agregar producto</label>
          <select id="addProductSelect" class="form-control selectsearh" data-live-search="true">
            <option value="">-- seleccionar producto --</option>
            @foreach(\App\Models\Product::orderBy('name')->get() as $p)
              <option value="{{ $p->id }}" data-price="{{ $p->price }}">{{ $p->name }} — {{ number_format($p->price,2) }}</option>
            @endforeach
          </select>
          <button id="btnAddProductToOrder" class="btn btn-primary mt-2">Agregar</button>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card p-3 mb-3">
        <h6>Datos Pedido</h6>
        @if($order->type === 'domicilio')
        <div class="mb-2">
          <label>Cliente (si aplica)</label>
          <input id="r_customer_name" class="form-control" value="{{ $order->customer_name }}">
        </div>
        <div class="mb-2">
          <label>Teléfono</label>
          <input id="r_customer_phone" class="form-control" value="{{ $order->customer_phone }}">
        </div>
        <div class="mb-2">
          <label>Dirección</label>
          <input id="r_customer_address" class="form-control" value="{{ $order->customer_address }}">
        </div>
        @else
        <div class="d-flex gap-2 mb-2">
          <select id="orderCustomer" class="form-control selectsearh" style="flex: 1;" data-live-search="true">
            <option value="">Público general</option>
            @foreach(\App\Models\Customer::orderBy('name')->get() as $c)
              <option value="{{ $c->id }}" @if($order->customer_id == $c->id) selected @endif>
                {{ $c->name }} - {{ $c->document }}
              </option>
            @endforeach
          </select>
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newCustomerModal">
            <i class="fa-solid fa-user-plus"></i>
          </button>
        </div>
        @endif
        
        @if($order->type === 'domicilio')
        <div class="mb-2">
          <label>Costo envío</label>
          <input id="r_shipping_cost" type="number" step="0.01" min="0" class="form-control" value="{{ $order->shipping_cost ?? 0 }}">
          <button id="btnSaveShipping" class="btn btn-sm btn-outline-primary mt-2">Guardar envío</button>
        </div>
        @endif

        <div class="mb-2">
          <label>Método de Pago</label>
          <select id="r_payment_method" class="form-control">
            <option value="Efectivo" {{ $order->payment_method == 'Efectivo' ? 'selected' : '' }}>Efectivo</option>
            <option value="Transferencia" {{ $order->payment_method == 'Transferencia' ? 'selected' : '' }}>Transferencia</option>  
            <option value="Tarjeta de Crédito" {{ $order->payment_method == 'Tarjeta de Crédito' ? 'selected' : '' }}>Tarjeta de Crédito</option>
            <option value="Tarjeta Débito" {{ $order->payment_method == 'Tarjeta Débito' ? 'selected' : '' }}>Tarjeta Débito</option>
            <option value="Contra Entrega" {{ $order->payment_method == 'Contra Entrega' ? 'selected' : '' }}>Contra Entrega</option>         
            @if(!$order->payment_method)
              <option value="" selected disabled>Seleccionar método</option>
            @endif
          </select>
        </div>

        <div class="mb-2">
          <label>Nota global</label>
          <textarea id="r_note" class="form-control">{{ $order->note }}</textarea>
        </div>

        <div class="mb-2">
          <label>Pagado</label>
          <div>
            <input type="checkbox" id="r_paid" {{ $order->paid ? 'checked' : '' }}>
            <label for="r_paid" class="ms-1">Pagado</label>
          </div>
        </div>

        {{-- Mostrar subtotal, envío y total por separado --}}
        <div class="mb-2">
          <div class="d-flex justify-content-between">
            <span>Subtotal productos:</span>
            <span id="r_subtotal">{{ number_format($order->total, 2) }}</span>
          </div>
          <div class="d-flex justify-content-between">
            <span>Envío:</span>
            <span id="r_shipping_display">{{ number_format($order->shipping_cost ?? 0, 2) }}</span>
          </div>
          <hr class="my-2">
          <div class="d-flex justify-content-between">
            <strong>Total:</strong>
            <strong id="r_total">{{ number_format($order->total + ($order->shipping_cost ?? 0), 2) }}</strong>
          </div>
        </div>

        <div class="mt-3">
          <button id="btnSaveOrderData" class="btn btn-success mb-1">Guardar cambios</button>
          <a href="{{ route('orders.ticket', $order->id) }}" target="_blank" class="btn btn-secondary">Imprimir ticket</a>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Modal editar item con addons --}}
<div class="modal fade" id="editItemModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form id="editItemForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5>Editar Producto</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" id="edit_item_id">
          <div class="mb-3">
            <label><strong>Cantidad</strong></label>
            <input id="edit_quantity" type="number" class="form-control" min="1">
          </div>
          <div class="mb-3">
            <label><strong>Adiciones / Extras</strong></label>
            <div id="edit_addons_list"></div>
          </div>
          <div class="mb-3">
            <label><strong>Nota</strong></label>
            <textarea id="edit_note" class="form-control"></textarea>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary" id="btnSaveItemEdit">Guardar cambios</button>
        </div>
      </div>
    </form>
  </div>
</div>

{{-- Modal nuevo cliente --}}
<div class="modal fade" id="newCustomerModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
    
      <div class="modal-header">
        <h5 class="modal-title">Nuevo Cliente</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <input id="cName" class="form-control mb-2" placeholder="Nombre">
        <input id="cDocument" class="form-control mb-2" placeholder="Documento">
        <input id="cPhone" class="form-control mb-2" placeholder="Teléfono">
        <input id="cEmail" class="form-control mb-2" placeholder="Correo">
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" id="btnSaveCustomer">Guardar</button>
      </div>

    </div>
  </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function(){
  const orderId = {{ $order->id }};
  const initialSubtotal = parseFloat('{{ $order->total }}');
  $.ajaxSetup({ headers: {'X-CSRF-TOKEN':'{{ csrf_token() }}'} });

  // Función para recalcular el total mostrado
  function updateDisplayedTotal() {
    const shippingCost = parseFloat($('#r_shipping_cost').val()) || 0;
    const total = initialSubtotal + shippingCost;
    
    // Actualizar visualmente
    $('#r_shipping_display').text(shippingCost.toFixed(2));
    $('#r_total').text(total.toFixed(2));
    
    return total;
  }

  // Función para actualizar cliente en la orden
  function updateOrderCustomer(customerId = null) {
    const customerIdToSend = customerId || $('#orderCustomer').val();
    
    $.post('{{ route("orders.updateCustomer") }}', {
      order_id: orderId,
      customer_id: customerIdToSend
    }, function(res) {
      _alertGeneric('success','Muy Bien!','Cliente creado correctamente',1);
    });
  }

  // Guardar cliente rápido
  $('#btnSaveCustomer').on('click', function () {
    const name = $('#cName').val();
    const document = $('#cDocument').val();
    const phone = $('#cPhone').val();
    const email = $('#cEmail').val();

    if (!name.trim()) {
      _alertGeneric('warning', 'Atención', 'El nombre es obligatorio');
      return;
    }

    $.post('{{ route("customers.quickSave") }}', {
      name: name,
      document: document,
      phone: phone,
      email: email
    }, function (res) {
      if (res.success) {
        // Crear y añadir la opción al select
        const select2Element = $('#orderCustomer');
        var optionText = res.customer.document ? 
                `${res.customer.name} - ${res.customer.document}` : 
                res.customer.name;

            // Crear y añadir la opción
            var newOption = $('<option>', {
                value: res.customer.id,
                text: optionText,
                selected: true
            });

            select2Element.append(newOption);
            
            // Actualizar Select2
            select2Element.trigger('change.select2');

        // Guardar en la orden
        updateOrderCustomer(res.customer.id);

        // Mostrar mensaje y cerrar modal        
        
        // Limpiar campos y cerrar modal
        $('#cName, #cDocument, #cPhone, #cEmail').val('');
        $('#newCustomerModal').modal('hide');
      }
    }).fail(function() {
      _alertGeneric('error', 'Error', 'No se pudo crear el cliente');
    });
  });

  // Cuando selecciona un cliente
  $('#orderCustomer').on('change', function(){
    updateOrderCustomer();
  });

  // Actualizar total automáticamente al cambiar el envío
  $('#r_shipping_cost').on('change keyup', function(){
    updateDisplayedTotal();
  });

  // Eliminar item
  $(document).on('click','.btn-delete-item', function(){
    const id = $(this).data('id');
    if(!confirm('Eliminar item?')) return;
    $.post('{{ route("orders.deleteItem") }}', { id: id }, function(res){
      if(res.success) location.reload();
    });
  });

  // Editar item -> abrir modal con datos actuales
  $(document).on('click','.btn-edit-item', function(){
    const id = $(this).data('id');

    $.get('/order-items/' + id, function(res){
      $('#edit_item_id').val(res.id);
      $('#edit_quantity').val(res.quantity);
      $('#edit_note').val(res.note);

      let html = "";
      res.addons_available.forEach(a => {
        let checked = res.addons_selected.includes(a.id) ? 'checked' : '';
        html += `
          <div class="form-check mb-2">
            <input type="checkbox" class="form-check-input edit-addon-check"
              value="${a.id}" ${checked}>
            <label class="form-check-label">
              ${a.name} (+${a.price})
            </label>
          </div>`;
      });

      $('#edit_addons_list').html(html);
      new bootstrap.Modal('#editItemModal').show();
    });
  });

  // Guardar cambios item
  $('#btnSaveItemEdit').on('click', function(e){
    e.preventDefault();

    let addons = [];
    $('.edit-addon-check:checked').each(function(){
      addons.push($(this).val());
    });

    $.post('{{ route("orders.updateItem") }}', {
      id: $('#edit_item_id').val(),
      quantity: $('#edit_quantity').val(),
      note: $('#edit_note').val(),
      addons: addons
    }, function(res){
      if(res.success) location.reload();
    });
  });

  // Agregar producto simple desde reporte
  $('#btnAddProductToOrder').on('click', function(){
    const pid = $('#addProductSelect').val();
    if(!pid) return alert('Selecciona producto');
    $.post('{{ route("orders.addItem") }}', { 
      order_id: orderId, 
      product_id: pid, 
      quantity: 1 
    }, function(res){
      if(res.success) location.reload();
    });
  });

  // Guardar envio en la base de datos
  $('#btnSaveShipping').on('click', function(){
    const shippingCost = parseFloat($('#r_shipping_cost').val()) || 0;
    
    $.post('{{ route("reports.orders.shipping", $order->id) }}', { 
      shipping_cost: shippingCost 
    }, function(res){
      if(res.success) {
        // Ya se actualizó visualmente con updateDisplayedTotal()
        // Solo mostrar mensaje de éxito
        _alertGeneric('success','Información','Valor de envío guardado');        
      }
    });
  });

  // Guardar datos globales (incluyendo método de pago)
  $('#btnSaveOrderData').on('click', function(){
    const data = {
      note: $('#r_note').val(),
      payment_method: $('#r_payment_method').val(),
      paid: $('#r_paid').is(':checked') ? 1 : 0
    };

    // Agregar campos según el tipo de orden
    @if($order->type === 'domicilio')
      data.customer_name = $('#r_customer_name').val();
      data.customer_phone = $('#r_customer_phone').val();
      data.customer_address = $('#r_customer_address').val();
    @else
      data.customer_id = $('#orderCustomer').val();
    @endif

    $.post('{{ route("reports.orders.update", $order->id) }}', data, function(res){
      if(res.success) {
        // Actualizar el total también en caso de que haya cambiado
        updateDisplayedTotal();
        _alertGeneric('success','Información','Datos guardados',1);
      }
    });
  });

  // Función de toast para feedback
  function showToast(message, type = 'info') {
    const Toast = Swal.mixin({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 2000,
      timerProgressBar: true,
      didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer);
        toast.addEventListener('mouseleave', Swal.resumeTimer);
      }
    });
    
    Toast.fire({
      icon: type,
      title: message
    });
  }

  // Función _alertGeneric (si no la tienes definida)
 

  // Inicializar el total mostrado
  updateDisplayedTotal();
  
  // Inicializar Select2 para búsquedas

});
</script>
@endpush