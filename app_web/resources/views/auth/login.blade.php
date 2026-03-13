{{-- En tu vista de login (donde está el formulario) --}}
<x-guest-layout>
    <div class="authentication-wrapper authentication-cover">    
        <div class="authentication-inner row m-0">
            <!-- /Left Text -->
            <div class="d-none d-xl-flex col-xl-8 p-0">
                <div class="auth-cover-bg d-flex justify-content-center align-items-center">
                    <img
                        src="{{ asset('imagenes/logo.png') }}"
                        alt="auth-login-cover"
                        style="visibility: visible; !important"
                        class="my-5 auth-illustration"
                    />
                </div>
            </div>
            <!-- /Left Text -->

            <!-- Login -->
            <div class="d-flex col-12 col-xl-4 align-items-center authentication-bg p-sm-12 p-6">
                <div class="w-px-400 mx-auto mt-12 pt-5">
                    <div class="mb-4 text-center">
                        <img src="{{ asset('imagenes/logo.png') }}" alt="Logo B&B Store" style="height: 72px;" class="rounded-3">
                    </div>
                    <h4 class="mb-1">Bienvenido a B&B Store! 👋</h4>
                    
                    @if (session('status'))
                        <div class="alert alert-success mb-3">
                            {{ session('status') }}
                        </div>
                    @endif
                    
                    <form action="{{ route('login') }}" method="POST" id="formAuthentication" class="mb-6">
                        @csrf
                        <div class="mb-6 form-control-validation">
                            <label for="email" class="form-label">Correo electrónico</label>
                            <input type="email" name="email" class="form-control" placeholder="Correo electrónico" value="{{ old('email') }}" autofocus />
                            @error('email')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="mb-6 form-password-toggle form-control-validation">
                            <label class="form-label" for="password">Password</label>
                            <div class="input-group input-group-merge">
                                <input
                                    type="password"
                                    id="password"
                                    class="form-control"
                                    name="password"
                                    placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                                    aria-describedby="password" />
                                <span class="input-group-text cursor-pointer"><i class="icon-base ti tabler-eye-off"></i></span>
                            </div>
                            @error('password')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        
                        <div class="mb-3">
                            <a href="{{ route('password.request') }}" class="float-end mb-1">
                                <small>¿Olvidaste tu contraseña?</small>
                            </a>
                        </div>
                        
                        <button type="submit" class="btn btn-primary d-grid w-100">Ingresar</button>
                    </form>

                </div>
            </div>
            <!-- /Login -->
        </div>
    </div>
</x-guest-layout>