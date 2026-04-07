<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use App\Models\SalesPoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductCategoryController extends Controller
{
    public function index(): View
    {
        $user = $this->ensureOwner();
        $selectedSalesPointId = request()->integer('sales_point_id');
        $salesPoints = SalesPoint::query()
            ->where('company_id', $user->company_id)
            ->orderBy('name')
            ->get();

        $categories = ProductCategory::query()
            ->with('salesPoints:id,name')
            ->where('company_id', $user->company_id)
            ->when($selectedSalesPointId, fn ($query) => $query->whereHas('salesPoints', fn ($sq) => $sq->where('sales_points.id', $selectedSalesPointId)))
            ->orderBy('name')
            ->get();

        return view('admin.product-categories.index', [
            'categories' => $categories,
            'editingCategory' => null,
            'salesPoints' => $salesPoints,
            'selectedSalesPointId' => $selectedSalesPointId,
        ]);
    }

    public function edit(ProductCategory $productCategory): View
    {
        $user = $this->ensureOwner();
        $selectedSalesPointId = request()->integer('sales_point_id');
        $salesPoints = SalesPoint::query()
            ->where('company_id', $user->company_id)
            ->orderBy('name')
            ->get();
        abort_unless((int) $productCategory->company_id === (int) $user->company_id, 403);

        $categories = ProductCategory::query()
            ->with('salesPoints:id,name')
            ->where('company_id', $user->company_id)
            ->when($selectedSalesPointId, fn ($query) => $query->whereHas('salesPoints', fn ($sq) => $sq->where('sales_points.id', $selectedSalesPointId)))
            ->orderBy('name')
            ->get();

        return view('admin.product-categories.index', [
            'categories' => $categories,
            'editingCategory' => $productCategory,
            'salesPoints' => $salesPoints,
            'selectedSalesPointId' => $selectedSalesPointId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->ensureOwner();
        $data = $this->validatedData($request, $user->company_id);
        $salesPointId = (int) $data['sales_point_id'];
        unset($data['sales_point_id']);

        $category = ProductCategory::create([
            ...$data,
            'company_id' => $user->company_id,
        ]);
        $category->salesPoints()->sync([$salesPointId]);

        return back()->with('success', 'Categoría creada correctamente.');
    }

    public function update(Request $request, ProductCategory $productCategory): RedirectResponse
    {
        $user = $this->ensureOwner();
        abort_unless((int) $productCategory->company_id === (int) $user->company_id, 403);

        $data = $this->validatedData($request, $user->company_id, $productCategory->id);
        $salesPointId = (int) $data['sales_point_id'];
        unset($data['sales_point_id']);
        $productCategory->update($data);
        $productCategory->salesPoints()->sync([$salesPointId]);

        return redirect()->route('product-categories.index')->with('success', 'Categoría actualizada correctamente.');
    }

    public function destroy(ProductCategory $productCategory): RedirectResponse
    {
        $user = $this->ensureOwner();
        abort_unless((int) $productCategory->company_id === (int) $user->company_id, 403);

        $productCategory->delete();

        return redirect()->route('product-categories.index')->with('success', 'Categoría eliminada correctamente.');
    }

    private function validatedData(Request $request, int $companyId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('product_categories', 'name')
                    ->where(fn ($query) => $query->where('company_id', $companyId))
                    ->ignore($ignoreId),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'sales_point_id' => ['required', 'integer', Rule::exists('sales_points', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
        ]);
    }

    private function ensureBusinessUser()
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->company_id, 403);

        return $user;
    }

    private function ensureOwner()
    {
        $user = $this->ensureBusinessUser();
        abort_unless(strtolower((string) $user->business_role) === 'owner', 403);

        return $user;
    }
}
