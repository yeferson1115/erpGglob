<?php

namespace App\Http\Controllers;

use App\Models\Company;
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

        $companies = Company::with(['owners', 'cashiers'])
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
        return view('admin.companies.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'nit' => ['required', 'string', 'max:60'],
            'address' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],

            'plan_name' => ['required', 'string', 'max:80'],
            'service_status' => ['required', 'in:active,inactive,suspended'],
            'started_at' => ['nullable', 'date'],
            'active_until' => ['nullable', 'date'],
            'gglob_cloud_enabled' => ['nullable', 'boolean'],
            'gglob_pay_enabled' => ['nullable', 'boolean'],
            'gglob_pos_enabled' => ['nullable', 'boolean'],
            'pos_mode' => ['required', 'in:mono,multi'],
            'pos_boxes' => ['required', 'integer', 'min:1', 'max:50'],
            'gglob_accounting_enabled' => ['nullable', 'boolean'],

            'owner_name' => ['required', 'string', 'max:255'],
            'owner_last_name' => ['nullable', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'owner_phone' => ['nullable', 'string', 'max:30'],
            'owner_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $company = Company::create([
            'name' => $data['name'],
            'nit' => $data['nit'],
            'address' => $data['address'],
            'email' => $data['email'],
            'contact_name' => $data['contact_name'],
            'plan_name' => $data['plan_name'],
            'service_status' => $data['service_status'],
            'started_at' => $data['started_at'] ?? null,
            'active_until' => $data['active_until'] ?? null,
            'gglob_cloud_enabled' => (bool)($data['gglob_cloud_enabled'] ?? false),
            'gglob_pay_enabled' => (bool)($data['gglob_pay_enabled'] ?? false),
            'gglob_pos_enabled' => (bool)($data['gglob_pos_enabled'] ?? false),
            'pos_mode' => $data['pos_mode'],
            'pos_boxes' => $data['pos_boxes'],
            'gglob_accounting_enabled' => (bool)($data['gglob_accounting_enabled'] ?? false),
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

        return redirect()->route('companies.edit', $company)->with('success', 'Negocio creado con dueño. Ahora puedes crear cajeros.');
    }

    public function edit(Company $company): View
    {
        $company->load(['owners', 'cashiers']);

        return view('admin.companies.edit', compact('company'));
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'nit' => ['required', 'string', 'max:60'],
            'address' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'plan_name' => ['required', 'string', 'max:80'],
            'service_status' => ['required', 'in:active,inactive,suspended'],
            'started_at' => ['nullable', 'date'],
            'active_until' => ['nullable', 'date'],
            'gglob_cloud_enabled' => ['nullable', 'boolean'],
            'gglob_pay_enabled' => ['nullable', 'boolean'],
            'gglob_pos_enabled' => ['nullable', 'boolean'],
            'pos_mode' => ['required', 'in:mono,multi'],
            'pos_boxes' => ['required', 'integer', 'min:1', 'max:50'],
            'gglob_accounting_enabled' => ['nullable', 'boolean'],
        ]);

        $company->update([
            ...$data,
            'gglob_cloud_enabled' => (bool)($data['gglob_cloud_enabled'] ?? false),
            'gglob_pay_enabled' => (bool)($data['gglob_pay_enabled'] ?? false),
            'gglob_pos_enabled' => (bool)($data['gglob_pos_enabled'] ?? false),
            'gglob_accounting_enabled' => (bool)($data['gglob_accounting_enabled'] ?? false),
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

    public function destroy(Company $company): RedirectResponse
    {
        $company->delete();

        return redirect()->route('companies.index')->with('success', 'Empresa eliminada correctamente.');
    }
}
