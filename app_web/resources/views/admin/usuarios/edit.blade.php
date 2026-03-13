@extends('layouts.app')

@section('title', 'Usuarios')
@section('page_title', 'Usuarios')
@section('page_subtitle', 'Editar')

@section('content')
<div class="content-header row mt-5">
    <div class="content-header-left col-md-9 col-12 mb-2">
        <h2 class="content-header-title float-start mb-0">Editar: {{ $user->name }} {{ $user->last_name }}</h2>
    </div>
</div>

<div class="content-body">
    <section id="multiple-column-form">
        <div class="row">
            <div class="col-12">
                <div class="card mb-3">
                    <div class="card-header"><h4 class="card-title">Editar Usuario</h4></div>
                    <div class="card-body">
                        <form class="form" role="form" id="main-form" autocomplete="off">
                            @method('PUT')
                            <input type="hidden" id="_url" value="{{ url('users',[$user->id]) }}">
                            <input type="hidden" id="_token" value="{{ csrf_token() }}">
                            <div class="row">
                                <div class="col-md-6"><label class="form-label">Nombres</label><input type="text" class="form-control" id="name" name="name" value="{{ $user->name }}"></div>
                                <div class="col-md-6"><label class="form-label">Apellidos</label><input type="text" class="form-control" id="last_name" name="last_name" value="{{ $user->last_name }}"></div>
                                <div class="col-md-6 mt-2"><label class="form-label">Teléfono</label><input type="text" class="form-control" id="phone" name="phone" value="{{ $user->phone }}"></div>
                                <div class="col-md-6 mt-2"><label class="form-label">Correo</label><input type="email" class="form-control" id="email" name="email" value="{{ $user->email }}"></div>
                                <div class="col-md-6 mt-2">
                                    <label class="form-label">Género</label>
                                    <div>
                                        <label class="me-2"><input type="radio" name="gender" value="M" {{ $user->gender=='M' ? 'checked' : '' }}> Masculino</label>
                                        <label><input type="radio" name="gender" value="F" {{ $user->gender=='F' ? 'checked' : '' }}> Femenino</label>
                                    </div>
                                </div>
                                <div class="col-md-6 mt-2"><label class="form-label">Nueva contraseña</label><input type="password" class="form-control" id="password" name="password"></div>
                                <div class="col-md-6 mt-2"><label class="form-label">Confirmar contraseña</label><input type="password" class="form-control" id="password_confirmation" name="password_confirmation"></div>
                                <div class="col-12 mt-3">
                                    <button type="submit" class="btn btn-primary" id="submit"><i id="ajax-icon" class="fa fa-save me-1"></i> Guardar datos del usuario</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Activación de servicios y estado del cliente</h4>
                        <small class="text-muted">Si el usuario pertenece a una empresa, hereda automáticamente el plan y servicios de esa empresa.</small>
                    </div>
                    <div class="card-body">
                        @if($user->company)
                        <div class="alert alert-info">Este usuario pertenece a <strong>{{ $user->company->name }}</strong> y hereda el plan <strong>{{ $user->company->plan_name }}</strong>. Gestiona el plan desde Empresas.</div>
                        @endif
                        <form method="POST" action="{{ route('users.services.update', $user) }}" class="row g-2">
                            @csrf
                            @method('PUT')
                            <div class="col-md-3"><label class="form-label">Plan</label><input class="form-control" name="plan_name" @disabled($user->company) value="{{ old('plan_name', $platformCustomer->plan_name ?? 'Sin plan') }}" required></div>
                            <div class="col-md-3">
                                <label class="form-label">Estado del servicio</label>
                                <select class="form-select" name="subscription_status" @disabled($user->company)>
                                    @foreach(['active'=>'Activo','inactive'=>'Inactivo','suspended'=>'Suspendido'] as $value=>$label)
                                        <option value="{{ $value }}" @selected(old('subscription_status', $platformCustomer->subscription_status ?? 'inactive')===$value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3"><label class="form-label">Activo desde</label><input type="date" class="form-control" name="started_at" @disabled($user->company) value="{{ old('started_at', optional($platformCustomer?->started_at)->format('Y-m-d')) }}"></div>
                            <div class="col-md-3"><label class="form-label">Activo hasta</label><input type="date" class="form-control" name="active_until" @disabled($user->company) value="{{ old('active_until', optional($platformCustomer?->active_until)->format('Y-m-d')) }}"></div>

                            <div class="col-md-2"><input type="hidden" name="gglob_cloud_enabled" @disabled($user->company) value="0"><label><input type="checkbox" name="gglob_cloud_enabled" @disabled($user->company) value="1" @checked(old('gglob_cloud_enabled', $platformCustomer->gglob_cloud_enabled ?? false))> Activar Gglob Nube</label></div>
                            <div class="col-md-2"><input type="hidden" name="gglob_pay_enabled" @disabled($user->company) value="0"><label><input type="checkbox" name="gglob_pay_enabled" @disabled($user->company) value="1" @checked(old('gglob_pay_enabled', $platformCustomer->gglob_pay_enabled ?? false))> Activar Gglob Pay</label></div>
                            <div class="col-md-2"><input type="hidden" name="gglob_pos_enabled" @disabled($user->company) value="0"><label><input type="checkbox" name="gglob_pos_enabled" @disabled($user->company) value="1" @checked(old('gglob_pos_enabled', $platformCustomer->gglob_pos_enabled ?? false))> Activar Gglob POS</label></div>
                            <div class="col-md-2"><input type="hidden" name="gglob_accounting_enabled" @disabled($user->company) value="0"><label><input type="checkbox" name="gglob_accounting_enabled" @disabled($user->company) value="1" @checked(old('gglob_accounting_enabled', $platformCustomer->gglob_accounting_enabled ?? false))> Activar Gglob Contable</label></div>
                            <div class="col-md-2"><input type="hidden" name="is_paid" @disabled($user->company) value="0"><label><input type="checkbox" name="is_paid" @disabled($user->company) value="1" @checked(old('is_paid', $platformCustomer->is_paid ?? false))> Cliente pago</label></div>

                            <div class="col-md-2">
                                <label class="form-label">POS</label>
                                <select class="form-select" name="pos_mode" @disabled($user->company)>
                                    <option value="mono" @selected(old('pos_mode', $platformCustomer->pos_mode ?? 'mono')==='mono')>MonoCaja</option>
                                    <option value="multi" @selected(old('pos_mode', $platformCustomer->pos_mode ?? 'mono')==='multi')>MultiCaja</option>
                                </select>
                            </div>
                            <div class="col-md-2"><label class="form-label">Cantidad de cajas</label><input type="number" class="form-control" min="1" max="30" name="pos_boxes" @disabled($user->company) value="{{ old('pos_boxes', $platformCustomer->pos_boxes ?? 1) }}"></div>

                            <div class="col-12 mt-2">
                                <button class="btn btn-outline-primary" @disabled($user->company)>Guardar activación de servicios</button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/admin/user/edit.js') }}"></script>
@endpush
