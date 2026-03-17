<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductCategoryController extends Controller
{
    public function index(): View
    {
        $user = $this->ensureBusinessUser();

        $categories = ProductCategory::query()
            ->where('company_id', $user->company_id)
            ->orderBy('name')
            ->get();

        return view('admin.product-categories.index', [
            'categories' => $categories,
            'editingCategory' => null,
        ]);
    }

    public function edit(ProductCategory $productCategory): View
    {
        $user = $this->ensureBusinessUser();
        abort_unless((int) $productCategory->company_id === (int) $user->company_id, 403);

        $categories = ProductCategory::query()
            ->where('company_id', $user->company_id)
            ->orderBy('name')
            ->get();

        return view('admin.product-categories.index', [
            'categories' => $categories,
            'editingCategory' => $productCategory,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->ensureBusinessUser();
        $data = $this->validatedData($request, $user->company_id);

        ProductCategory::create([
            ...$data,
            'company_id' => $user->company_id,
        ]);

        return back()->with('success', 'Categoría creada correctamente.');
    }

    public function update(Request $request, ProductCategory $productCategory): RedirectResponse
    {
        $user = $this->ensureBusinessUser();
        abort_unless((int) $productCategory->company_id === (int) $user->company_id, 403);

        $data = $this->validatedData($request, $user->company_id, $productCategory->id);
        $productCategory->update($data);

        return redirect()->route('product-categories.index')->with('success', 'Categoría actualizada correctamente.');
    }

    public function destroy(ProductCategory $productCategory): RedirectResponse
    {
        $user = $this->ensureBusinessUser();
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
        ]);
    }

    private function ensureBusinessUser()
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->company_id, 403);

        return $user;
    }
}
