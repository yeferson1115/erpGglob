@extends('layouts.app')

@section('content')
<div class="card mb-6">
    <div class="row card-header flex-column flex-md-row border-bottom mx-0 px-3 mb-3">
        <div class="d-md-flex justify-content-between align-items-center col-md-auto me-auto mt-0">
            <h5 class="card-title mb-0">Negocios</h5>
        </div>
        <div class="d-md-flex justify-content-between align-items-center col-md-auto ms-auto mt-0">
            <a class="btn btn-primary" href="{{ route('companies.create') }}"><i class="icon-base ti tabler-plus icon-sm"></i> Nuevo negocio</a>
        </div>
    </div>

    <div class="px-3 pb-2">
        <form method="GET" class="row g-2">
            <div class="col-md-3">
                <select name="service_status" class="form-select">
                    <option value="">Todos los estados</option>
                    <option value="active" @selected(($status ?? '') === 'active')>Activos</option>
                    <option value="inactive" @selected(($status ?? '') === 'inactive')>Inactivos</option>
                    <option value="suspended" @selected(($status ?? '') === 'suspended')>Suspendidos</option>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-outline-primary">Filtrar</button></div>
        </form>
    </div>

    @if (session('success'))
        <div class="px-3"><div class="alert alert-success">{{ session('success') }}</div></div>
    @endif

    <div class="table-responsive text-nowrap px-3 pb-3">
        <table class="table table-striped" id="datatables">
            <thead>
                <tr>
                    <th>Negocio</th>
                    <th>Dueño</th>
                    <th>Cajeros</th>
                    <th>Plan</th>
                    <th>Estado</th>
                    <th>Servicios</th>
                    <th>Vigencia</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($companies as $company)
                    @php $owner = $company->owners->first(); @endphp
                    <tr>
                        <td>{{ $company->name }}<br><small>{{ $company->nit }}</small></td>
                        <td>{{ $owner?->name }} {{ $owner?->last_name }}<br><small>{{ $owner?->email }}</small></td>
                        <td>{{ $company->cashiers->count() }}</td>
                        <td>{{ $company->plan_name }}</td>
                        <td><span class="badge bg-{{ $company->service_status === 'active' ? 'success' : ($company->service_status === 'suspended' ? 'warning' : 'secondary') }}">{{ strtoupper($company->service_status) }}</span></td>
                        <td class="small">
                            {{ $company->gglob_cloud_enabled ? 'Nube ' : '' }}
                            {{ $company->gglob_pay_enabled ? 'Pay ' : '' }}
                            {{ $company->gglob_pos_enabled ? 'POS('.($company->pos_mode === 'multi' ? 'Multi x'.$company->pos_boxes : 'Mono').') ' : '' }}
                            {{ ($company->pos_locations_count ?? 0) > 0 ? 'PDV x'.$company->pos_locations_count.' ' : '' }}
                            {{ $company->gglob_accounting_enabled ? 'Contable' : '' }}
                        </td>
                        <td>{{ optional($company->active_until)->format('d/m/Y') ?? '-' }}</td>
                        <td class="d-flex gap-2">
                            <a href="{{ route('companies.edit', $company) }}" class="btn btn-sm btn-secondary">Gestionar</a>
                            <form action="{{ route('companies.destroy', $company) }}" method="POST" onsubmit="return confirm('¿Eliminar negocio?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center">No hay negocios registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="px-3 pb-3">{{ $companies->links() }}</div>
</div>
@endsection
