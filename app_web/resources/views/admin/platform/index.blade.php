@extends('layouts.app')

@section('title', 'Panel Plataforma Gglob')
@section('page_title', 'Panel Plataforma Gglob')

@section('content')
<div class="content-body mt-4 platform-theme">
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card platform-card p-3"><small>Clientes</small><h3>{{ $totals['users'] }}</h3></div></div>
        <div class="col-md-3"><div class="card platform-card p-3"><small>Activos</small><h3>{{ $totals['active'] }}</h3></div></div>
        <div class="col-md-3"><div class="card platform-card p-3"><small>Ventas Totales</small><h3>${{ number_format($totals['total_sales'], 0, ',', '.') }}</h3></div></div>
        <div class="col-md-3"><div class="card platform-card p-3"><small>Gglob Pay / POS</small><h3>${{ number_format($totals['total_gglob_pay'], 0, ',', '.') }} / ${{ number_format($totals['total_gglob_pos'], 0, ',', '.') }}</h3></div></div>
    </div>

    <ul class="nav nav-pills mb-3" id="platform-tabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#users-tab" type="button">Usuarios</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#visual-tab" type="button">Panel visual</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#marketing-tab" type="button">Marketing</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#promotions-tab" type="button">Promociones</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#catalog-tab" type="button">Base de productos</button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="users-tab">
            <div class="card mb-3"><div class="card-header">Crear usuario y habilitar cliente</div><div class="card-body">
                <form method="POST" action="{{ route('admin.platform.users.store') }}" class="row g-3">
                    @csrf
                    <div class="col-md-3"><input name="name" class="form-control" placeholder="Nombre" required></div>
                    <div class="col-md-3"><input name="last_name" class="form-control" placeholder="Apellido"></div>
                    <div class="col-md-3"><input type="email" name="email" class="form-control" placeholder="Correo" required></div>
                    <div class="col-md-3"><input name="contact_phone" class="form-control" placeholder="Teléfono"></div>
                    <div class="col-md-3"><input type="password" name="password" class="form-control" placeholder="Contraseña" required></div>
                    <div class="col-md-3"><input type="password" name="password_confirmation" class="form-control" placeholder="Confirmar contraseña" required></div>
                    <div class="col-md-3"><input name="plan_name" class="form-control" placeholder="Plan (ej: Premium)"></div>
                    <div class="col-md-3 d-flex align-items-center"><label><input type="checkbox" name="is_paid" value="1"> Cliente pago</label></div>
                    <div class="col-12"><button class="btn btn-primary">Crear usuario</button></div>
                </form>
            </div></div>
        </div>

        <div class="tab-pane fade" id="visual-tab">
            @foreach($customers as $customer)
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between">
                        <strong>{{ $customer->user->name }} {{ $customer->user->last_name }}</strong>
                        <span>{{ $customer->user->email }}</span>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.platform.customers.update', $customer) }}" class="row g-2">
                            @csrf @method('PUT')
                            <div class="col-md-2"><input class="form-control" name="plan_name" value="{{ $customer->plan_name }}"></div>
                            <div class="col-md-2">
                                <select class="form-select" name="subscription_status">
                                    @foreach(['active'=>'Activo','inactive'=>'Inactivo','suspended'=>'Suspendido'] as $value => $label)
                                        <option value="{{ $value }}" @selected($customer->subscription_status===$value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2"><input type="date" class="form-control" name="started_at" value="{{ optional($customer->started_at)->format('Y-m-d') }}"></div>
                            <div class="col-md-2"><input type="date" class="form-control" name="active_until" value="{{ optional($customer->active_until)->format('Y-m-d') }}"></div>
                            <div class="col-md-2"><input type="number" class="form-control" name="pos_boxes" min="1" value="{{ $customer->pos_boxes }}"></div>
                            <div class="col-md-2">
                                <select class="form-select" name="pos_mode"><option value="mono" @selected($customer->pos_mode==='mono')>MonoCaja</option><option value="multi" @selected($customer->pos_mode==='multi')>MultiCaja</option></select>
                            </div>
                            <div class="col-md-2"><label><input type="checkbox" name="gglob_cloud_enabled" value="1" @checked($customer->gglob_cloud_enabled)> Nube</label></div>
                            <div class="col-md-2"><label><input type="checkbox" name="gglob_pay_enabled" value="1" @checked($customer->gglob_pay_enabled)> Gglob Pay</label></div>
                            <div class="col-md-2"><label><input type="checkbox" name="gglob_pos_enabled" value="1" @checked($customer->gglob_pos_enabled)> Gglob POS</label></div>
                            <div class="col-md-2"><label><input type="checkbox" name="gglob_accounting_enabled" value="1" @checked($customer->gglob_accounting_enabled)> Contable</label></div>
                            <div class="col-md-2"><label><input type="checkbox" name="electronic_billing_enabled" value="1" @checked($customer->electronic_billing_enabled)> Facturación electrónica</label></div>
                            <div class="col-md-2"><label><input type="checkbox" name="is_paid" value="1" @checked($customer->is_paid)> Pago activo</label></div>
                            <div class="col-md-3"><select class="form-select" name="electronic_billing_scope"><option value="single_branch" @selected($customer->electronic_billing_scope==='single_branch')>1 sucursal</option><option value="multi_branch" @selected($customer->electronic_billing_scope==='multi_branch')>Varias sucursales</option></select></div>
                            <div class="col-md-3"><input type="number" name="electronic_billing_boxes" class="form-control" value="{{ $customer->electronic_billing_boxes }}"></div>
                            <div class="col-md-3"><input type="number" name="electronic_billing_monthly_limit" class="form-control" value="{{ $customer->electronic_billing_monthly_limit }}" placeholder="Límite mensual"></div>
                            <div class="col-md-3"><select class="form-select" name="electronic_billing_status"><option value="active" @selected($customer->electronic_billing_status==='active')>Activa</option><option value="suspended" @selected($customer->electronic_billing_status==='suspended')>Suspendida</option><option value="pending" @selected($customer->electronic_billing_status==='pending')>Pendiente</option></select></div>
                            <div class="col-md-12 small text-muted">Docs: emitidos {{ $customer->electronic_docs_issued }} | enviados {{ $customer->electronic_docs_sent }} | aceptados {{ $customer->electronic_docs_accepted }} | rechazados {{ $customer->electronic_docs_rejected }} | pendientes {{ $customer->electronic_docs_pending }}</div>
                            <div class="col-md-12"><button class="btn btn-outline-primary btn-sm">Guardar cambios</button></div>
                        </form>
                    </div>
                </div>
            @endforeach
            {{ $customers->links() }}
        </div>

        <div class="tab-pane fade" id="marketing-tab">
            <div class="card mb-3"><div class="card-header">Panel de marketing (automatizado)</div><div class="card-body">
                <form method="POST" action="{{ route('admin.platform.marketing.store') }}" class="row g-2">@csrf
                    <div class="col-md-2"><select name="channel" class="form-select"><option value="email">Correo</option><option value="whatsapp">WhatsApp</option><option value="banner">Banner APP</option><option value="sms">SMS</option></select></div>
                    <div class="col-md-2"><select name="audience_type" class="form-select"><option value="segment">Segmento</option><option value="user">Usuario</option></select></div>
                    <div class="col-md-2"><input name="audience_segment" class="form-control" placeholder="Activos / Inactivos"></div>
                    <div class="col-md-3"><select name="user_id" class="form-select"><option value="">Individual (opcional)</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }} {{ $user->last_name }}</option>@endforeach</select></div>
                    <div class="col-md-2"><select name="frequency" class="form-select"><option value="daily">Diario</option><option value="weekly">Semanal</option><option value="monthly">Mensual</option><option value="specific_date">Fecha puntual</option></select></div>
                    <div class="col-md-2"><input type="datetime-local" name="scheduled_for" class="form-control"></div>
                    <div class="col-md-12"><textarea name="message" class="form-control" rows="3" placeholder="Incluye comparativo de últimas 4 semanas: ventas totales, productos más vendidos y cantidad de ventas" required></textarea></div>
                    <div class="col-md-3"><label><input type="checkbox" name="is_automated" value="1" checked> Envío automático</label></div>
                    <div class="col-md-12"><button class="btn btn-primary">Programar envío</button></div>
                </form>
            </div></div>
            <div class="card"><div class="card-header">Últimos envíos</div><div class="card-body small">
                @forelse($recentBroadcasts as $item)
                    <div>{{ $item->created_at->format('d/m/Y H:i') }} - {{ strtoupper($item->channel) }} - {{ $item->frequency ?? 'sin frecuencia' }} - {{ \Illuminate\Support\Str::limit($item->message, 80) }}</div>
                @empty <div>No hay envíos registrados.</div> @endforelse
            </div></div>
        </div>

        <div class="tab-pane fade" id="promotions-tab">
            <div class="card mb-3"><div class="card-header">Panel de promociones (WOMPI botones + API)</div><div class="card-body">
                <form method="POST" action="{{ route('admin.platform.promotions.store') }}" class="row g-2">@csrf
                    <div class="col-md-2"><input name="code" class="form-control" placeholder="Código" required></div>
                    <div class="col-md-2"><select name="discount_type" class="form-select"><option value="percentage">%</option><option value="fixed">Valor fijo</option></select></div>
                    <div class="col-md-2"><input step="0.01" type="number" name="discount_value" class="form-control" placeholder="Valor" required></div>
                    <div class="col-md-2"><input name="target_service" class="form-control" placeholder="Servicio objetivo"></div>
                    <div class="col-md-2"><input name="wompi_rule" class="form-control" placeholder="Regla WOMPI"></div>
                    <div class="col-md-2"><input type="datetime-local" name="expires_at" class="form-control"></div>
                    <div class="col-md-12"><textarea name="notes" class="form-control" rows="2" placeholder="Notas para aumentar, disminuir o quitar servicios"></textarea></div>
                    <div class="col-md-3"><label><input type="checkbox" name="is_active" value="1" checked> Activo</label></div>
                    <div class="col-md-12"><button class="btn btn-primary">Crear código</button></div>
                </form>
            </div></div>
            <div class="card"><div class="card-header">Códigos recientes</div><div class="card-body small">@forelse($promotionCodes as $promo)<div>{{ $promo->code }} - {{ $promo->discount_value }} {{ $promo->discount_type }} - {{ $promo->target_service }}</div>@empty<div>No hay códigos.</div>@endforelse</div></div>
        </div>

        <div class="tab-pane fade" id="catalog-tab">
            <div class="card mb-3"><div class="card-header">Publicar base de datos de productos automatizada</div><div class="card-body">
                <form method="POST" action="{{ route('admin.platform.catalog.store') }}" class="row g-2">@csrf
                    <div class="col-md-3"><select name="category" class="form-select"><option>Supermercado</option><option>Cafetería</option><option>Electrodomésticos</option><option>Otros</option></select></div>
                    <div class="col-md-4"><input name="title" class="form-control" placeholder="Nombre publicación" required></div>
                    <div class="col-md-5"><textarea name="description" class="form-control" rows="2" placeholder="Descripción"></textarea></div>
                    <div class="col-md-3"><label><input type="checkbox" name="is_published" value="1" checked> Publicar ahora</label></div>
                    <div class="col-md-12"><button class="btn btn-primary">Publicar base</button></div>
                </form>
            </div></div>
            <div class="card"><div class="card-header">Publicaciones recientes</div><div class="card-body small">@forelse($catalogPublications as $item)<div>{{ $item->title }} - {{ $item->category }} - {{ $item->published_at?->format('d/m/Y H:i') }}</div>@empty<div>No hay publicaciones.</div>@endforelse</div></div>
        </div>
    </div>
</div>

<style>
    .platform-theme .platform-card {
        border: 0;
        color: #fff;
        border-radius: 14px;
        background: linear-gradient(135deg, #0f3f95, #1f77d0 60%, #25c5f4);
    }
    .platform-theme .nav-pills .nav-link.active {
        background: linear-gradient(135deg, #0f3f95, #25c5f4);
    }
</style>
@endsection
