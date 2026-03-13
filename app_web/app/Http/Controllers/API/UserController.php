<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index(Request $request)
    {
        $query = User::query()->with('roles', 'permissions');

        // Filtrar por nombre o email opcionalmente
        if ($search = $request->query('search')) {
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
        }

        // Paginación (default 10)
        $perPage = $request->query('per_page', 10);
        $users = $query->paginate($perPage);

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email'=> 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'roles' => 'array'
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email'=> $validated['email'],
            'phone'=> $request->phone,
            'tarjeta_profecional'=> $request->tarjeta_profecional,
            'r_aa'=> $request->r_aa,
            'document'=> $request->document,
            'profesion'=> $request->profesion,
            'password' => Hash::make($validated['password']),
        ]);

        if (!empty($validated['roles'])) {
            $user->syncRoles($validated['roles']);
        }        

        return response()->json(['message' => 'Usuario creado', 'user' => $user->load('roles','permissions')], 201);
    }

    public function show($id)
    {
        $user = User::with('roles', 'permissions')->findOrFail($id);
        return response()->json($user);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email'=> ['sometimes', 'required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:6',
            'roles' => 'array'            
        ]);

        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }
        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->document=$request->document;
        $user->phone=$request->phone;
        $user->tarjeta_profecional=$request->tarjeta_profecional;
        $user->r_aa=$request->r_aa;
        $user->profesion=$request->profesion;
        
        $user->save();

        if (isset($validated['roles'])) {
            $user->syncRoles($validated['roles']);
        }
        if (isset($validated['permissions'])) {
            $user->syncPermissions($validated['permissions']);
        }

        return response()->json(['message' => 'Usuario actualizado', 'user' => $user->load('roles','permissions')]);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(['message' => 'Usuario eliminado']);
    }
}
