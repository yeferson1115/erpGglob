@extends('layouts.app')

@section('title', 'Categorías de producto')

@section('content')
<div class="row g-4">
    <div class="col-12 col-xl-5">
        <div class="card">
            <div class="card-header">{{ $editingCategory ? 'Editar categoría' : 'Nueva categoría' }}</div>
            <div class="card-body">
                <form method="POST" action="{{ $editingCategory ? route('product-categories.update', $editingCategory) : route('product-categories.store') }}" class="row g-3">
                    @csrf
                    @if($editingCategory)
                        @method('PUT')
                    @endif

                    <div class="col-12">
                        <label class="form-label">Nombre de la categoría</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $editingCategory?->name) }}" required maxlength="120">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Descripción</label>
                        <textarea name="description" rows="3" class="form-control" maxlength="500">{{ old('description', $editingCategory?->description) }}</textarea>
                    </div>

                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="isActive" @checked(old('is_active', $editingCategory?->is_active ?? true))>
                            <label class="form-check-label" for="isActive">Categoría activa</label>
                        </div>
                    </div>

                    <div class="col-12 d-flex gap-2">
                        <button class="btn btn-primary">{{ $editingCategory ? 'Actualizar categoría' : 'Crear categoría' }}</button>
                        @if($editingCategory)
                            <a class="btn btn-outline-secondary" href="{{ route('product-categories.index') }}">Cancelar</a>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-7">
        <div class="card">
            <div class="card-header">Listado de categorías del negocio</div>
            <div class="card-body table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categories as $category)
                            <tr>
                                <td>{{ $category->name }}</td>
                                <td>{{ $category->description ?: '—' }}</td>
                                <td>
                                    <span class="badge {{ $category->is_active ? 'bg-label-success' : 'bg-label-secondary' }}">
                                        {{ $category->is_active ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('product-categories.edit', $category) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                                    <form method="POST" action="{{ route('product-categories.destroy', $category) }}" class="d-inline" onsubmit="return confirm('¿Eliminar esta categoría?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">No hay categorías registradas todavía.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
