@extends('layouts.app')
@section('content')
<div class="card mb-6">
  <h5 class="card-header">Editar Producto</h5>
  <form method="POST" action="{{ route('products.update', $product->id) }}" enctype="multipart/form-data" class="card-body">
    @csrf
    @method('PUT')
    <div class="row g-6">
      <div class="col-md-8">
        <div class="mb-3">
          <label>Nombre</label>
          <input name="name" class="form-control" value="{{ old('name', $product->name) }}" required>
        </div>

        <div class="mb-3">
          <label>Descripción</label>
          <textarea name="note" class="form-control">{{ old('note', $product->note) }}</textarea>
        </div>

        <div class="mb-3">
          <label>Precio</label>
          <input name="price" type="number" step="0.01" class="form-control" value="{{ old('price', $product->price) }}" required>
        </div>

        <div class="mb-3">
          <label>Categoría</label>
          <select name="category_id" class="form-control">
            <option value="">--</option>
            @foreach($categories as $c)
              <option value="{{ $c->id }}" {{ old('category_id', $product->category_id) == $c->id ? 'selected' : '' }}>
                {{ $c->name }}
              </option>
            @endforeach
          </select>
        </div>

        <!--<div class="mb-3 form-check">
          <input type="checkbox" name="single_option" value="1" class="form-check-input" id="singleOption"
            {{ old('single_option', $product->single_option) ? 'checked' : '' }}>
          <label class="form-check-label" for="singleOption">Opción única</label>
        </div>-->

        <h6>Adicionales (opcional)</h6>
        <div id="addonsContainer">
          @php
        $old_addons = old('addons', $product->addons->toArray() ?? []);
    @endphp
    @foreach($old_addons as $index => $addon)
        <div class="row mb-2 addon-row">
            <div class="col-6">
                <input name="addons[{{ $index }}][name]" class="form-control" placeholder="Nombre" value="{{ $addon['name'] ?? '' }}">
            </div>
            <div class="col-4">
                <input name="addons[{{ $index }}][price]" class="form-control" placeholder="Precio" type="number" step="0.01" value="{{ $addon['price'] ?? 0 }}">
            </div>
            <div class="col-2">
                <button class="btn btn-danger remove-addon">X</button>
            </div>
        </div>
    @endforeach
        </div>
        <button type="button" id="addAddon" class="btn btn-sm btn-outline-primary mb-3">Agregar adicional</button>
      </div>

      <div class="col-md-4">
        <div class="mb-3">
          <label>Imagen</label>
          <input type="file" name="image" class="form-control">
          @if($product->image)
            <img src="{{ asset($product->image) }}" class="img-fluid mt-2" alt="Imagen del producto">
          @endif
        </div>
      </div>
    </div>

    <button class="btn btn-primary">Actualizar</button>
  </form>
</div>

@push('scripts')
<script>
$(function(){
    // Función para obtener el siguiente índice
    function nextIndex() {
        let maxIndex = -1;
        $('#addonsContainer .addon-row').each(function(){
            let nameInput = $(this).find('input[name^="addons"]')[0];
            if(nameInput) {
                let match = nameInput.name.match(/\d+/);
                if(match) maxIndex = Math.max(maxIndex, parseInt(match[0]));
            }
        });
        return maxIndex + 1;
    }

    $('#addAddon').on('click', function(){
        let index = nextIndex();
        $('#addonsContainer').append(`
            <div class="row mb-2 addon-row">
                <div class="col-6">
                    <input name="addons[${index}][name]" class="form-control" placeholder="Nombre">
                </div>
                <div class="col-4">
                    <input name="addons[${index}][price]" class="form-control" placeholder="Precio" type="number" step="0.01">
                </div>
                <div class="col-2">
                    <button class="btn btn-danger remove-addon">X</button>
                </div>
            </div>
        `);
    });

    $(document).on('click', '.remove-addon', function(e){
        e.preventDefault();
        $(this).closest('.addon-row').remove();
    });
});
</script>
@endpush
@endsection
