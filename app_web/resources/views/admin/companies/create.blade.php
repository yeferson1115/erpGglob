@extends('layouts.app')

@section('content')
<div class="card mb-6">
    <h5 class="card-header">Registrar negocio + usuario dueño</h5>
    <form method="POST" action="{{ route('companies.store') }}" class="card-body">
        @csrf

        <h6 class="mb-2">Datos del negocio</h6>
        <div class="row g-3 mb-4">
            <div class="col-md-6"><label class="form-label">Nombre negocio</label><input name="name" class="form-control" value="{{ old('name') }}" required></div>
            <div class="col-md-6"><label class="form-label">NIT</label><input name="nit" class="form-control" value="{{ old('nit') }}" required></div>
            <div class="col-md-6"><label class="form-label">Dirección</label><input name="address" class="form-control" value="{{ old('address') }}" required></div>
            <div class="col-md-6"><label class="form-label">Email negocio</label><input type="email" name="email" class="form-control" value="{{ old('email') }}" required></div>
            <div class="col-md-6"><label class="form-label">Contacto negocio</label><input name="contact_name" class="form-control" value="{{ old('contact_name') }}" required></div>
        </div>

        <h6 class="mb-2">Activación de servicios del negocio</h6>
        <div class="row g-3 mb-4">
            <div class="col-md-3"><label class="form-label">Plan</label><input name="plan_name" class="form-control" value="{{ old('plan_name', 'Sin plan') }}" required></div>
            <div class="col-md-3"><label class="form-label">Estado</label><select name="service_status" class="form-select"><option value="active">Activo</option><option value="inactive" selected>Inactivo</option><option value="suspended">Suspendido</option></select></div>
            <div class="col-md-3"><label class="form-label">Activo desde</label><input type="date" name="started_at" class="form-control" value="{{ old('started_at') }}"></div>
            <div class="col-md-3"><label class="form-label">Activo hasta</label><input type="date" name="active_until" class="form-control" value="{{ old('active_until') }}"></div>

            <div class="col-md-2"><input type="hidden" name="gglob_cloud_enabled" value="0"><label><input type="checkbox" name="gglob_cloud_enabled" value="1" @checked(old('gglob_cloud_enabled'))> Gglob Nube</label></div>
            <div class="col-md-2"><input type="hidden" name="gglob_pay_enabled" value="0"><label><input type="checkbox" name="gglob_pay_enabled" value="1" @checked(old('gglob_pay_enabled'))> Gglob Pay</label></div>
            <div class="col-md-2"><input type="hidden" name="gglob_pos_enabled" value="0"><label><input type="checkbox" name="gglob_pos_enabled" value="1" @checked(old('gglob_pos_enabled'))> Gglob POS</label></div>
            <div class="col-md-2"><input type="hidden" name="gglob_accounting_enabled" value="0"><label><input type="checkbox" name="gglob_accounting_enabled" value="1" @checked(old('gglob_accounting_enabled'))> Gglob Contable</label></div>
            <div class="col-md-2"><label class="form-label">POS modo</label><select name="pos_mode" class="form-select"><option value="mono">MonoCaja</option><option value="multi">MultiCaja</option></select></div>
            <div class="col-md-2"><label class="form-label"># Cajas</label><input type="number" min="1" max="50" name="pos_boxes" class="form-control" value="{{ old('pos_boxes', 1) }}"></div>
        </div>

        <h6 class="mb-2">Usuario dueño (más opciones)</h6>
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Nombre</label><input name="owner_name" class="form-control" value="{{ old('owner_name') }}" required></div>
            <div class="col-md-4"><label class="form-label">Apellido</label><input name="owner_last_name" class="form-control" value="{{ old('owner_last_name') }}"></div>
            <div class="col-md-4"><label class="form-label">Teléfono</label><input name="owner_phone" class="form-control" value="{{ old('owner_phone') }}"></div>
            <div class="col-md-4"><label class="form-label">Correo</label><input type="email" name="owner_email" class="form-control" value="{{ old('owner_email') }}" required></div>
            <div class="col-md-4"><label class="form-label">Contraseña</label><input type="password" name="owner_password" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Confirmar contraseña</label><input type="password" name="owner_password_confirmation" class="form-control" required></div>
        </div>

        <div class="pt-4 d-flex gap-2">
            <button class="btn btn-primary">Guardar negocio</button>
            <a href="{{ route('companies.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
</div>
@endsection
