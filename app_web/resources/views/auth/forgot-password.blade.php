{{-- resources/views/auth/forgot-password.blade.php --}}
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

            <!-- Forgot Password -->
            <div class="d-flex col-12 col-xl-4 align-items-center authentication-bg p-sm-12 p-6">
                <div class="w-px-400 mx-auto mt-12 pt-5">
                    <h4 class="mb-1">Restablecer contraseña 🔒</h4>
                    <p class="mb-4">Ingresa tu correo electrónico y te enviaremos instrucciones para restablecer tu contraseña</p>
                    
                    <!-- Session Status -->
                    @if (session('status'))
                        <div class="alert alert-success mb-3">
                            {{ session('status') }}
                        </div>
                    @endif
                    
                    <form method="POST" action="{{ route('password.email') }}">
                        @csrf
                        
                        <div class="mb-6">
                            <label for="email" class="form-label">Correo electrónico</label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   placeholder="Ingresa tu correo"
                                   value="{{ old('email') }}"
                                   required 
                                   autofocus>
                            @error('email')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                        
                        <button type="submit" class="btn btn-primary d-grid w-100">
                            Enviar enlace de restablecimiento
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
            <!-- /Forgot Password -->
        </div>
    </div>
</x-guest-layout>