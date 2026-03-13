{{-- resources/views/auth/reset-password.blade.php --}}
<x-guest-layout>
    <div class="authentication-wrapper authentication-cover">    
        <div class="authentication-inner row m-0">
            <!-- /Left Text -->
            <div class="d-none d-xl-flex col-xl-8 p-0">
                <div class="auth-cover-bg d-flex justify-content-center align-items-center">
                    <img
                        src="{{ asset('imagenes/logo.pjpgng') }}"
                        alt="auth-login-cover"
                        style="visibility: visible; !important"
                        class="my-5 auth-illustration"
                    />
                </div>
            </div>
            <!-- /Left Text -->

            <!-- Reset Password -->
            <div class="d-flex col-12 col-xl-4 align-items-center authentication-bg p-sm-12 p-6">
                <div class="w-px-400 mx-auto mt-12 pt-5">
                    <h4 class="mb-1">Establecer nueva contraseña 🔒</h4>
                    <p class="mb-4">Ingresa tu nueva contraseña</p>
                    
                    <form method="POST" action="{{ route('password.store') }}">
                        @csrf
                        
                        <!-- Password Reset Token -->
                        <input type="hidden" name="token" value="{{ $request->route('token') }}">
                        
                        <div class="mb-6">
                            <label for="email" class="form-label">Correo electrónico</label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   value="{{ old('email', $request->email) }}"
                                   required 
                                   autofocus
                                   readonly>
                            @error('email')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        
                        <div class="mb-6 form-password-toggle">
                            <label class="form-label" for="password">Nueva contraseña</label>
                            <div class="input-group input-group-merge">
                                <input
                                    type="password"
                                    id="password"
                                    class="form-control"
                                    name="password"
                                    placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                                    required />
                                <span class="input-group-text cursor-pointer"><i class="icon-base ti tabler-eye-off"></i></span>
                            </div>
                            @error('password')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        
                        <div class="mb-6 form-password-toggle">
                            <label class="form-label" for="password_confirmation">Confirmar contraseña</label>
                            <div class="input-group input-group-merge">
                                <input
                                    type="password"
                                    id="password_confirmation"
                                    class="form-control"
                                    name="password_confirmation"
                                    placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                                    required />
                                <span class="input-group-text cursor-pointer"><i class="icon-base ti tabler-eye-off"></i></span>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary d-grid w-100">
                            Restablecer contraseña
                        </button>
                        
                        <div class="text-center mt-3">
                            <a href="{{ route('login') }}" class="d-flex align-items-center justify-content-center">
                                <i class="icon-base ti tabler-chevron-left"></i>
                                Volver al login
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            <!-- /Reset Password -->
        </div>
    </div>
</x-guest-layout>