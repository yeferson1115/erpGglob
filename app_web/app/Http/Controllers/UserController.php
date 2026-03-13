<?php

namespace App\Http\Controllers;

use App\Models\PlatformCustomer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct()
    {
        // Middlewares deshabilitados por configuración actual del proyecto.
    }

    public function index(Request $request)
    {
        $statusFilter = $request->get('service_status');

        $usersQuery = User::with('roles')->with('permissions')->with('company')->orderBy('created_at', 'desc');

        if (Schema::hasTable('platform_customers')) {
            $usersQuery->with('platformCustomer');

            if (in_array($statusFilter, ['active', 'inactive', 'suspended'], true)) {
                $usersQuery->whereHas('platformCustomer', function ($query) use ($statusFilter) {
                    $query->where('subscription_status', $statusFilter);
                });
            }
        }

        $users = $usersQuery->get();

        return view('admin.usuarios.index', [
            'users' => $users,
            'statusFilter' => $statusFilter,
        ]);
    }

    public function create()
    {
        return view('admin.usuarios.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string',
        ]);

        $user = new User();
        $user->name = $request->name;
        $user->last_name = $request->last_name;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->gender = $request->gender ?? null;
        $user->phone = $request->phone;
        $user->save();

        if ($request->has('role')) {
            $user->assignRole($request->role);
        }

        if (Schema::hasTable('platform_customers')) {
            PlatformCustomer::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'plan_name' => 'Sin plan',
                    'subscription_status' => 'inactive',
                    'contact_phone' => $user->phone,
                    'pos_mode' => 'mono',
                    'pos_boxes' => 1,
                    'electronic_billing_scope' => 'single_branch',
                    'electronic_billing_boxes' => 1,
                    'electronic_billing_status' => 'pending',
                ]
            );
        }

        return response()->json([
            'success' => true,
            'user_id' => $user->id,
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

        $platformCustomer = null;
        if ($user && Schema::hasTable('platform_customers')) {
            $platformCustomer = PlatformCustomer::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'plan_name' => 'Sin plan',
                    'subscription_status' => 'inactive',
                    'contact_phone' => $user->phone,
                    'pos_mode' => 'mono',
                    'pos_boxes' => 1,
                    'electronic_billing_scope' => 'single_branch',
                    'electronic_billing_boxes' => 1,
                    'electronic_billing_status' => 'pending',
                ]
            );
        }

        return view('admin.usuarios.edit', [
            'user' => $user,
            'platformCustomer' => $platformCustomer,
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'password' => 'nullable|string|min:8|confirmed',
            'phone' => 'nullable|string',
        ]);

        $user = User::findOrFail($id);
        $user->name = $request->name;
        $user->last_name = $request->last_name;
        $user->email = $request->email;
        $user->phone = $request->phone;
        $user->gender = $request->gender;

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

        if (Schema::hasTable('platform_customers')) {
            PlatformCustomer::where('user_id', $user->id)->update([
                'contact_phone' => $user->phone,
            ]);
        }

        return json_encode(['success' => true]);
    }

    public function updateServices(Request $request, User $user): RedirectResponse
    {
        if (!Schema::hasTable('platform_customers')) {
            return back()->with('error', 'La tabla platform_customers no existe. Ejecuta migraciones.');
        }

        $validated = $request->validate([
            'plan_name' => 'required|string|max:80',
            'subscription_status' => 'required|in:active,inactive,suspended',
            'started_at' => 'nullable|date',
            'active_until' => 'nullable|date',
            'is_paid' => 'nullable|boolean',
            'gglob_cloud_enabled' => 'nullable|boolean',
            'gglob_pay_enabled' => 'nullable|boolean',
            'gglob_pos_enabled' => 'nullable|boolean',
            'pos_mode' => 'required|in:mono,multi',
            'pos_boxes' => 'required|integer|min:1|max:30',
            'gglob_accounting_enabled' => 'nullable|boolean',
        ]);

        $customer = PlatformCustomer::firstOrCreate(
            ['user_id' => $user->id],
            [
                'electronic_billing_scope' => 'single_branch',
                'electronic_billing_boxes' => 1,
                'electronic_billing_status' => 'pending',
            ]
        );

        $customer->update([
            ...$validated,
            'is_paid' => (bool) ($validated['is_paid'] ?? false),
            'gglob_cloud_enabled' => (bool) ($validated['gglob_cloud_enabled'] ?? false),
            'gglob_pay_enabled' => (bool) ($validated['gglob_pay_enabled'] ?? false),
            'gglob_pos_enabled' => (bool) ($validated['gglob_pos_enabled'] ?? false),
            'gglob_accounting_enabled' => (bool) ($validated['gglob_accounting_enabled'] ?? false),
            'contact_phone' => $user->phone,
        ]);

        return back()->with('status', 'Servicios del cliente actualizados correctamente.');
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
