<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureAdmin();

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
        $this->ensureAdmin();

        $plans = Plan::orderBy('name')->get();

        return view('admin.companies.create', compact('plans'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'nit' => ['required', 'string', 'max:60'],
            'address' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'pos_locations_count' => ['nullable', 'integer', 'min:0', 'max:100'],
            'pos_locations_text' => ['nullable', 'string', 'max:5000'],

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
        $posLocations = $this->parsePosLocations($data['pos_locations_text'] ?? null);
        $posLocationsCount = count($posLocations) > 0
            ? count($posLocations)
            : (int) ($data['pos_locations_count'] ?? 0);

        $company = Company::create([
            'name' => $data['name'],
            'nit' => $data['nit'],
            'address' => $data['address'],
            'email' => $data['email'],
            'contact_name' => $data['contact_name'],
            'pos_locations_count' => $posLocationsCount,
            'pos_locations' => $posLocations,
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
        $this->ensureAdminOrCompanyOwner($company);

        $company->load(['owners', 'cashiers', 'users']);
        $availableUsers = User::whereNull('company_id')->orderBy('name')->get();
        $plans = Plan::orderBy('name')->get();
        $cashierPermissions = Permission::orderBy('name')->get();
        $currentUser = Auth::user();

        return view('admin.companies.edit', compact('company', 'availableUsers', 'plans', 'cashierPermissions', 'currentUser'));
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $this->ensureAdmin();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'nit' => ['required', 'string', 'max:60'],
            'address' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'pos_locations_count' => ['nullable', 'integer', 'min:0', 'max:100'],
            'pos_locations_text' => ['nullable', 'string', 'max:5000'],
            'plan_id' => ['required', 'exists:plans,id'],
            'service_status' => ['required', 'in:active,inactive,suspended'],
            'started_at' => ['nullable', 'date'],
            'active_until' => ['nullable', 'date'],
        ]);

        $plan = Plan::findOrFail($data['plan_id']);
        $posLocations = $this->parsePosLocations($data['pos_locations_text'] ?? null);
        $posLocationsCount = count($posLocations) > 0
            ? count($posLocations)
            : (int) ($data['pos_locations_count'] ?? 0);

        $company->update([
            'name' => $data['name'],
            'nit' => $data['nit'],
            'address' => $data['address'],
            'email' => $data['email'],
            'contact_name' => $data['contact_name'],
            'pos_locations_count' => $posLocationsCount,
            'pos_locations' => $posLocations,
            ...$this->planDataForCompany($plan),
            'service_status' => $data['service_status'],
            'started_at' => $data['started_at'] ?? null,
            'active_until' => $data['active_until'] ?? null,
        ]);

        return redirect()->route('companies.index')->with('success', 'Negocio actualizado correctamente.');
    }

    public function storeCashier(Request $request, Company $company): RedirectResponse
    {
        $this->ensureAdminOrCompanyOwner($company);

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
        $this->ensureAdminOrCompanyOwner($company);

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'business_role' => ['required', 'in:owner,cashier'],
        ]);

        $user = User::findOrFail($data['user_id']);

        if ($this->isOwner() && $data['business_role'] !== 'cashier') {
            return back()->with('error', 'Como dueño solo puedes asignar usuarios cajeros.');
        }

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
        $this->ensureAdminOrCompanyOwner($company);

        abort_unless($user->company_id === $company->id, 404);

        $data = $request->validate([
            'business_role' => ['required', 'in:owner,cashier'],
        ]);

        if ($this->isOwner() && $data['business_role'] !== 'cashier') {
            return back()->with('error', 'Como dueño solo puedes gestionar usuarios con rol cajero.');
        }

        if ($data['business_role'] === 'owner') {
            User::where('company_id', $company->id)->where('business_role', 'owner')->where('id', '!=', $user->id)->update(['business_role' => 'cashier']);
        }

        $user->update(['business_role' => $data['business_role']]);

        return back()->with('success', 'Rol del usuario actualizado dentro del negocio.');
    }

    public function unassignBusinessUser(Company $company, User $user): RedirectResponse
    {
        $this->ensureAdminOrCompanyOwner($company);

        abort_unless($user->company_id === $company->id, 404);

        if ($this->isOwner() && $user->business_role !== 'cashier') {
            return back()->with('error', 'Como dueño solo puedes eliminar cajeros.');
        }

        $user->update([
            'company_id' => null,
            'business_role' => null,
        ]);

        return back()->with('success', 'Usuario desasignado del negocio.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        $this->ensureAdmin();

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

    public function updateCashierPermissions(Request $request, Company $company, User $user): RedirectResponse
    {
        $this->ensureAdminOrCompanyOwner($company);
        abort_unless($user->company_id === $company->id, 404);
        abort_unless($user->business_role === 'cashier', 403);

        $permissionNames = Permission::pluck('name')->all();
        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($permissionNames)],
        ]);

        $user->syncPermissions($data['permissions'] ?? []);

        return back()->with('success', 'Permisos del cajero actualizados correctamente.');
    }

    private function ensureAdmin(): void
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);
    }

    private function ensureAdminOrCompanyOwner(Company $company): void
    {
        $user = Auth::user();

        $isAdmin = $user?->hasRole('admin');
        $isOwner = $user && $user->company_id === $company->id && $user->business_role === 'owner';

        abort_unless($isAdmin || $isOwner, 403);
    }

    private function isOwner(): bool
    {
        $user = Auth::user();

        return (bool) ($user && $user->business_role === 'owner' && !$user->hasRole('admin'));
    }

    private function parsePosLocations(?string $value): array
    {
        if (!$value) {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $value) ?: [];

        return collect($lines)
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->unique()
            ->values()
            ->take(100)
            ->all();
    }
}
