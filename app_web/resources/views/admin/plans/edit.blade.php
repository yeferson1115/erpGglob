@extends('layouts.app')

@section('content')
<div class="card mb-6">
    <h5 class="card-header">Editar plan</h5>
    <form method="POST" action="{{ route('plans.update', $plan) }}" class="card-body">
        @csrf
        @method('PUT')
        @include('admin.plans._form')

        <div class="pt-4 d-flex gap-2">
            <button class="btn btn-primary">Guardar cambios</button>
            <a href="{{ route('plans.index') }}" class="btn btn-outline-secondary">Cancelar</a>
        </div>
    </form>
</div>
@endsection
