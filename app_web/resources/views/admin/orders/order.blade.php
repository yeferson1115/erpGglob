@extends('layouts.app')

<style>
.qty-input {
    -moz-appearance: textfield;
}

/* Ocultar flechas en navegadores webkit */
.qty-input::-webkit-outer-spin-button,
.qty-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.order-item.updating {
    opacity: 0.7;
    background-color: #f8f9fa;
}

.order-item.updating .qty-input {
    background-color: #e9ecef;
}

/* Estilo para mostrar que se está guardando */
#order-note.is-saving {
    border-color: #0d6efd;
    background-color: rgba(13, 110, 253, 0.05);
}

/* Indicador visual de guardado */
.note-saving-indicator {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 12px;
    color: #6c757d;
    opacity: 0;
    transition: opacity 0.3s;
}

#order-note.is-saving + .note-saving-indicator {
    opacity: 1;
}
</style>

@section('content')
<div class="container">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      @if(isset($table))
        <h4>Pedido - Mesa {{ $table->name }}</h4>
      @else
        <h4>Pedido #{{ $order->id }} ({{ $order->type }})</h4>
      @endif
      <small class="text-muted">Estado: <span id="order-status">{{ $order->status }}</span></small>
    </div>

   
  </div>

  <div class="row">
    <!-- Productos (lado izquierdo) -->
    <div class="col-md-6">
      <div class="card p-3 mb-3">
        <h6>Productos</h6>
        <div class="row" style="max-height:60vh;overflow:auto">
            <div class="mb-3 d-flex gap-2">

                <select id="filterCategory" class="form-select" style="width:200px">
                    <option value="all">Todas las categorías</option>
                    @foreach(\App\Models\Category::orderBy('name')->get() as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>

                <input type="text" id="filterSearch" 
                    class="form-control" 
                    placeholder="Buscar producto..." 
                    style="width:200px">

            </div>

            <div id="productsList"></div>

            <div id="paginationLinks" class="mt-2">
                {{ $products->links() }}
            </div>

         
        </div>
      </div>
    </div>

    <!-- Pedido (lado derecho) -->
    <div class="col-md-6">
      <div class="card p-3 mb-3">
        <div class="card p-3 mb-3">
    <h6>Cliente</h6>

        <div class="d-flex gap-2 mb-2">
            <select id="orderCustomer" class="form-control selectsearh" data-live-search="true">
                <option value="">Público general</option>
                @foreach(\App\Models\Customer::orderBy('name')->get() as $c)
                    <option value="{{ $c->id }}" @if($order->customer_id == $c->id) selected @endif>
                        {{ $c->name }} - {{ $c->document }}
                    </option>
                @endforeach
            </select>

            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newCustomerModal">
                Nuevo
            </button>
        </div>
    </div>


        <h6>Pedido</h6>
        <div id="order-items">
          @foreach($order->items as $it)
           {{-- Reemplaza esta sección en tu vista --}}
              <div class="order-item mb-2" data-id="{{ $it->id }}">
                  <div class="d-flex justify-content-between">
                      <div>
                          <strong>{{ $it->product->name }}</strong>
                          
                          @if($it->addons->count())
                              <ul class="small text-muted mb-1">
                                  @foreach($it->addons as $ad)
                                      <li>{{ $ad->addon->name }} (+{{ $ad->addon->price }})</li>
                                  @endforeach
                              </ul>
                          @endif
                          
                          @if($it->note)
                              <div class="small">Nota: {{ $it->note }}</div>
                          @endif
                      </div>
                      
                      <div class="text-end">
                          <div class="d-flex align-items-center mb-2">
                              <button class="btn btn-sm btn-outline-secondary btn-decrease" data-id="{{ $it->id }}">-</button>
                              <input type="number" 
                                    class="form-control form-control-sm qty-input mx-2" 
                                    data-id="{{ $it->id }}"
                                    value="{{ $it->quantity }}"
                                    min="1"
                                    max="999"
                                    style="width: 70px; text-align: center;">
                              <button class="btn btn-sm btn-outline-secondary btn-increase" data-id="{{ $it->id }}">+</button>
                          </div>
                          
                          <!--<div class="small mt-1 item-total" data-id="{{ $it->id }}">
                              {{ number_format($it->price * $it->quantity + $it->addons->sum(fn($a) => $a->addon->price) * $it->quantity,2) }}
                          </div>-->
                          
                          <button class="btn btn-sm btn-danger btn-remove mt-1" data-id="{{ $it->id }}">Quitar</button>
                      </div>
                  </div>
              </div> 
          @endforeach
        </div>

        <hr>
        <div class="d-flex justify-content-between">
          <strong>Total</strong>
          <strong id="order-total">{{ number_format($order->total) }}</strong>
        </div>
         <div class="mt-2">
          <label>Metodo de Pago</label>
          <select id="payment-method" class="form-select">
            <option value="Efectivo">Efectivo</option>
            <option value="Transferencia">Transferencia</option>
            <option value="Tarjeta de Crédito">Tarjeta de Crédito</option>
            <option value="Tarjeta Débito">Tarjeta Débito</option>
            <option value="Contra Entrega">Contra Entrega</option>
          </select>
        </div>
        
        <div class="mt-2">
          <label>Nota general</label>
          <textarea id="order-note" class="form-control">{{ $order->note }}</textarea>
        </div>
        @if($order->type == 'domicilio')
        <div class="card p-3 mt-3">
            <h6>Datos del domicilio (obligatorios)</h6>
            <input id="d_name" class="form-control mb-2" name="customer_name" placeholder="Nombre del cliente" 
                value="{{ $order->customer_name }}">

            <input id="d_phone" class="form-control mb-2" name="customer_phone" placeholder="Teléfono"
                value="{{ $order->customer_phone }}">

            <input id="d_address" class="form-control mb-2" name="customer_address" placeholder="Dirección"
                value="{{ $order->customer_address }}">
        </div>
        @endif

      </div>

      <div class="card p-3 mt-3">
        <h6>Opciones</h6>

        <div class="d-flex gap-2 mb-3">
        @if(isset($table))
            <button id="btnToggleAvailable" class="btn btn-outline-secondary">
                Cambiar disponibilidad
            </button>
        @endif
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('orders.ticket', $order->id) }}" target="_blank" class="btn btn-secondary">Imprimir ticket</a>
            <button id="btnCloseOrder" class="btn btn-success w-50">
                Cerrar / Pagar
            </button>

            <a href="{{ route('orders.index') }}" class="btn btn-secondary w-50">
                Volver
            </a>
        </div>
    </div>

    </div>
  </div>
</div>


<div class="modal fade" id="addonsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      
      <div class="modal-header">
        <h5 class="modal-title">Agregar adicionales</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <h5 id="addonProductName"></h5>
        <input type="hidden" id="addonProductId">

        <div id="addonsList"></div>

        <div class="mt-3">
          <label>Nota del producto</label>
          <textarea id="addonNote" class="form-control"></textarea>
        </div>

      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" id="btnConfirmAddons">Agregar a la orden</button>
      </div>

    </div>
  </div>
</div>

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


// Guardar cliente rápido
$('#btnSaveCustomer').on('click', function () {
    $.post('{{ route("customers.quickSave") }}', {
        name: $('#cName').val(),
        document: $('#cDocument').val(),
        phone: $('#cPhone').val(),
        email: $('#cEmail').val()
    }, function (res) {

        if (res.success) {
          console.log(res);

           var select2Element = $('#orderCustomer');
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
            updateOrderCustomer();

            _alertGeneric('success','Muy Bien!','Cliente creado correctamente',1);

            // Cerrar modal
            $('#newCustomerModal').modal('hide');
        }
    });
});

// Cuando selecciona un cliente
$('#orderCustomer').on('change', function(){
    updateOrderCustomer();
});

// Guardar cliente en la orden
function updateOrderCustomer(){
    $.post('{{ route("orders.updateCustomer") }}', {
        order_id: {{ $order->id }},
        customer_id: $('#orderCustomer').val()
    });
}


$(function(){
  $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }});

  const orderId = {{ $order->id }};
  const orderTotalEl = $('#order-total');

  // agregar producto
$(document).on('click','.add-product',function(){
    const pid = $(this).data('id');
    const pname = $(this).data('name');
    
    $.get('/products/'+pid+'/addons', function(addons){

        $('#addonProductName').text(pname);
        $('#addonProductId').val(pid);
        $('#addonNote').val('');

        let html = "";

        if(addons.length === 0){
            // no addons → agregar directo
            addItemToOrder(pid, [], '');
            return;
        }

        addons.forEach(a=>{
            html += `
              <div class="form-check mb-2">
                <input class="form-check-input addon-check" 
       type="checkbox"
       value="${a.id}" 
       data-name="${a.name}" 
       data-price="${a.price}">
                <label class="form-check-label">
                    ${a.name} (+ ${a.price} COP)
                </label>
              </div>
            `;
        });

        $('#addonsList').html(html);
        new bootstrap.Modal('#addonsModal').show();
    });
});


// Actualizar la función de aumentar
$(document).on('click', '.btn-increase', function(){
    const id = $(this).data('id');
    const input = $(`.qty-input[data-id="${id}"]`);
    let currentQty = parseInt(input.val());
    
    // Validar que sea un número
    if (isNaN(currentQty)) {
        currentQty = 1;
    }
    
    const newQty = currentQty + 1;
    input.val(newQty);
    
    // Actualizar inmediatamente
    updateItemQuantity(id, newQty);
});

// Actualizar la función de disminuir
$(document).on('click', '.btn-decrease', function(){
    const id = $(this).data('id');
    const input = $(`.qty-input[data-id="${id}"]`);
    let currentQty = parseInt(input.val());
    
    // Validar que sea un número
    if (isNaN(currentQty) || currentQty <= 1) {
        currentQty = 1;
    }
    
    const newQty = Math.max(1, currentQty - 1);
    input.val(newQty);
    
    // Actualizar inmediatamente
    updateItemQuantity(id, newQty);
});

  // quitar
  $(document).on('click', '.btn-remove', function(){
    const id = $(this).data('id');
    Swal.fire({
      title:'Quitar item?',
      showCancelButton:true,
      confirmButtonText:'Quitar'
    }).then((r)=>{
      if(r.isConfirmed){
        $.post('{{ route("orders.deleteItem") }}', { id: id }, function(res){
          if(res.success) location.reload();
        });
      }
    });
  });

  // cambiar estado mesa (si aplica)
  $('#btnToggleAvailable').on('click', function(){
    @if(isset($table))
      const tableId = {{ $table->id }};
      // toggle disponible <-> ocupada
      const newStatus = '{{ $table->status }}' === 'disponible' ? 'ocupada' : 'disponible';
      $.post('{{ route("orders.changeStatus") }}', { table_id: tableId, status: newStatus }, function(res){
        if(res.success) location.reload();
      });
    @endif
  });

  // cerrar / pagar pedido
 // cerrar / pagar pedido
// cerrar / pagar pedido
$('#btnCloseOrder').on('click', function(){
    // Verificar si hay productos en el pedido
    const itemCount = $('#order-items .order-item').length;
    
    if(itemCount === 0) {
        Swal.fire('Pedido vacío', 'Debes agregar productos al pedido antes de cerrarlo.', 'warning');
        return;
    }
    
    // Solo validar si es domicilio
    @if($order->type == 'domicilio')
        let n = $('#d_name').val().trim();
        let p = $('#d_phone').val().trim();
        let a = $('#d_address').val().trim();

        if(n === '' || p === '' || a === ''){
            Swal.fire('Faltan datos', 'Debes completar nombre, teléfono y dirección.', 'warning');
            return;
        }
    @endif

    Swal.fire({
        title: 'Cerrar pedido y generar ticket?',
        html: `Método de pago seleccionado: <strong>${$('#payment-method').val()}</strong>`,
        showCancelButton: true,
        confirmButtonText: 'Sí, cerrar'
    }).then((r) => {
        if(r.isConfirmed){
            const data = {
                note: $('#order-note').val(),
                payment_method: $('#payment-method').val(), // Añadido el método de pago
                @if($order->type == 'domicilio')
                    customer_name: $('#d_name').val(),
                    customer_phone: $('#d_phone').val(),
                    customer_address: $('#d_address').val(),
                @endif
            };
            
            $.post('{{ url("/orders/close/".$order->id) }}', data, function(res){
                if(res.success){
                    Swal.fire('Listo', 'Pedido cerrado', 'success').then(() => {
                        window.open('{{ route("orders.ticket", $order->id) }}', '_blank');
                        window.location.href = '{{ route("orders.index") }}';
                    });
                }
            });
        }
    });
});

});


$('#btnConfirmAddons').on('click', function(){
    const pid = $('#addonProductId').val();
    const note = $('#addonNote').val();

    let addons = [];
    $('.addon-check:checked').each(function(){
        addons.push($(this).val());

    });

    addItemToOrder(pid, addons, note);
});

function addItemToOrder(productId, addons, note){
    $.post('{{ route("orders.addItem") }}', {
        order_id: {{ $order->id }},
        product_id: productId,
        quantity: 1,
        addons: addons,
        note: note
    }, function(res){
        if(res.success){
            location.reload();
        }
    });
}

function loadProducts(page = 1) {
    $.get('{{ route("products.filter") }}', {
        category_id: $('#filterCategory').val(),
        search: $('#filterSearch').val(),
        page: page
    }, function(res) {
        $('#productsList').html(res.html);
        $('#paginationLinks').html(res.pagination);
    });
}


// Filtro por categoría
$('#filterCategory').on('change', function() {
    loadProducts();
});

// Buscador
$('#filterSearch').on('keyup', function() {
    loadProducts();
});

// Paginación AJAX
$(document).on('click', '#paginationLinks a', function(e){
    e.preventDefault();
    let url = $(this).attr('href');
    let page = url.split('page=')[1];
    loadProducts(page);
});

$(document).ready(function(){
    loadProducts();
});

// En tu script JavaScript, añade estas funciones:

// Función para actualizar la cantidad via input
$(document).on('change', '.qty-input', function(){
    const id = $(this).data('id');
    let newQty = parseInt($(this).val());
    
    // Validar que sea un número válido
    if (isNaN(newQty) || newQty < 1) {
        // Restaurar valor anterior
        $(this).val($(this).data('last-value') || 1);
        return;
    }
    
    // Guardar último valor válido
    $(this).data('last-value', newQty);
    
    // Actualizar en servidor
    updateItemQuantity(id, newQty);
});

// Función para validar entrada mientras se escribe
$(document).on('input', '.qty-input', function(){
    const input = $(this);
    let value = input.val();
    
    // Remover caracteres no numéricos
    let numericValue = value.replace(/[^0-9]/g, '');
    
    // Si está vacío o no es número, usar 1
    if (!numericValue || numericValue === '0') {
        numericValue = '1';
    }
    
    // Limitar a 3 dígitos
    if (numericValue.length > 3) {
        numericValue = numericValue.substring(0, 3);
    }
    
    // Actualizar valor en el input
    if (value !== numericValue) {
        input.val(numericValue);
    }
});

// Función para actualizar cantidad (reutilizable)
function updateItemQuantity(itemId, quantity) {
    // Mostrar loader opcional
    const itemEl = $(`.order-item[data-id="${itemId}"]`);
    itemEl.addClass('updating');
    
    $.post('{{ route("orders.updateItem") }}', { 
        id: itemId, 
        quantity: quantity 
    }, function(res){
        if(res.success) {
            // Actualizar total del item localmente
            updateItemTotal(itemId);
            
            // Actualizar total general del pedido
            updateOrderTotal(res.order_total);
            
            // Quitar clase de actualización
            itemEl.removeClass('updating');
            
            // Si deseas feedback visual
            showToast('Cantidad actualizada', 'success');
        }
    }).fail(function(){
        // En caso de error, recargar la página para sincronizar
        location.reload();
    });
}

// Función para actualizar total del item localmente
function updateItemTotal(itemId) {
    const qty = parseInt($(`.qty-input[data-id="${itemId}"]`).val());
    const price = $(`.order-item[data-id="${itemId}"]`).data('price') || 0;
    const addonsTotal = $(`.order-item[data-id="${itemId}"]`).data('addons-total') || 0;
    
    const itemTotal = (price + addonsTotal) * qty;
    $(`.item-total[data-id="${itemId}"]`).text(itemTotal.toFixed(0));
}

// Función para actualizar total general
function updateOrderTotal(newTotal) {
    $('#order-total').text(parseFloat(newTotal).toFixed(0));
}

// Función de toast para feedback
function showToast(message, type = 'info') {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });
    
    Toast.fire({
        icon: type,
        title: message
    });
}

// Función para actualizar los atributos data en los items al cargar
function initializeItemData() {
    $('.order-item').each(function() {
        const itemId = $(this).data('id');
        const price = parseFloat($(this).find('.item-total').text()) / parseInt($(this).find('.qty-input').val());
        $(this).data('price', price);
        
        // Guardar último valor válido
        const input = $(this).find('.qty-input');
        input.data('last-value', input.val());
    });
}

// Inicializar al cargar la página
$(document).ready(function(){
    initializeItemData();
});


// En la sección de scripts de tu vista

// Variable para el debounce
let noteDebounceTimer;
let lastSavedNote = $('#order-note').val();

// Función para guardar nota general
function saveOrderNote() {
    const note = $('#order-note').val();
    
    // Solo guardar si cambió
    if (note === lastSavedNote) {
        return;
    }
    
    $.post('{{ route("orders.updateNote", $order->id) }}', {
        note: note
    }, function(res) {
        if (res.success) {
            lastSavedNote = note;
            showToast('Nota guardada', 'success');
        } else {
            showToast('Error al guardar la nota', 'error');
        }
    }).fail(function() {
        showToast('Error de conexión', 'error');
    });
}

// Evento con debounce (guardar 1 segundo después de dejar de escribir)
$('#order-note').on('input', function() {
    clearTimeout(noteDebounceTimer);
    
    // Mostrar indicador de guardando
    $(this).addClass('is-saving');
    
    noteDebounceTimer = setTimeout(function() {
        saveOrderNote();
        $('#order-note').removeClass('is-saving');
    }, 1000); // 1 segundo de debounce
});

// También guardar cuando pierde el foco (inmediatamente)
$('#order-note').on('blur', function() {
    clearTimeout(noteDebounceTimer);
    saveOrderNote();
    $(this).removeClass('is-saving');
});

// También guardar al presionar Ctrl+Enter
$('#order-note').on('keydown', function(e) {
    if (e.ctrlKey && e.keyCode === 13) { // Ctrl+Enter
        e.preventDefault();
        clearTimeout(noteDebounceTimer);
        saveOrderNote();
    }
});
</script>
@endpush
