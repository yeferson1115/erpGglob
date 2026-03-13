<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class BusinessRegistrationController extends Controller
{
    public function create(): View
    {
        return view('auth.business-register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
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

        $user = DB::transaction(function () use ($validated) {
            $company = Company::create([
                'name' => $validated['company_name'],
                'nit' => $validated['nit'],
                'email' => $validated['company_email'],
                'address' => $validated['address'],
                'contact_name' => trim($validated['owner_name'] . ' ' . $validated['owner_last_name']),
                'service_status' => 'inactive',
                'plan_name' => 'Sin plan',
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

        event(new Registered($user));
        Auth::login($user);

        return redirect()->route('index')->with('status', 'Tu negocio fue registrado correctamente.');
    }
}
