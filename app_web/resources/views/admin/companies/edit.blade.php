@extends('layouts.app')

@section('content')
<div class="card mb-6">
    <h5 class="card-header">Editar empresa</h5>
    <form method="POST" action="{{ route('companies.update', $company) }}" class="card-body">
        @csrf
        @method('PUT')

        <div class="row g-6">
            <div class="col-md-6">
                <label class="form-label">Nombre empresa</label>
                <input name="name" class="form-control" value="{{ old('name', $company->name) }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">NIT</label>
                <input name="nit" class="form-control" value="{{ old('nit', $company->nit) }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Dirección</label>
                <input name="address" class="form-control" value="{{ old('address', $company->address) }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="{{ old('email', $company->email) }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Nombre persona encargada</label>
                <input name="contact_name" class="form-control" value="{{ old('contact_name', $company->contact_name) }}" required>
            </div>
        </div>

        <div class="pt-6 d-flex gap-2">
            <button class="btn btn-primary">Actualizar</button>
            <a href="{{ route('companies.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
</div>
@endsection
