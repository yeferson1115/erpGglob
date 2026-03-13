@extends('layouts.app')

@section('content')
<div class="card mb-6">
    <h5 class="card-header">Nueva empresa</h5>
    <form method="POST" action="{{ route('companies.store') }}" class="card-body">
        @csrf

        <div class="row g-6">
            <div class="col-md-6">
                <label class="form-label">Nombre empresa</label>
                <input name="name" class="form-control" value="{{ old('name') }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">NIT</label>
                <input name="nit" class="form-control" value="{{ old('nit') }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Dirección</label>
                <input name="address" class="form-control" value="{{ old('address') }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Nombre persona encargada</label>
                <input name="contact_name" class="form-control" value="{{ old('contact_name') }}" required>
            </div>
        </div>

        <div class="pt-6 d-flex gap-2">
            <button class="btn btn-primary">Guardar</button>
            <a href="{{ route('companies.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
</div>
@endsection
