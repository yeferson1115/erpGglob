@extends('layouts.app')

@section('content')
<div class="container">

    <h3 class="mb-3">Reporte de Ventas</h3>

    <!-- FILTROS -->
    <form class="row g-2 mb-3">

        <div class="col-md-3">
            <label>Fecha Inicio</label>
            <input type="date" name="start" class="form-control" value="{{ request('start') }}">
        </div>

        <div class="col-md-3">
            <label>Fecha Final</label>
            <input type="date" name="end" class="form-control" value="{{ request('end') }}">
        </div>

        <div class="col-md-3">
            <label>Buscar</label>
            <input type="text" name="search" class="form-control" placeholder="ID, Mesa, Cliente"
                   value="{{ request('search') }}">
        </div>

        <div class="col-md-3 d-flex align-items-end">
            <button class="btn btn-primary me-2">Filtrar</button>

            <a href="{{ route('reports.sales.export', request()->all()) }}"
               class="btn btn-success">Exportar Excel</a>
        </div>
    </form>

    <!-- TABLA -->
    <div class="card p-3">
        <table class="table table-bordered table-hover">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Tipo</th>
                    <th>Mesa</th>
                    <th>Total</th>
                    <th>Pagado</th>
                    <th>Acciones</th>
                </tr>
            </thead>

            <tbody>
                @foreach($orders as $o)
                <tr>
                    <td>{{ $o->id }}</td>
                    <td>{{ $o->created_at->format('d/m/Y H:i') }}</td>

                    <td>
                        {{ $o->customer_name ?? ($o->customer->name ?? 'Cliente General') }}
                    </td>

                    <td>{{ ucfirst($o->type) }}</td>

                    <td>{{ $o->table->name ?? 'N/A' }}</td>

                    <td>${{ number_format($o->total,2) }}</td>

                    <td>
                        <span class="badge bg-{{ $o->paid ? 'success':'danger' }}">
                            {{ $o->paid ? 'SI':'NO' }}
                        </span>
                    </td>

                    <td>
                        <a href="{{ route('orders.edit',$o->id) }}" class="btn btn-sm btn-primary">Ver / Editar</a>
                        <a href="{{ route('orders.ticket',$o->id) }}" target="_blank" class="btn btn-sm btn-secondary">Ticket</a>
                    </td>
                </tr>
                @endforeach
            </tbody>

        </table>

        <div>
            {{ $orders->links() }}
        </div>
    </div>

</div>
@endsection
