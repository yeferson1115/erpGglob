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
            <div class="col-md-3"><label class="form-label"># Puntos de venta</label><input type="number" name="pos_locations_count" class="form-control" min="0" max="100" value="{{ old('pos_locations_count', 0) }}"></div>
            <div class="col-md-9"><label class="form-label">Puntos de venta (uno por línea)</label><textarea name="pos_locations_text" class="form-control" rows="2" placeholder="Sede Centro&#10;Sede Norte">{{ old('pos_locations_text') }}</textarea></div>
        </div>

        <h6 class="mb-2">Activación de servicios del negocio</h6>
        <div class="row g-3 mb-4">
            <div class="col-md-4"><label class="form-label">Plan</label><select name="plan_id" class="form-select" required><option value="">Selecciona un plan</option>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected((int) old('plan_id') === $plan->id)>{{ $plan->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Estado</label><select name="service_status" class="form-select"><option value="active">Activo</option><option value="inactive" selected>Inactivo</option><option value="suspended">Suspendido</option></select></div>
            <div class="col-md-3"><label class="form-label">Activo desde</label><input type="date" name="started_at" class="form-control" value="{{ old('started_at') }}"></div>
            <div class="col-md-3"><label class="form-label">Activo hasta</label><input type="date" name="active_until" class="form-control" value="{{ old('active_until') }}"></div>

            
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
