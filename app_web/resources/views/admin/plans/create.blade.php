@extends('layouts.app')

@section('content')
<div class="card mb-6">
    <h5 class="card-header">Crear plan</h5>
    <form method="POST" action="{{ route('plans.store') }}" class="card-body">
        @csrf
        @include('admin.plans._form')

        <div class="pt-4 d-flex gap-2">
            <button class="btn btn-primary">Guardar plan</button>
            <a href="{{ route('plans.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
</div>
@endsection
