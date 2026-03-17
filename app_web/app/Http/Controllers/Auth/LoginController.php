<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->filled('remember'))) {
            /** @var User|null $user */
            $user = Auth::user();
            $company = $user?->company;

            $companyIsActive = $company
                && strtolower((string) $company->service_status) === 'active'
                && $company->plan_id
                && !empty($company->active_until)
                && now()->toDateString() <= $company->active_until->toDateString();

            $hasAdminRole = $user?->hasRole('admin') || $user?->hasRole('Administrador');
            $isBusinessOwner = strtolower((string) $user?->business_role) === 'owner';

            if (!$hasAdminRole && !$isBusinessOwner) {
                Auth::logout();
                return back()->withErrors([
                    'email' => 'Acceso app_web permitido solo para Administradores o Dueños de Negocio.',
                ])->onlyInput('email');
            }

            if (!$companyIsActive || !($company?->gglob_cloud_enabled)) {
                Auth::logout();
                return back()->withErrors([
                    'email' => 'Para ingresar a app_web se requiere plan activo con Gglob Nube habilitado.',
                ])->onlyInput('email');
            }

            $request->session()->regenerate();
            return redirect()->intended('/dashboard'); // o la ruta que quieras
        }

        return back()->withErrors([
            'email' => 'Credenciales incorrectas.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}
