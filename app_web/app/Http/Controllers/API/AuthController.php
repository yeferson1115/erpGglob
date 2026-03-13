<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|unique:users',
            'password' => 'required|string|min:6|confirmed'
        ]);

        if ($validator->fails()) return response()->json($validator->errors(), 422);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password)
        ]);

        $user->assignRole('user'); // asignar rol por defecto

        return response()->json(['message' => 'User created successfully']);
    }

    public function login(Request $request)
    {
       $credentials = $request->only(['email', 'password']);

        if (!$token = auth('api')->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Obtener usuario manualmente:
        $user = auth('api')->user();
        $user->load('roles', 'permissions');
        // Alternativamente, si $user es null, búscalo así:
        if (!$user) {
            $user = User::where('email', $credentials['email'])->first();
        }

        $permissions = $user->getAllPermissions();

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => auth('api')->factory()->getTTL() * 60,
            'user'         => $user,
            'permissions' => $permissions,
        ]);
    }

    public function logout()
    {
        auth()->logout();
        return response()->json(['message' => 'Successfully logged out']);
    }

    public function me()
    {
        return response()->json(auth()->user());
    }

    public function refresh()
    {
        try {
            $newToken = auth()->refresh(); // Genera un nuevo token
            $user = auth()->user();
            $permissions = $user->getAllPermissions();

            return response()->json([
                'access_token' => $newToken,
                'token_type'   => 'bearer',
                'expires_in'   => auth()->factory()->getTTL() * 60,
                'user'         => $user,
                'permissions'  => $permissions,
            ]);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['error' => 'Token inválido o expirado'], 401);
        }
    }
}
