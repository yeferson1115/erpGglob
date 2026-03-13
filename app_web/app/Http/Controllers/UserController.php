<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Requests\StoreUser;
use App\Http\Requests\UpdateUser;
use App\Models\Log\LogSistema;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;


class UserController extends Controller
{
     public function __construct()
    {
        /*$this->middleware('permission:Ver Usuario')->only('index');
        $this->middleware('permission:Registrar Usuario')->only('create');
        $this->middleware('permission:Registrar Usuario')->only('store');
        $this->middleware('permission:Editar Usuario')->only('edit');
        $this->middleware('permission:Editar Usuario')->only('update');
        $this->middleware('permission:Ver Usuario')->only('show');*/

    }

    public function index(Request $request)
    {
        $users = User::with('roles')->with('permissions')
                       ->orderBy('created_at', 'desc')
                       ->get();



        return view('admin.usuarios.index', ['users' => $users]);
    }




    public function create()
    {

        return view('admin.usuarios.create');
    }



public function store(Request $request)
{
    $request->validate([
        'name'      => 'required|string|max:255',
        'last_name' => 'required|string|max:255',        
        'email'     => 'required|email|unique:users,email',
        'password'  => 'required|string|min:8|confirmed',            
        'phone'  => 'nullable|string'
    ]);

    // Crear el usuario
    $user = new User();
    $user->name      = $request->name;
    $user->last_name = $request->last_name;    
    $user->email     = $request->email;
    $user->password  = Hash::make($request->password);
    $user->gender    = $request->gender ?? null;   
    $user->phone    = $request->phone;
    


    $user->save();

    // Asignar rol si se seleccionó
    if ($request->has('role')) {
        $user->assignRole($request->role);
    }

    return response()->json([
        'success' => true,
        'user_id' => $user->id
    ]);
}





    public function show($id)
    {
        $user = User::find($id);
        return view('admin.usuarios.edit', ['user' => $user]);
    }




    public function edit($id)
    {
        $user = User::with('roles')->with('permissions')->find($id);
        

        return view('admin.usuarios.edit', ['user' => $user]);
    }




    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',            
            'email' => 'required|email|unique:users,email,' . $id,
            'password' => 'nullable|string|min:8|confirmed',            
            'phone'  => 'nullable|string'
            
        ]);

        $user = User::findOrFail($id);
        $user->name = $request->name;
        $user->last_name = $request->last_name;        
        $user->email = $request->email;
        $user->phone = $request->phone;
        $user->gender=$request->gender;

        if ($request->password) {
            $user->password = Hash::make($request->password);
        }
        $user->save();

        if ($request->has('role')) {
            $role = Role::find($request->role);
            if ($role) {
                $user->syncRoles([$role->name]);
            }
        }

        return json_encode(['success' => true]);
    }





    public function destroy($id)
    {

        $user = User::find($id)->delete();

        return json_encode(['success' => true]);
    }



    public function autocomplete(Request $request)
    {
        return User::search($request->q)->take(10)->get();
    }
}
