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
        <div class="col-md-3"><div class="card platform-card p-3"><small>Pagos / Sin pago</small><h3>{{ $totals['paid_users'] }} / {{ $totals['unpaid_users'] }}</h3></div></div>
        <div class="col-md-3"><div class="card platform-card p-3"><small>Activos / Inactivos</small><h3>{{ $totals['active'] }} / {{ $totals['inactive'] }}</h3></div></div>
        <div class="col-md-3"><div class="card platform-card p-3"><small>Ventas (Total / Pay / POS)</small><h3>${{ number_format($totals['total_sales'], 0, ',', '.') }}</h3><small>${{ number_format($totals['total_gglob_pay'], 0, ',', '.') }} / ${{ number_format($totals['total_gglob_pos'], 0, ',', '.') }}</small></div></div>
    </div>

    <ul class="nav nav-pills mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#visual-tab" type="button">Panel visual</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#marketing-tab" type="button">Marketing</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#promotions-tab" type="button">Promociones</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#catalog-tab" type="button">Base de productos</button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="visual-tab">
            <div class="card mb-3">
                <div class="card-body">
                    <form class="row g-2" method="GET" action="{{ route('admin.platform.index') }}">
                        <div class="col-md-3">
                            <select name="status" class="form-select">
                                <option value="">Todos los estados</option>
                                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Activos</option>
                                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactivos</option>
                                <option value="suspended" @selected(($filters['status'] ?? '') === 'suspended')>Suspendidos</option>
                            </select>
                        </div>
                        <div class="col-md-3"><input type="date" name="started_from" value="{{ $filters['started_from'] ?? '' }}" class="form-control" placeholder="Desde"></div>
                        <div class="col-md-3"><input type="date" name="started_to" value="{{ $filters['started_to'] ?? '' }}" class="form-control" placeholder="Hasta"></div>
                        <div class="col-md-3"><button class="btn btn-primary">Filtrar panel visual</button></div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Datos de usuarios (incluye usuarios sin plan activo o no pagos)</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Contacto</th>
                            <th>Plan / Estado</th>
                            <th>Desde usa software</th>
                            <th>Ventas total</th>
                            <th>Gglob Pay</th>
                            <th>Gglob POS</th>
                            <th># Ventas</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($customers as $customer)
                            <tr>
                                <td>{{ $customer->user->name }} {{ $customer->user->last_name }}<br><small>{{ $customer->user->email }}</small></td>
                                <td>{{ $customer->contact_phone ?: '-' }}</td>
                                <td>{{ $customer->plan_name }} / <span class="badge bg-secondary">{{ $customer->subscription_status }}</span><br><small>{{ $customer->is_paid ? 'Pago' : 'No pago' }}</small></td>
                                <td>{{ optional($customer->started_at)->format('d/m/Y') ?? '-' }}</td>
                                <td>${{ number_format($customer->sales_total, 0, ',', '.') }}</td>
                                <td>${{ number_format($customer->sales_gglob_pay, 0, ',', '.') }}</td>
                                <td>${{ number_format($customer->sales_gglob_pos, 0, ',', '.') }}</td>
                                <td>{{ $customer->sales_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8">No hay datos</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-2">{{ $customers->links() }}</div>
            </div>
        </div>

        <div class="tab-pane fade" id="marketing-tab">
            <div class="card mb-3"><div class="card-header">Panel de marketing (automatizado)</div><div class="card-body">
                <form method="POST" action="{{ route('admin.platform.marketing.store') }}" class="row g-2">@csrf
                    <div class="col-md-2"><select name="channel" class="form-select"><option value="email">Correo</option><option value="whatsapp">WhatsApp</option><option value="banner">Mensaje superior APP</option><option value="sms">SMS</option></select></div>
                    <div class="col-md-2"><select name="audience_type" class="form-select"><option value="segment">Categoría</option><option value="user">Usuario</option></select></div>
                    <div class="col-md-3"><input name="audience_segment" class="form-control" placeholder="Activos / Inactivos / No pagos"></div>
                    <div class="col-md-3"><select name="user_id" class="form-select"><option value="">Usuario individual (opcional)</option>@foreach($usersList as $user)<option value="{{ $user->id }}">{{ $user->name }} {{ $user->last_name }}</option>@endforeach</select></div>
                    <div class="col-md-2"><select name="frequency" class="form-select"><option value="daily">Diario</option><option value="weekly">Semanal</option><option value="monthly">Mensual</option><option value="specific_date">Fecha puntual</option></select></div>
                    <div class="col-md-3"><input type="datetime-local" name="scheduled_for" class="form-control"></div>
                    <div class="col-md-12"><textarea name="message" class="form-control" rows="3" placeholder="Contenido comparativo últimas 4 semanas: ventas totales, productos más vendidos y cantidad de ventas" required></textarea></div>
                    <div class="col-md-3"><label><input type="checkbox" name="is_automated" value="1" checked> Envío automático</label></div>
                    <div class="col-md-12"><button class="btn btn-primary">Programar envío</button></div>
                </form>
            </div></div>
            <div class="card"><div class="card-header">Últimos envíos</div><div class="card-body small">@forelse($recentBroadcasts as $item)<div>{{ $item->created_at->format('d/m/Y H:i') }} - {{ strtoupper($item->channel) }} - {{ $item->frequency ?? 'sin frecuencia' }} - {{ \Illuminate\Support\Str::limit($item->message, 100) }}</div>@empty<div>No hay envíos.</div>@endforelse</div></div>
        </div>

        <div class="tab-pane fade" id="promotions-tab">
            <div class="card mb-3"><div class="card-header">Panel de promociones (WOMPI + gestión de servicios)</div><div class="card-body">
                <form method="POST" action="{{ route('admin.platform.promotions.store') }}" class="row g-2">@csrf
                    <div class="col-md-2"><input name="code" class="form-control" placeholder="Código" required></div>
                    <div class="col-md-2"><select name="discount_type" class="form-select"><option value="percentage">%</option><option value="fixed">Valor fijo</option></select></div>
                    <div class="col-md-2"><input step="0.01" type="number" name="discount_value" class="form-control" placeholder="Valor" required></div>
                    <div class="col-md-2"><input name="target_service" class="form-control" placeholder="Servicio objetivo"></div>
                    <div class="col-md-2"><select name="service_action" class="form-select"><option value="">Acción servicio</option><option value="increase">Aumentar</option><option value="decrease">Disminuir</option><option value="remove">Quitar</option></select></div>
                    <div class="col-md-2"><select name="target_customer_id" class="form-select"><option value="">Cliente activo</option>@foreach($activeCustomers as $customer)<option value="{{ $customer->id }}">{{ $customer->user->name }} {{ $customer->user->last_name }}</option>@endforeach</select></div>
                    <div class="col-md-3"><input name="wompi_rule" class="form-control" placeholder="Regla WOMPI"></div>
                    <div class="col-md-3"><input type="datetime-local" name="expires_at" class="form-control"></div>
                    <div class="col-md-12"><textarea name="notes" class="form-control" rows="2" placeholder="Notas operativas"></textarea></div>
                    <div class="col-md-3"><label><input type="checkbox" name="is_active" value="1" checked> Activo</label></div>
                    <div class="col-md-12"><button class="btn btn-primary">Crear código</button></div>
                </form>
            </div></div>
            <div class="card"><div class="card-header">Códigos recientes</div><div class="card-body small">@forelse($promotionCodes as $promo)<div>{{ $promo->code }} - {{ $promo->discount_value }} {{ $promo->discount_type }} - {{ $promo->target_service }} - {{ $promo->customer?->user?->email ?? 'sin cliente' }}</div>@empty<div>No hay códigos.</div>@endforelse</div></div>
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
.platform-theme .platform-card { border:0; color:#fff; border-radius:14px; background:linear-gradient(135deg,#0f3f95,#1f77d0 60%,#25c5f4); }
.platform-theme .nav-pills .nav-link.active { background:linear-gradient(135deg,#0f3f95,#25c5f4); }
</style>
@endsection
