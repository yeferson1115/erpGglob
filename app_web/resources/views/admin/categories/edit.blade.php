@extends('layouts.app')
@section('content')
<div class="card mb-6">
  <h5 class="card-header">Editar Categoría</h5>
  <form method="POST" action="{{ route('categories.update', $category) }}" class="card-body">
    @csrf @method('PUT')
    <div class="row g-6">
        <div class="col-md-6">
            <label class="form-label">Nombre</label>
            <input name="name" class="form-control"   value="{{ $category->name }}" required>
        </div>
        <!--<div class="col-md-6">
            <label class="form-label">Descripción</label>
            <textarea name="description" class="form-control">{{ $category->description }}</textarea>
        </div>-->
    </div>  
    <div class="pt-6">
        <button class="btn btn-primary mb-5">Actualizar</button>
    </div> 
  </form>
</div>
@endsection
