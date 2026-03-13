@extends('layouts.app')

@section('content')
<div class="card mb-4">
    <h5 class="card-header">Editar negocio + activación de servicios</h5>
    <form method="POST" action="{{ route('companies.update', $company) }}" class="card-body">
        @csrf
        @method('PUT')

        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Nombre negocio</label><input name="name" class="form-control" value="{{ old('name', $company->name) }}" required></div>
            <div class="col-md-6"><label class="form-label">NIT</label><input name="nit" class="form-control" value="{{ old('nit', $company->nit) }}" required></div>
            <div class="col-md-6"><label class="form-label">Dirección</label><input name="address" class="form-control" value="{{ old('address', $company->address) }}" required></div>
            <div class="col-md-6"><label class="form-label">Email negocio</label><input type="email" name="email" class="form-control" value="{{ old('email', $company->email) }}" required></div>
            <div class="col-md-6"><label class="form-label">Contacto negocio</label><input name="contact_name" class="form-control" value="{{ old('contact_name', $company->contact_name) }}" required></div>

            <div class="col-md-3"><label class="form-label">Plan</label><input name="plan_name" class="form-control" value="{{ old('plan_name', $company->plan_name) }}" required></div>
            <div class="col-md-3"><label class="form-label">Estado servicio</label><select name="service_status" class="form-select"><option value="active" @selected(old('service_status', $company->service_status)==='active')>Activo</option><option value="inactive" @selected(old('service_status', $company->service_status)==='inactive')>Inactivo</option><option value="suspended" @selected(old('service_status', $company->service_status)==='suspended')>Suspendido</option></select></div>
            <div class="col-md-3"><label class="form-label">Activo desde</label><input type="date" name="started_at" class="form-control" value="{{ old('started_at', optional($company->started_at)->format('Y-m-d')) }}"></div>
            <div class="col-md-3"><label class="form-label">Activo hasta</label><input type="date" name="active_until" class="form-control" value="{{ old('active_until', optional($company->active_until)->format('Y-m-d')) }}"></div>

            <div class="col-md-2"><input type="hidden" name="gglob_cloud_enabled" value="0"><label><input type="checkbox" name="gglob_cloud_enabled" value="1" @checked(old('gglob_cloud_enabled', $company->gglob_cloud_enabled))> Gglob Nube</label></div>
            <div class="col-md-2"><input type="hidden" name="gglob_pay_enabled" value="0"><label><input type="checkbox" name="gglob_pay_enabled" value="1" @checked(old('gglob_pay_enabled', $company->gglob_pay_enabled))> Gglob Pay</label></div>
            <div class="col-md-2"><input type="hidden" name="gglob_pos_enabled" value="0"><label><input type="checkbox" name="gglob_pos_enabled" value="1" @checked(old('gglob_pos_enabled', $company->gglob_pos_enabled))> Gglob POS</label></div>
            <div class="col-md-2"><input type="hidden" name="gglob_accounting_enabled" value="0"><label><input type="checkbox" name="gglob_accounting_enabled" value="1" @checked(old('gglob_accounting_enabled', $company->gglob_accounting_enabled))> Gglob Contable</label></div>
            <div class="col-md-2"><label class="form-label">POS modo</label><select name="pos_mode" class="form-select"><option value="mono" @selected(old('pos_mode', $company->pos_mode)==='mono')>MonoCaja</option><option value="multi" @selected(old('pos_mode', $company->pos_mode)==='multi')>MultiCaja</option></select></div>
            <div class="col-md-2"><label class="form-label"># Cajas</label><input type="number" min="1" max="50" name="pos_boxes" class="form-control" value="{{ old('pos_boxes', $company->pos_boxes) }}"></div>
        </div>

        <div class="pt-4 d-flex gap-2">
            <button class="btn btn-primary">Actualizar negocio</button>
            <a href="{{ route('companies.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Dueño del negocio</div>
            <div class="card-body small">
                @forelse($company->owners as $owner)
                    <div><strong>{{ $owner->name }} {{ $owner->last_name }}</strong></div>
                    <div>{{ $owner->email }} | {{ $owner->phone }}</div>
                @empty
                    <div>No hay dueño asociado.</div>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Crear usuario cajero (permisos limitados)</div>
            <div class="card-body">
                <form method="POST" action="{{ route('companies.cashiers.store', $company) }}" class="row g-2">
                    @csrf
                    <div class="col-md-6"><input name="name" class="form-control" placeholder="Nombre" required></div>
                    <div class="col-md-6"><input name="last_name" class="form-control" placeholder="Apellido"></div>
                    <div class="col-md-6"><input type="email" name="email" class="form-control" placeholder="Correo" required></div>
                    <div class="col-md-6"><input name="phone" class="form-control" placeholder="Teléfono"></div>
                    <div class="col-md-6"><input type="password" name="password" class="form-control" placeholder="Contraseña" required></div>
                    <div class="col-md-6"><input type="password" name="password_confirmation" class="form-control" placeholder="Confirmar" required></div>
                    <div class="col-12"><button class="btn btn-outline-primary">Agregar cajero</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header">Cajeros del negocio</div>
            <div class="card-body table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Nombre</th><th>Email</th><th>Teléfono</th></tr></thead>
                    <tbody>
                    @forelse($company->cashiers as $cashier)
                        <tr><td>{{ $cashier->name }} {{ $cashier->last_name }}</td><td>{{ $cashier->email }}</td><td>{{ $cashier->phone }}</td></tr>
                    @empty
                        <tr><td colspan="3">No hay cajeros creados.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
