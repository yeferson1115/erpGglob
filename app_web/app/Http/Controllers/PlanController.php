<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        $plans = Plan::orderBy('name')->paginate(20);

        return view('admin.plans.index', compact('plans'));
    }

    public function create(): View
    {
        return view('admin.plans.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        Plan::create($this->normalizeData($data));

        return redirect()->route('plans.index')->with('success', 'Plan creado correctamente.');
    }

    public function edit(Plan $plan): View
    {
        return view('admin.plans.edit', compact('plan'));
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $data = $this->validateData($request, $plan->id);

        $plan->update($this->normalizeData($data));

        return redirect()->route('plans.index')->with('success', 'Plan actualizado correctamente.');
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        $plan->delete();

        return back()->with('success', 'Plan eliminado correctamente.');
    }

    private function validateData(Request $request, ?int $planId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:plans,name'.($planId ? ','.$planId : '')],
            'gglob_cloud_enabled' => ['nullable', 'boolean'],
            'gglob_pay_enabled' => ['nullable', 'boolean'],
            'gglob_pos_enabled' => ['nullable', 'boolean'],
            'pos_mode' => ['required', 'in:mono,multi'],
            'pos_boxes' => ['required', 'integer', 'min:1', 'max:50'],
            'gglob_accounting_enabled' => ['nullable', 'boolean'],
        ]);
    }

    private function normalizeData(array $data): array
    {
        return [
            ...$data,
            'gglob_cloud_enabled' => (bool) ($data['gglob_cloud_enabled'] ?? false),
            'gglob_pay_enabled' => (bool) ($data['gglob_pay_enabled'] ?? false),
            'gglob_pos_enabled' => (bool) ($data['gglob_pos_enabled'] ?? false),
            'gglob_accounting_enabled' => (bool) ($data['gglob_accounting_enabled'] ?? false),
        ];
    }
}
