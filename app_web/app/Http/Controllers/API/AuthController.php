<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

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

        $user->assignRole('user');

        return response()->json(['message' => 'User created successfully']);
    }


    public function registerBusiness(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_name' => ['required', 'string', 'max:255'],
            'nit' => ['required', 'string', 'max:60', 'unique:companies,nit'],
            'company_email' => ['required', 'email', 'max:255', 'unique:companies,email'],
            'address' => ['required', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_last_name' => ['required', 'string', 'max:255'],
            'owner_phone' => ['nullable', 'string', 'max:40'],
            'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Errores de validación.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $user = DB::transaction(function () use ($validated) {
            $now = now();
            $trialEndsAt = $now->copy()->addMonthsNoOverflow(2);

            $plan = Plan::query()->first();
            if (!$plan) {
                $plan = Plan::create([
                    'name' => 'Plan Bienvenida',
                    'gglob_cloud_enabled' => true,
                    'gglob_pay_enabled' => true,
                    'gglob_pos_enabled' => true,
                    'pos_mode' => 'mono',
                    'pos_boxes' => 1,
                    'gglob_accounting_enabled' => false,
                ]);
            }

            $company = Company::create([
                'name' => $validated['company_name'],
                'nit' => $validated['nit'],
                'email' => $validated['company_email'],
                'address' => $validated['address'],
                'contact_name' => trim($validated['owner_name'] . ' ' . $validated['owner_last_name']),
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'service_status' => 'active',
                'started_at' => $now->toDateString(),
                'active_until' => $trialEndsAt->toDateString(),
                'gglob_cloud_enabled' => true,
                'gglob_pay_enabled' => true,
                'gglob_pos_enabled' => true,
            ]);

            $user = User::create([
                'name' => $validated['owner_name'],
                'last_name' => $validated['owner_last_name'],
                'phone' => $validated['owner_phone'] ?? null,
                'email' => $validated['owner_email'],
                'password' => Hash::make($validated['password']),
                'company_id' => $company->id,
                'business_role' => 'owner',
            ]);

            if (Role::query()->where('name', 'user')->exists()) {
                $user->assignRole('user');
            }

            return $user;
        });

        return response()->json([
            'message' => 'Negocio registrado correctamente.',
            'user_id' => $user->id,
            'company_id' => $user->company_id,
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->only(['email', 'password']);

        if (!$token = auth('api')->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user = auth('api')->user();
        if (!$user) {
            $user = User::where('email', $credentials['email'])->first();
        }

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user->load(['roles', 'permissions', 'company', 'cashRegisters']);

        $context = strtolower((string) $request->input('app_context', 'web'));
        $access = $this->validateAccessByContext($user, $context);
        if ($access !== null) {
            auth('api')->logout();
            return $access;
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

    private function validateAccessByContext(User $user, string $context)
    {
        $company = $user->company;
        $companyIsActive = $company
            && strtolower((string) $company->service_status) === 'active'
            && $company->plan_id
            && !empty($company->active_until)
            && now()->toDateString() <= $company->active_until->toDateString();

        if ($context === 'desk') {
            $isDeskBusinessRole = in_array(strtolower((string) $user->business_role), ['owner', 'cashier'], true);
            $isAdmin = $user->hasRole('admin') || $user->hasRole('Administrador');
            if (!$isDeskBusinessRole && !$isAdmin) {
                return response()->json(['error' => 'Rol no permitido para app_desk. Solo Dueño, Cajero o Administrador.'], 403);
            }

            if (!$companyIsActive) {
                return response()->json(['error' => 'Plan o estado de la empresa inválido para app_desk.'], 403);
            }

            return null;
        }

        $hasAdminRole = $user->hasRole('admin') || $user->hasRole('Administrador');
        $isBusinessOwner = strtolower((string) $user->business_role) === 'owner';
        if (!$hasAdminRole && !$isBusinessOwner) {
            return response()->json(['error' => 'Acceso app_web permitido solo para Administradores o Dueños de Negocio.'], 403);
        }

        if (!$hasAdminRole && (!$companyIsActive || !($company?->gglob_cloud_enabled))) {
            return response()->json(['error' => 'Para ingresar a app_web como Dueño se requiere plan activo con Gglob Nube habilitado.'], 403);
        }

        return null;
    }

    public function logout()
    {
        auth()->logout();
        return response()->json(['message' => 'Successfully logged out']);
    }

    public function me()
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $user->load(['roles', 'permissions', 'company', 'cashRegisters']);

        return response()->json($user);
    }

    public function refresh()
    {
        try {
            $newToken = auth()->refresh();
            $user = auth()->user();
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            $user->load(['roles', 'permissions', 'company', 'cashRegisters']);
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
