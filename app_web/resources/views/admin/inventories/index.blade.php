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
            <div class="col-md-6">
                <label class="form-label">Categoría del producto</label>
                <select name="product_category_id" class="form-select">
                    <option value="">-- Seleccionar categoría --</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) old('product_category_id', $editingProduct?->product_category_id) === (string) $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Precio del producto</label>
                <input type="number" step="0.01" min="0" name="price" class="form-control" value="{{ old('price', $editingProduct?->price) }}" required>
            </div>

            <div class="col-12">
                <label class="form-check-label">
                    <input id="tracks_inventory_check" class="form-check-input me-2" type="checkbox" name="tracks_inventory" value="1" @checked(old('tracks_inventory', $editingProduct?->tracks_inventory))>
                    El producto tiene inventario de existencias
                </label>
            </div>

            <div class="col-md-6 inventory-field">
                <label class="form-label">Número de existencias</label>
                <input type="number" min="0" name="stock_quantity" class="form-control" value="{{ old('stock_quantity', $editingProduct?->stock_quantity) }}">
            </div>
            <div class="col-md-6 inventory-field">
                <label class="form-label">Existencias mínimas</label>
                <input type="number" min="0" name="minimum_stock" class="form-control" value="{{ old('minimum_stock', $editingProduct?->minimum_stock) }}">
                <small class="text-muted">Cuando se alcance este valor, se recomienda reabastecer con proveedores.</small>
            </div>

            <div class="col-12">
                <label class="form-check-label">
                    <input id="is_combo_check" class="form-check-input me-2" type="checkbox" name="is_combo" value="1" @checked(old('is_combo', $editingProduct?->is_combo))>
                    Producto tipo KIT / COMBO
                </label>
            </div>
            <div class="col-12 combo-field">
                <label class="form-label">Códigos incluidos en el combo (separados por coma)</label>
                <textarea name="combo_product_codes" rows="2" class="form-control" placeholder="COD-001, COD-002">{{ old('combo_product_codes', $editingProduct ? implode(', ', $editingProduct->combo_product_codes ?? []) : '') }}</textarea>
            </div>
            <div class="col-md-6 combo-field">
                <label class="form-label">Filtrar productos por categoría</label>
                <select id="combo_category_filter" class="form-select">
                    <option value="">-- Todas las categorías --</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 combo-field">
                <label class="form-label">Seleccionar productos para el combo</label>
                @php
                    $selectedCodes = collect(old('combo_product_codes', $editingProduct ? implode(', ', $editingProduct->combo_product_codes ?? []) : ''))
                        ->flatMap(fn ($codes) => explode(',', $codes))
                        ->map(fn ($code) => trim($code))
                        ->filter()
                        ->values()
                        ->all();
                @endphp
                <select name="combo_product_ids[]" class="form-select" multiple size="6" id="combo_products_select">
                    @foreach($products as $product)
                        @if(!$editingProduct || $product->id !== $editingProduct->id)
                            <option
                                value="{{ $product->id }}"
                                data-category-id="{{ $product->product_category_id }}"
                                @selected(in_array($product->code, $selectedCodes, true))
                            >
                                [{{ $product->code }}] {{ $product->name }}
                                @if($product->category)
                                    - {{ $product->category->name }}
                                @endif
                            </option>
                        @endif
                    @endforeach
                </select>
                <small class="text-muted">También puedes elegir productos desde la lista por categoría.</small>
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
                    <th>Categoría</th>
                    <th>Precio</th>
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
                    <td>{{ $product->category?->name ?? 'Sin categoría' }}</td>
                    <td>${{ number_format((float) $product->price, 2, '.', ',') }}</td>
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
                <tr><td colspan="7">No hay productos registrados.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const tracksInventoryCheck = document.getElementById('tracks_inventory_check');
        const inventoryFields = document.querySelectorAll('.inventory-field input');
        const isComboCheck = document.getElementById('is_combo_check');
        const comboFields = document.querySelectorAll('.combo-field');
        const comboCategoryFilter = document.getElementById('combo_category_filter');
        const comboProductsSelect = document.getElementById('combo_products_select');

        const toggleInventoryFields = () => {
            const enabled = tracksInventoryCheck?.checked;
            inventoryFields.forEach((field) => {
                field.disabled = !enabled;
            });
        };

        const toggleComboFields = () => {
            const enabled = isComboCheck?.checked;
            comboFields.forEach((field) => {
                field.style.display = enabled ? '' : 'none';
                field.querySelectorAll('textarea, select').forEach((input) => {
                    input.disabled = !enabled;
                });
            });
        };

        const filterComboOptions = () => {
            if (!comboCategoryFilter || !comboProductsSelect) {
                return;
            }

            const selectedCategory = comboCategoryFilter.value;
            [...comboProductsSelect.options].forEach((option) => {
                const categoryId = option.dataset.categoryId || '';
                option.hidden = selectedCategory !== '' && categoryId !== selectedCategory;
            });
        };

        tracksInventoryCheck?.addEventListener('change', toggleInventoryFields);
        isComboCheck?.addEventListener('change', toggleComboFields);
        comboCategoryFilter?.addEventListener('change', filterComboOptions);

        toggleInventoryFields();
        toggleComboFields();
        filterComboOptions();
    });
</script>
@endpush
