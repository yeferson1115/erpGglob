<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\UpdatePermission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;


class PermissionController extends Controller
{


     public function __construct()
    {
        /*$this->middleware('permission:Ver Permisos')->only('index');
        $this->middleware('permission:Editar Permisos')->only('create');
        $this->middleware('permission:Ver Permisos')->only('show');*/

    }


    public function index()
    {
        $role = Role::findByName('Usuario');        
        $permissions = Permission::get();


        return view('admin.permission.index',compact('role','permissions'));
    }


    public function edit($roleId)
    {

        $role = Role::findOrFail($roleId);

        // Obtener todos los permisos disponibles
        $permissions = Permission::all();

        // Obtener los permisos ya asignados al rol
        $rolePermissions = $role->permissions->pluck('id')->toArray();


        return view('admin.permission.index',compact('role', 'permissions', 'rolePermissions'));
    }




    public function update(Request $request, $roleId)
    {

        $role = Role::findOrFail($roleId);

        // Validar que al menos un permiso sea seleccionado
        $request->validate([
            'permissions' => 'required|array',
        ]);
        
        // Sincronizar los permisos seleccionados con el rol
        $role->syncPermissions($request->permissions);

        return redirect()->route('permissions.edit', $roleId)->with('success', 'Permisos actualizados correctamente.');
    }


}
