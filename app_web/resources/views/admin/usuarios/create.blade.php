@extends('layouts.app')

@section('title', 'Usuarios')
@section('page_title', 'Usuarios')
@section('page_subtitle', 'Guardar')
@section('content')

<div class="content-header row mt-5">
    <div class="content-header-left col-md-9 col-12 mb-2">
        <div class="row breadcrumbs-top">
            <div class="col-12">
                <h2 class="content-header-title float-start mb-0">Nuevo usuario</h2>
            </div>
        </div>
    </div>
</div>
<div class="content-body">
    <section id="multiple-column-form">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Crear Usuario</h4>
                    </div>
                    <div class="card-body">
                        <form class="form" role="form" 
                              action="javascript:void(0)" 
                              id="main-form" 
                              autocomplete="off" 
                              enctype="multipart/form-data">
                            <input type="hidden" id="_url" value="{{ url('users') }}">
                            <input type="hidden" id="_token" value="{{ csrf_token() }}">
                            <div class="row">

                                <div class="col-md-6 col-12">
                                    <div class="mb-1">
                                        <label class="form-label" for="name">Nombres</label>
                                        <input type="text" class="form-control" id="name" name="name" placeholder="Nombres">
                                        <span class="missing_alert text-danger" id="name_alert"></span>
                                    </div>
                                </div>

                                <div class="col-md-6 col-12">
                                    <div class="mb-1">
                                        <label class="form-label" for="last_name">Apellidos</label>
                                        <input type="text" id="last_name" name="last_name" class="form-control" placeholder="Apellidos">
                                        <span class="missing_alert text-danger" id="last_name_alert"></span>
                                    </div>
                                </div>

                                <div class="col-md-6 col-12 mt-2">
                                    <div class="mb-1">
                                        <label class="form-label" for="phone">Teléfono</label>
                                        <input type="text" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror"  autocomplete="phone" autofocus placeholder="Teléfono">
                                       
                                    </div>
                                </div>
                               

                                <div class="col-md-6 col-12">
                                    <div class="mb-1">
                                        <label class="form-label" for="gender">Género</label>
                                        <div class="demo-inline-spacing">
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="gender" id="inlineRadio1" value="M" checked="">
                                                <label class="form-check-label" for="inlineRadio1">Masculino</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="gender" id="inlineRadio2" value="F">
                                                <label class="form-check-label" for="inlineRadio2">Femenino</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!--<div class="col-md-6 col-12">
                                    <div class="mb-1">
                                        <label class="form-label" for="username">Usuario para el sistema</label>
                                        <input type="text"id="username" name="username" class="form-control"  placeholder="Usuario">
                                        <span class="missing_alert text-danger" id="username_alert"></span>
                                    </div>
                                </div>-->

                                <div class="col-md-6 col-12">
                                    <div class="mb-1">
                                        <label class="form-label" for="email">Correo Electrónico</label>
                                        <input type="email" class="form-control" id="email" name="email" placeholder="Correo Electrónico">
                                        <span class="missing_alert text-danger" id="email_alert"></span>
                                    </div>
                                </div>
                                

                           
                                
                                <div class="col-md-6 col-12">
                                    <div class="mb-1">
                                        <label class="form-label" for="city-column">Tipo de usuario</label>
                                        <div class="demo-inline-spacing">
                                            <input type="hidden" value="{{$roles = Spatie\Permission\Models\Role::get()}}">
                                            @foreach ($roles as $role)
                                            @if($role->name!='Scaneer')
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="role" id="role{{ $role->id }}" value="{{ $role->name }}">
                                                <label class="form-check-label" for="role{{ $role->id }}">{{ $role->name }}</label>
                                            </div>
                                            @endif
                                            @endforeach
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6 col-12">
                                        <div class="mb-1">
                                            <label class="form-label" for="password">Contraseña</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="password" name="password" placeholder="Contraseña">
                                                <button type="button" class="btn btn-outline-secondary" id="toggle-password">
                                                    <i class="fa-solid fa-eye"></i>
                                                </button>
                                            </div>
                                            <span class="missing_alert text-danger" id="password_alert"></span>
                                        </div>
                                    </div>

                                    <div class="col-md-6 col-12">
                                        <div class="mb-1">
                                            <label class="form-label" for="password_confirmation">Confirmar Contraseña</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" placeholder="Confirmar Contraseña">
                                                <button type="button" class="btn btn-outline-secondary" id="toggle-password-confirm">
                                                    <i class="fa-solid fa-eye"></i>
                                                </button>
                                            </div>
                                            <span class="missing_alert text-danger" id="password_confirmation_alert"></span>
                                        </div>
                                    </div>

                                

                                <div class="col-12 mt-5">
                                    <button type="submit" class="btn btn-primary me-1 waves-effect waves-float waves-light ajax" id="submit">
                                        <i id="ajax-icon" style="margin-right: 10px;" class="fa fa-save"></i> Guardar
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

@endsection

@push('scripts')
    <script src="{{ asset('js/admin/user/create.js') }}"></script>

<script>
    // Toggle contraseña
    $('#toggle-password').on('click', function () {
        let input = $('#password');
        let icon = $(this).find('i');
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            input.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    // Toggle confirmar contraseña
    $('#toggle-password-confirm').on('click', function () {
        let input = $('#password_confirmation');
        let icon = $(this).find('i');
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            input.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });
</script>
@endpush

