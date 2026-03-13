@extends('layouts.app')

@section('content')
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Inventarios</h5>
        @if($editingProduct)
            <a href="{{ route('inventories.index') }}" class="btn btn-outline-secondary btn-sm">Nuevo producto</a>
        @endif
    </div>
    <div class="card-body">
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $editingProduct ? '' : 'active' }}" type="button">Creación de producto</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ $editingProduct ? 'active' : '' }}" type="button">Modificación de productos</button>
            </li>
        </ul>

        <form method="POST" action="{{ $editingProduct ? route('inventories.update', $editingProduct) : route('inventories.store') }}" class="row g-3">
            @csrf
            @if($editingProduct)
                @method('PUT')
            @endif

            <div class="col-md-4">
                <label class="form-label">Código del producto</label>
                <input type="text" name="code" class="form-control" value="{{ old('code', $editingProduct?->code) }}" required>
            </div>
            <div class="col-md-8">
                <label class="form-label">Nombre del producto</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $editingProduct?->name) }}" required>
            </div>

            <div class="col-12">
                <label class="form-check-label">
                    <input class="form-check-input me-2" type="checkbox" name="tracks_inventory" value="1" @checked(old('tracks_inventory', $editingProduct?->tracks_inventory))>
                    El producto tiene inventario de existencias
                </label>
            </div>

            <div class="col-md-6">
                <label class="form-label">Número de existencias</label>
                <input type="number" min="0" name="stock_quantity" class="form-control" value="{{ old('stock_quantity', $editingProduct?->stock_quantity) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Existencias mínimas</label>
                <input type="number" min="0" name="minimum_stock" class="form-control" value="{{ old('minimum_stock', $editingProduct?->minimum_stock) }}">
                <small class="text-muted">Cuando se alcance este valor, se recomienda reabastecer con proveedores.</small>
            </div>

            <div class="col-12">
                <label class="form-check-label">
                    <input class="form-check-input me-2" type="checkbox" name="is_combo" value="1" @checked(old('is_combo', $editingProduct?->is_combo))>
                    Producto tipo KIT / COMBO
                </label>
            </div>
            <div class="col-12">
                <label class="form-label">Códigos incluidos en el combo (separados por coma)</label>
                <textarea name="combo_product_codes" rows="2" class="form-control" placeholder="COD-001, COD-002">{{ old('combo_product_codes', $editingProduct ? implode(', ', $editingProduct->combo_product_codes ?? []) : '') }}</textarea>
            </div>

            <div class="col-12">
                <button class="btn btn-primary">{{ $editingProduct ? 'Actualizar producto' : 'Crear producto' }}</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">Listado de productos</div>
    <div class="card-body table-responsive">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Inventario</th>
                    <th>Tipo</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
            @forelse($products as $product)
                <tr>
                    <td>{{ $product->code }}</td>
                    <td>{{ $product->name }}</td>
                    <td>
                        @if($product->tracks_inventory)
                            {{ $product->stock_quantity }} (mín: {{ $product->minimum_stock }})
                        @else
                            Ilimitado
                        @endif
                    </td>
                    <td>{{ $product->is_combo ? 'KIT / COMBO' : 'Producto normal' }}</td>
                    <td><a class="btn btn-sm btn-outline-primary" href="{{ route('inventories.edit', $product) }}">Editar</a></td>
                </tr>
            @empty
                <tr><td colspan="5">No hay productos registrados.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
