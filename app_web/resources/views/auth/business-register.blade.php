<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registrar negocio | Gglob</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4 p-md-5">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h1 class="h4 mb-1">Registra tu negocio</h1>
                                <p class="text-muted mb-0">Crea tu cuenta de dueño para acceder a la plataforma.</p>
                            </div>
                            <a href="{{ route('home') }}" class="btn btn-outline-secondary btn-sm">Volver</a>
                        </div>

                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0 ps-3">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('business.register.store') }}" class="row g-3">
                            @csrf

                            <div class="col-12">
                                <h2 class="h6 text-uppercase text-muted">Datos del negocio</h2>
                            </div>
                            <div class="col-md-7">
                                <label class="form-label" for="company_name">Nombre del negocio</label>
                                <input id="company_name" name="company_name" type="text" class="form-control" value="{{ old('company_name') }}" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label" for="nit">NIT</label>
                                <input id="nit" name="nit" type="text" class="form-control" value="{{ old('nit') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="company_email">Correo del negocio</label>
                                <input id="company_email" name="company_email" type="email" class="form-control" value="{{ old('company_email') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="address">Dirección</label>
                                <input id="address" name="address" type="text" class="form-control" value="{{ old('address') }}" required>
                            </div>

                            <div class="col-12 pt-2">
                                <h2 class="h6 text-uppercase text-muted">Datos del dueño</h2>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="owner_name">Nombres</label>
                                <input id="owner_name" name="owner_name" type="text" class="form-control" value="{{ old('owner_name') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="owner_last_name">Apellidos</label>
                                <input id="owner_last_name" name="owner_last_name" type="text" class="form-control" value="{{ old('owner_last_name') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="owner_email">Correo de acceso</label>
                                <input id="owner_email" name="owner_email" type="email" class="form-control" value="{{ old('owner_email') }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="owner_phone">Teléfono</label>
                                <input id="owner_phone" name="owner_phone" type="text" class="form-control" value="{{ old('owner_phone') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="password">Contraseña</label>
                                <input id="password" name="password" type="password" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="password_confirmation">Confirmar contraseña</label>
                                <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" required>
                            </div>

                            <div class="col-12 pt-2 d-flex justify-content-end gap-2">
                                <a href="{{ route('login') }}" class="btn btn-light border">Ya tengo cuenta</a>
                                <button type="submit" class="btn btn-primary">Crear cuenta</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
