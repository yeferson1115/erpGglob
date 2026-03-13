@extends('layouts.app')

@section('title', 'Usuarios')
@section('page_title', 'Usuarios')

@section('content')
<div class="content-header row mt-5">
    <div class="content-header-left col-md-9 col-12 mb-2">
        <h2 class="content-header-title float-start mb-0">Usuarios</h2>
    </div>
    <div class="content-header-right text-md-end col-md-3 col-12 d-md-block d-none">
        @can('Crear Usuarios')
            <a href="{{ url('users/create') }}" class="btn btn-success"><i class="fa-solid fa-plus"></i> Nuevo Usuario</a>
        @endcan
    </div>
</div>

<div class="content-body">
    <section id="multiple-column-form">
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" class="row g-2">
                    <div class="col-md-3">
                        <select name="service_status" class="form-select">
                            <option value="">Todos los estados</option>
                            <option value="active" @selected(($statusFilter ?? '') === 'active')>Activos</option>
                            <option value="inactive" @selected(($statusFilter ?? '') === 'inactive')>Inactivos</option>
                            <option value="suspended" @selected(($statusFilter ?? '') === 'suspended')>Suspendidos</option>
                        </select>
                    </div>
                    <div class="col-md-2"><button class="btn btn-primary">Filtrar</button></div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h4 class="card-title">Usuarios del sistema</h4></div>
            <div class="card-body table-responsive">
                <table class="table" id="datatables">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Acciones</th>
                            <th>Nombre</th>
                            <th>Plan</th>
                            <th>Estado servicio</th>
                            <th>Servicios activos</th>
                            <th>Vigencia</th>
                            <th>Email</th>
                            <th>Teléfono</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($users as $user)
                        @php $customer = $user->platformCustomer; @endphp
                        <tr class="odd row{{ $user->id }}">
                            <td>{{ $user->id }}</td>
                            <td>
                                @can('Editar Usuarios')
                                    <a class="mb-1 btn btn-warning" href="{{ url('users', [$user->id, 'edit']) }}" title="Editar"><i class="fa-solid fa-pen-to-square"></i></a>
                                @endcan
                                @can('Eliminar Usuarios')
                                    <form method="POST" action="" class="d-inline">
                                        <button type="submit" data-token="{{ csrf_token() }}" data-attr="{{ url('users',[$user->id]) }}" class="btn btn-danger delete-user"><i class="fa-solid fa-trash-can"></i></button>
                                    </form>
                                @endcan
                            </td>
                            <td>{{ $user->name }} {{ $user->last_name }}</td>
                            <td>{{ $customer->plan_name ?? 'Sin configurar' }}</td>
                            <td>
                                @if($customer)
                                    <span class="badge bg-{{ $customer->subscription_status === 'active' ? 'success' : ($customer->subscription_status === 'suspended' ? 'warning' : 'secondary') }}">
                                        {{ strtoupper($customer->subscription_status) }}
                                    </span>
                                @else
                                    <span class="badge bg-dark">SIN PERFIL</span>
                                @endif
                            </td>
                            <td class="small">
                                @if($customer)
                                    {{ $customer->gglob_cloud_enabled ? 'Nube ' : '' }}
                                    {{ $customer->gglob_pay_enabled ? 'Pay ' : '' }}
                                    {{ $customer->gglob_pos_enabled ? 'POS('.($customer->pos_mode === 'multi' ? 'Multi x'.$customer->pos_boxes : 'Mono').') ' : '' }}
                                    {{ $customer->gglob_accounting_enabled ? 'Contable' : '' }}
                                @endif
                            </td>
                            <td>{{ optional($customer?->active_until)->format('d/m/Y') ?? '-' }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->phone }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
$('.delete-user').click(function(e){
    e.preventDefault();
    let href = $(this).attr('data-attr');
    let token = $(this).attr('data-token');

    Swal.fire({
      title: 'Seguro que desea eliminar el usuario?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Aceptar',
      cancelButtonText: 'Cancelar',
    }).then((result) => {
      if (result.isConfirmed) {
        $.ajax({
          url: href,
          headers: {'X-CSRF-TOKEN': token},
          type: 'DELETE',
          success: function (response) {
            var json = $.parseJSON(response);
            if (json.success) {
              Swal.fire('Muy bien!', 'Usuario eliminado correctamente', 'success').then(() => location.reload());
            }
          }
        });
      }
    });
});
</script>
@endpush
