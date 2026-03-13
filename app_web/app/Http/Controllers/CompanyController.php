<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->get('service_status');

        $companies = Company::with(['owners', 'cashiers', 'plan'])
            ->when(in_array($status, ['active', 'inactive', 'suspended'], true), function ($query) use ($status) {
                $query->where('service_status', $status);
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.companies.index', compact('companies', 'status'));
    }

    public function create(): View
    {
        $plans = Plan::orderBy('name')->get();

        return view('admin.companies.create', compact('plans'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'nit' => ['required', 'string', 'max:60'],
            'address' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],

            'plan_id' => ['required', 'exists:plans,id'],
            'service_status' => ['required', 'in:active,inactive,suspended'],
            'started_at' => ['nullable', 'date'],
            'active_until' => ['nullable', 'date'],

            'owner_name' => ['required', 'string', 'max:255'],
            'owner_last_name' => ['nullable', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'owner_phone' => ['nullable', 'string', 'max:30'],
            'owner_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $plan = Plan::findOrFail($data['plan_id']);

        $company = Company::create([
            'name' => $data['name'],
            'nit' => $data['nit'],
            'address' => $data['address'],
            'email' => $data['email'],
            'contact_name' => $data['contact_name'],
            ...$this->planDataForCompany($plan),
            'service_status' => $data['service_status'],
            'started_at' => $data['started_at'] ?? null,
            'active_until' => $data['active_until'] ?? null,
        ]);

        $owner = User::create([
            'name' => $data['owner_name'],
            'last_name' => $data['owner_last_name'] ?? null,
            'email' => $data['owner_email'],
            'phone' => $data['owner_phone'] ?? null,
            'password' => Hash::make($data['owner_password']),
            'company_id' => $company->id,
            'business_role' => 'owner',
        ]);

        if (Role::where('name', 'admin')->exists()) {
            $owner->assignRole('admin');
        }

        return redirect()->route('companies.edit', $company)->with('success', 'Negocio creado con dueño. Ahora puedes crear/asignar cajeros.');
    }

    public function edit(Company $company): View
    {
        $company->load(['owners', 'cashiers', 'users']);
        $availableUsers = User::whereNull('company_id')->orderBy('name')->get();
        $plans = Plan::orderBy('name')->get();

        return view('admin.companies.edit', compact('company', 'availableUsers', 'plans'));
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'nit' => ['required', 'string', 'max:60'],
            'address' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'plan_id' => ['required', 'exists:plans,id'],
            'service_status' => ['required', 'in:active,inactive,suspended'],
            'started_at' => ['nullable', 'date'],
            'active_until' => ['nullable', 'date'],
        ]);

        $plan = Plan::findOrFail($data['plan_id']);

        $company->update([
            'name' => $data['name'],
            'nit' => $data['nit'],
            'address' => $data['address'],
            'email' => $data['email'],
            'contact_name' => $data['contact_name'],
            ...$this->planDataForCompany($plan),
            'service_status' => $data['service_status'],
            'started_at' => $data['started_at'] ?? null,
            'active_until' => $data['active_until'] ?? null,
        ]);

        return redirect()->route('companies.index')->with('success', 'Negocio actualizado correctamente.');
    }

    public function storeCashier(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $cashier = User::create([
            'name' => $data['name'],
            'last_name' => $data['last_name'] ?? null,
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'company_id' => $company->id,
            'business_role' => 'cashier',
        ]);

        if (Role::where('name', 'user')->exists()) {
            $cashier->assignRole('user');
        }

        return back()->with('success', 'Cajero creado con permisos limitados.');
    }

    public function assignExistingUser(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'business_role' => ['required', 'in:owner,cashier'],
        ]);

        $user = User::findOrFail($data['user_id']);

        if ($data['business_role'] === 'owner') {
            User::where('company_id', $company->id)->where('business_role', 'owner')->update(['business_role' => 'cashier']);
        }

        $user->update([
            'company_id' => $company->id,
            'business_role' => $data['business_role'],
        ]);

        return back()->with('success', 'Usuario asignado al negocio correctamente.');
    }

    public function updateBusinessUserRole(Request $request, Company $company, User $user): RedirectResponse
    {
        abort_unless($user->company_id === $company->id, 404);

        $data = $request->validate([
            'business_role' => ['required', 'in:owner,cashier'],
        ]);

        if ($data['business_role'] === 'owner') {
            User::where('company_id', $company->id)->where('business_role', 'owner')->where('id', '!=', $user->id)->update(['business_role' => 'cashier']);
        }

        $user->update(['business_role' => $data['business_role']]);

        return back()->with('success', 'Rol del usuario actualizado dentro del negocio.');
    }

    public function unassignBusinessUser(Company $company, User $user): RedirectResponse
    {
        abort_unless($user->company_id === $company->id, 404);

        $user->update([
            'company_id' => null,
            'business_role' => null,
        ]);

        return back()->with('success', 'Usuario desasignado del negocio.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        $company->delete();

        return redirect()->route('companies.index')->with('success', 'Empresa eliminada correctamente.');
    }

    private function planDataForCompany(Plan $plan): array
    {
        return [
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'gglob_cloud_enabled' => $plan->gglob_cloud_enabled,
            'gglob_pay_enabled' => $plan->gglob_pay_enabled,
            'gglob_pos_enabled' => $plan->gglob_pos_enabled,
            'pos_mode' => $plan->pos_mode,
            'pos_boxes' => $plan->pos_boxes,
            'gglob_accounting_enabled' => $plan->gglob_accounting_enabled,
        ];
    }
}
