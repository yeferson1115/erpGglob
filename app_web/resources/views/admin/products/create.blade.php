@extends('layouts.app')
@section('content')
<div class="card mb-6">
  <h5 class="card-header">Nuevo Producto</h5>
  <form method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data" class="card-body">
    @csrf
    <div class="row g-6">
      <div class="col-md-8">
        <div class="mb-3"><label>Nombre</label><input name="name" class="form-control" required></div>
        <div class="mb-3"><label>Descripción</label><textarea name="note" class="form-control"></textarea></div>
        <div class="mb-3"><label>Precio</label><input name="price" type="number" step="0.01" class="form-control" required></div>
        <div class="mb-3"><label>Categoría</label>
          <select name="category_id" class="form-control">
            <option value="">--</option>
            @foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
          </select>
        </div>
       <!-- <div class="mb-3 form-check">
          <input type="checkbox" name="single_option" value="1" class="form-check-input" id="singleOption">
          <label class="form-check-label" for="singleOption">Opción única</label>
        </div>-->

        <h6>Adicionales (opcional)</h6>
        <div id="addonsContainer"></div>
        <button type="button" id="addAddon" class="btn btn-sm btn-outline-primary mb-3">Agregar adicional</button>
      </div>

      <div class="col-md-4">
        <div class="mb-3"><label>Imagen</label><input type="file" name="image" class="form-control"></div>
      </div>
    </div>

    <button class="btn btn-primary">Guardar</button>
  </form>
</div>

@push('scripts')
<script>
$(function(){
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
                <div class="col-6"><input name="addons[${index}][name]" class="form-control" placeholder="Nombre"></div>
                <div class="col-4"><input name="addons[${index}][price]" class="form-control" placeholder="Precio" type="number" step="0.01"></div>
                <div class="col-2"><button class="btn btn-danger remove-addon">X</button></div>
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
