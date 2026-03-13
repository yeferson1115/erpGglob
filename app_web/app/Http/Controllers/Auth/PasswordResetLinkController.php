<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        // Validar el email
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        // Verificar si el usuario existe
        $status = Password::sendResetLink(
            $request->only('email')
        );

        // Determinar el mensaje de respuesta
        if ($status == Password::RESET_LINK_SENT) {
            return back()->with('status', __($status));
        }

        // Si hay error, mostrar mensaje apropiado
        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

    /**
     * Enviar el enlace de restablecimiento (versión API)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => __($status)], 200)
            : response()->json(['message' => __($status)], 400);
    }
}