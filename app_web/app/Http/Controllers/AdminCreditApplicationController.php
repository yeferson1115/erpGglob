<?php

namespace App\Http\Controllers;

use App\Models\CreditApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminCreditApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $statusFilter = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());

        $applications = CreditApplication::query()
            ->with('company:id,name,nit')
            ->when($statusFilter !== '', function ($query) use ($statusFilter) {
                $query->where('status', $statusFilter);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nestedQuery) use ($search) {
                    $nestedQuery->where('full_name', 'like', "%{$search}%")
                        ->orWhere('document_number', 'like', "%{$search}%")
                        ->orWhere('phone_primary', 'like', "%{$search}%")
                        ->orWhere('public_token', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.credit-applications.index', [
            'applications' => $applications,
            'statuses' => $this->statuses(),
            'statusFilter' => $statusFilter,
            'search' => $search,
        ]);
    }

    public function show(CreditApplication $creditApplication): View
    {
        $creditApplication->load('company:id,name,nit');

        return view('admin.credit-applications.show', [
            'application' => $creditApplication,
            'statuses' => $this->statuses(),
        ]);
    }

    public function updateStatus(Request $request, CreditApplication $creditApplication): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys($this->statuses()))],
        ]);

        $creditApplication->status = $data['status'];
        $creditApplication->save();

        return redirect()
            ->route('admin.credit-applications.show', $creditApplication)
            ->with('success', 'Estado actualizado correctamente.');
    }

    private function statuses(): array
    {
        return [
            'draft' => 'Borrador',
            'submitted' => 'Enviada',
            'approved' => 'Aprobada',
            'rejected' => 'Rechazada',
        ];
    }
}
