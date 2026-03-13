<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function index(): View
    {
        $companies = Company::orderBy('name')->paginate(20);

        return view('admin.companies.index', compact('companies'));
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
        ]);

        Company::create($data);

        return redirect()->route('companies.index')->with('success', 'Empresa creada correctamente.');
    }

    public function edit(Company $company): View
    {
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
        ]);

        $company->update($data);

        return redirect()->route('companies.index')->with('success', 'Empresa actualizada correctamente.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        $company->delete();

        return redirect()->route('companies.index')->with('success', 'Empresa eliminada correctamente.');
    }
}
