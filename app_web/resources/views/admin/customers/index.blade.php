@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4>Clientes</h4>
        <a href="{{ route('customers.create') }}" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> Nuevo Cliente
        </a>
    </div>

    <!-- Tabla de clientes -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="datatables">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>Documento</th>
                            <th>Teléfono</th>
                            <th>Email</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($customers as $customer)
                        <tr>
                            <td>{{ $customer->id }}</td>
                            <td>{{ $customer->name }}</td>
                            <td>{{ $customer->document ?? 'N/A' }}</td>
                            <td>{{ $customer->phone ?? 'N/A' }}</td>
                            <td>{{ $customer->email ?? 'N/A' }}</td>
                            <td>                               
                                <a href="{{ route('customers.edit', $customer) }}" class="btn btn-sm btn-warning">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                                <form action="{{ route('customers.destroy', $customer) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar este cliente?')">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <!-- Paginación -->
           
        </div>
    </div>

    <!-- Tabla de clientes eliminados -->
    @php
        $deletedCustomers = \App\Models\Customer::onlyTrashed()->get();
    @endphp
    @if($deletedCustomers->count() > 0)
    <!--<div class="card mt-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">Clientes Eliminados</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm" id="datatables">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Documento</th>
                            <th>Teléfono</th>
                            <th>Eliminado el</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($deletedCustomers as $customer)
                        <tr>
                            <td>{{ $customer->name }}</td>
                            <td>{{ $customer->document ?? 'N/A' }}</td>
                            <td>{{ $customer->phone ?? 'N/A' }}</td>
                            <td>{{ $customer->deleted_at->format('d/m/Y H:i') }}</td>
                            <td>
                                <a href="{{ route('customers.restore', $customer->id) }}" class="btn btn-sm btn-success" onclick="return confirm('¿Restaurar este cliente?')">
                                    <i class="fa-solid fa-rotate-left"></i> Restaurar
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>-->
    @endif
</div>
@endsection