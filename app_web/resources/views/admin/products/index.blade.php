@extends('layouts.app')
@section('content')
<div class="card mb-6">   
    <div class="row card-header flex-column flex-md-row border-bottom mx-0 px-3 mb-3">
        <div class="d-md-flex justify-content-between align-items-center dt-layout-start col-md-auto me-auto mt-0">
            <h5 class="card-title mb-0 text-md-start text-center pb-md-0 pb-6">Productos</h5>
        </div>
        @can('Crear Productos')
        <div class="d-md-flex justify-content-between align-items-center dt-layout-end col-md-auto ms-auto mt-0">
            <div class="dt-buttons btn-group flex-wrap mb-0"> 
               <a class="btn create-new btn-primary" href="{{ route('products.create') }}" >
                    <span>
                        <span class="d-flex align-items-center gap-2">
                            <i class="icon-base ti tabler-plus icon-sm"></i> 
                            <span class="d-none d-sm-inline-block">Nuevo Producto</span>
                        </span>
                    </span>
                </a> 
            </div>
        </div>
        @endcan
    </div>

  <table class="table" id="datatables">
    <thead><tr><th>Imagen</th><th>Nombre</th><th>Categoría</th><th>Precio</th><th></th></tr></thead>
    <tbody>
      @foreach($products as $p)
        <tr>
          <td style="width:80px;">
            @if($p->image)
              <img src="{{ asset($p->image) }}" style="height:50px;object-fit:cover">
            @endif
          </td>
          <td>{{ $p->name }}</td>
          <td>{{ $p->category?->name }}</td>
          <td>{{ number_format($p->price,2) }}</td>
          <td>
            @can('Editar Productos')
            <a href="{{ route('products.edit', $p) }}" class="btn btn-sm btn-secondary"><i class="fa-solid fa-pen-to-square" style="margin-right: 10px;"></i> Editar</a>            
            @endcan
            @can('Eliminar Productos')
            <button class="btn btn-danger btn-sm btnDelete"
                    data-id="{{ $p->id }}"
                    data-url="{{ route('products.destroy', $p->id) }}">
                <i class="fa-regular fa-trash-can" style="margin-right: 10px;"></i> Eliminar
            </button>
            @endcan
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>

  {{ $products->links() }}
</div>
@endsection
