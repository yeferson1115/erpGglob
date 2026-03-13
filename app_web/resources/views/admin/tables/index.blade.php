@extends('layouts.app')

@section('content')

<div class="card mb-6">   
    <div class="row card-header flex-column flex-md-row border-bottom mx-0 px-3 mb-3">
        <div class="d-md-flex justify-content-between align-items-center dt-layout-start col-md-auto me-auto mt-0">
            <h5 class="card-title mb-0 text-md-start text-center pb-md-0 pb-6">Mesas</h5>
        </div>
        @can('Crear Mesas')
        <div class="d-md-flex justify-content-between align-items-center dt-layout-end col-md-auto ms-auto mt-0">
            <div class="dt-buttons btn-group flex-wrap mb-0"> 
               <button class="btn create-new btn-primary" id="btnNew" >
                    <span>
                        <span class="d-flex align-items-center gap-2">
                            <i class="icon-base ti tabler-plus icon-sm"></i> 
                            <span class="d-none d-sm-inline-block">Nueva Mesa</span>
                        </span>
                    </span>
                </button> 
            </div>
        </div>
        @endcan
    </div>

    <table class="table table-bordered table-striped" id="datatables">
        <thead>
            <tr>
                <th>#</th>
                <th>Mesa</th>
                <th>Estado</th>
                <th>Nota</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tables as $t)
            <tr id="row-{{ $t->id }}">
                <td>{{ $t->id }}</td>
                <td>{{ $t->name }}</td>
                <td>
                    @if($t->status == 'disponible')
                        <span class="badge bg-success">Disponible</span>
                    @elseif($t->status == 'ocupada')
                        <span class="badge bg-danger">Ocupada</span>
                    @else
                        <span class="badge bg-warning">Reservada</span>
                    @endif
                </td>
                <td>{{ $t->note }}</td>
                <td>
                    @can('Editar Mesas')
                    <button class="btn btn-secondary btn-sm btnEdit" data-id="{{ $t->id }}"> <i class="fa-solid fa-pen-to-square" style="margin-right: 10px;"></i> Editar</button>
                    @endcan
                    @can('Eliminar Mesas')
                    <button class="btn btn-danger btn-sm btnDelete"
                        data-id="{{ $t->id }}"
                        data-url="{{ route('tables.destroy', $t->id) }}">
                        <i class="fa-regular fa-trash-can" style="margin-right: 10px;"></i> Eliminar
                    </button>
                    @endcan
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>


{{-- MODAL --}}
<div class="modal fade" id="tableModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="tableForm">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 id="modalTitle">Nueva Mesa</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <input type="hidden" id="table_id">

                    <div class="mb-2">
                        <label>Nombre</label>
                        <input type="text" id="name" class="form-control">
                        <div class="invalid-feedback" id="error-name"></div>
                    </div>

                    <!--<div class="mb-2">
                        <label>Asientos</label>
                        <input type="number" id="seats" class="form-control">
                        <div class="invalid-feedback" id="error-seats"></div>
                    </div>-->

                    <div class="mb-2">
                        <label>Estado</label>
                        <select id="status" class="form-control">
                            <option value="disponible">Disponible</option>
                            <option value="ocupada">Ocupada</option>
                            <option value="reservada">Reservada</option>
                        </select>
                        <div class="invalid-feedback" id="error-status"></div>
                    </div>

                    <div class="mb-2">
                        <label>Nota</label>
                        <textarea id="note" class="form-control"></textarea>
                    </div>

                    <div id="formErrors"></div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button class="btn btn-primary" id="btnSave">Guardar</button>
                </div>

            </div>
        </form>
    </div>
</div>
@endsection


@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function(){

    let modal = new bootstrap.Modal(document.getElementById('tableModal'));

    // Abrir modal nueva mesa
    $('#btnNew').on('click', function(){
        clearForm();
        $('#modalTitle').text('Nueva Mesa');
        modal.show();
    });

    // Guardar (store/update)
    // Guardar (store/update)
$('#btnSave').on('click', function(e){
    e.preventDefault();

    let id = $('#table_id').val();
    let url = id ? '/tables/' + id : '/tables';
    let method = id ? 'PUT' : 'POST';

    $.ajax({
        url: url,
        method: 'POST',
        data: {
            _method: method,
            name: $('#name').val(),
            seats: $('#seats').val(),
            status: $('#status').val(),
            note: $('#note').val(),
            _token: '{{ csrf_token() }}'   // 🔥 FALTABA ESTO
        },
        success: function(res){

            // 🔥 CERRAR EL MODAL
            modal.hide();

            // 🔥 LIMPIAR FORMULARIO
            clearForm();

            // 🔥 ALERTA BONITA
            Swal.fire({
                icon: 'success',
                title: res.message,
                timer: 1500,
                showConfirmButton: false
            });

            // 🔥 RECARGAR LA TABLA
            setTimeout(() => location.reload(), 700);
        },
        error: function(xhr){
            if(xhr.status === 422){
                let errors = xhr.responseJSON.errors;
                let html = '<ul>';
                $.each(errors, (k,v)=> html += '<li>'+v[0]+'</li>');
                html += '</ul>';
                $('#formErrors').html('<div class="alert alert-danger">'+html+'</div>');
            }
        }
    });
});


    // Editar mesa
    $(document).on('click', '.btnEdit', function(){
        let id = $(this).data('id');

        $.get('/tables/' + id, function(res){
            let t = res.table;
            clearForm();
            $('#modalTitle').text('Editar Mesa');
            $('#table_id').val(t.id);
            $('#name').val(t.name);
            $('#seats').val(t.seats);
            $('#status').val(t.status);
            $('#note').val(t.note);
            modal.show();
        });
    });

    // Eliminar con SweetAlert
    $(document).on('click', '.btnDelete', function(){
        let id = $(this).data('id');
        let url = $(this).data('url');

        Swal.fire({
            title: '¿Eliminar mesa?',
            text: 'No se puede deshacer',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar'
        }).then((result)=>{
            if(result.isConfirmed){
                $.ajax({
                    url: url,
                    method: 'POST',
                    data: { _method:'DELETE' },
                    success: function(res){
                        Swal.fire('Eliminada', res.message, 'success');
                        $('#row-'+id).remove();
                    }
                });
            }
        });
    });

    // limpiar form
    function clearForm(){
        $('#tableForm')[0].reset();
        $('#table_id').val('');
        $('#formErrors').html('');
    }

});
</script>
@endpush
