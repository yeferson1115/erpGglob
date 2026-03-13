<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Yajra\DataTables\Facades\DataTables;

class RoleController extends Controller
{
  public function index(Request $request)
    {
        $roles = Role::with('permissions')->select('roles.*');

        return DataTables::eloquent($roles)
            ->addColumn('permissions', function ($role) {
                return $role->permissions->pluck('name')->implode(', ');
            })
            ->filter(function ($query) use ($request) {
                $search = $request->input('search.value', '');
                if (!empty($search)) {
                    $query->where('name', 'like', "%{$search}%");
                }
            })
            ->toJson(); // o ->make(true)
    }
    public function getroles()
    {
        return response()->json(Role::all());
    }
    public function store(Request $request)
    {
        $request->validate([
            'name'       => 'required|unique:roles,name',
            'permissions' => 'array'
        ]);

        $role = Role::create(['name' => $request->name]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return response()->json($role->load('permissions'));
    }

    public function show($id)
    {
        $role = Role::with('permissions')->findOrFail($id);
        return response()->json($role);
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $role->update(['name' => $request->name]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return response()->json($role->load('permissions'));
    }

    public function destroy($id)
    {
        $role = Role::findOrFail($id);
        $role->delete();

        return response()->json(['message' => 'Rol eliminado']);
    }

    public function allPermissions()
    {
        return Permission::all();
    }
}
