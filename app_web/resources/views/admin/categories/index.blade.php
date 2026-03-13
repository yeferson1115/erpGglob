@extends('layouts.app')
@section('content')

<div class="card mb-6">   
    <div class="row card-header flex-column flex-md-row border-bottom mx-0 px-3 mb-3">
        <div class="d-md-flex justify-content-between align-items-center dt-layout-start col-md-auto me-auto mt-0">
            <h5 class="card-title mb-0 text-md-start text-center pb-md-0 pb-6">Categorías</h5>
        </div>
        @can('Crear Categorias')
        <div class="d-md-flex justify-content-between align-items-center dt-layout-end col-md-auto ms-auto mt-0">
            <div class="dt-buttons btn-group flex-wrap mb-0"> 
               <a class="btn create-new btn-primary" href="{{ route('categories.create') }}" >
                    <span>
                        <span class="d-flex align-items-center gap-2">
                            <i class="icon-base ti tabler-plus icon-sm"></i> 
                            <span class="d-none d-sm-inline-block">Nueva categoría</span>
                        </span>
                    </span>
                </a> 
            </div>
        </div>
        @endcan
    </div>
  

  <table class="table table-striped" id="datatables">
    <thead><tr><th>Nombre</th><th>Descripción</th><th></th></tr></thead>
    <tbody>
      @foreach($categories as $c)
        <tr>
          <td>{{ $c->name }}</td>
          <!--<td>{{ $c->description }}</td>-->
          <td>
            @can('Editar Categorias')
            <a href="{{ route('categories.edit', $c) }}" class="btn btn-sm btn-secondary">Editar</a>
            @endcan
            @can('Eliminar Categorias')
            <button class="btn btn-danger btn-sm btnDelete"
                    data-id="{{ $c->id }}"
                    data-url="{{ route('categories.destroy', $c->id) }}">
                Eliminar
            </button>
            @endcan

          </td>
        </tr>
      @endforeach
    </tbody>
  </table>

  {{ $categories->links() }}
</div>
@endsection
