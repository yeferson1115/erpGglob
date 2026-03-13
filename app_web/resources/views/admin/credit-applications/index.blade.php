@extends('layouts.app')

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card">
            <div class="card-header d-flex flex-column flex-md-row justify-content-between gap-3">
                <div>
                    <h4 class="mb-0">Solicitudes de crédito</h4>
                    <small class="text-muted">Consulta, revisa y gestiona el estado de cada solicitud.</small>
                </div>
            </div>

            <div class="card-body border-bottom">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Buscar</label>
                        <input type="text" class="form-control" name="search" value="{{ $search }}" placeholder="Nombre, documento, celular o token">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Estado</label>
                        <select class="form-select" name="status">
                            <option value="">Todos</option>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected($statusFilter === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button class="btn btn-primary" type="submit">Filtrar</button>
                        <a href="{{ route('admin.credit-applications.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Cliente</th>
                            <th>Documento</th>
                            <th>Celular</th>
                            <th>Empresa</th>
                            <th>Estado</th>
                            <th>Actualizada</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($applications as $application)
                            <tr>
                                <td>{{ $application->id }}</td>
                                <td>{{ $application->full_name ?: 'Sin nombre' }}</td>
                                <td>{{ $application->document_number ?: '—' }}</td>
                                <td>{{ $application->phone_primary ?: '—' }}</td>
                                <td>{{ $application->company?->name ?: '—' }}</td>
                                <td>
                                    <span class="badge bg-label-primary">{{ $statuses[$application->status] ?? ucfirst($application->status) }}</span>
                                </td>
                                <td>{{ optional($application->updated_at)->format('d/m/Y H:i') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.credit-applications.show', $application) }}" class="btn btn-sm btn-outline-primary">Ver detalle</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">No hay solicitudes para los filtros seleccionados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="card-footer">
                {{ $applications->links() }}
            </div>
        </div>
    </div>
@endsection
