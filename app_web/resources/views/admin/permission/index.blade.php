@extends('layouts.app')

@section('title', 'Permisos')
@section('page_title', 'Permisos')


@section('content')

    <div class="container">
      <div class="row">
        <div class="col-sm-12">
          <div class="card card-line-primary">
            <div class="card-header">
                <h5 class="font-weight-bold">Permisos del rol: {{ $role->name }}</h5>
                <div class="card-tools"></div>
              </div>
              <div class="card-body">
              @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif
              <form action="{{ route('permissions.update', $role->id) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label for="permissions" class="mb-3">Selecciona los permisos:</label><br>

                    @foreach($permissions as $permission)
                        <div class="form-check">
                            <input 
                                type="checkbox" 
                                class="form-check-input" 
                                name="permissions[]" 
                                value="{{ $permission->name }}" 
                                {{ in_array($permission->id, $rolePermissions) ? 'checked' : '' }}
                            >
                            <label class="form-check-label" for="permissions">{{ $permission->name }}</label>
                        </div>
                    @endforeach
                </div>

                <button type="submit" class="btn btn-primary mt-5">Actualizar Permisos</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>


@endsection

@push('scripts')

  <script src="{{ asset('js/admin/permission/index.js') }}"></script>
@endpush
