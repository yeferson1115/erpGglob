@extends('layouts.app')

@section('content')
<div class="card mb-6">
    <div class="row card-header flex-column flex-md-row border-bottom mx-0 px-3 mb-3">
        <div class="d-md-flex justify-content-between align-items-center dt-layout-start col-md-auto me-auto mt-0">
            <h5 class="card-title mb-0 text-md-start text-center pb-md-0 pb-6">Empresas</h5>
        </div>

        <div class="d-md-flex justify-content-between align-items-center dt-layout-end col-md-auto ms-auto mt-0">
            <div class="dt-buttons btn-group flex-wrap mb-0">
                <a class="btn create-new btn-primary" href="{{ route('companies.create') }}">
                    <span class="d-flex align-items-center gap-2">
                        <i class="icon-base ti tabler-plus icon-sm"></i>
                        <span class="d-none d-sm-inline-block">Nueva empresa</span>
                    </span>
                </a>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="px-3">
            <div class="alert alert-success">{{ session('success') }}</div>
        </div>
    @endif

    <div class="table-responsive text-nowrap px-3 pb-3">
        <table class="table table-striped" id="datatables">
            <thead>
                <tr>
                    <th>Nombre empresa</th>
                    <th>NIT</th>
                    <th>Dirección</th>
                    <th>Email</th>
                    <th>Persona encargada</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($companies as $company)
                    <tr>
                        <td>{{ $company->name }}</td>
                        <td>{{ $company->nit }}</td>
                        <td>{{ $company->address }}</td>
                        <td>{{ $company->email }}</td>
                        <td>{{ $company->contact_name }}</td>
                        <td class="d-flex gap-2">
                            <a href="{{ route('companies.edit', $company) }}" class="btn btn-sm btn-secondary">Editar</a>
                            <form action="{{ route('companies.destroy', $company) }}" method="POST" onsubmit="return confirm('¿Eliminar empresa?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center">No hay empresas registradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="px-3 pb-3">
        {{ $companies->links() }}
    </div>
</div>
@endsection
