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
        $user = $this->ensureBusinessUser();
        $allowedSalesPointIds = $this->allowedSalesPointIds($user->id, (int) $user->company_id);
        $selectedSalesPointId = $this->resolveSelectedSalesPointId(request()->integer('sales_point_id'), $allowedSalesPointIds);
        $salesPoints = SalesPoint::query()
            ->whereIn('id', $allowedSalesPointIds)
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
            'canSelectSalesPoint' => $this->canSelectSalesPoint($user),
        ]);
    }

    public function edit(ProductCategory $productCategory): View
    {
        $user = $this->ensureBusinessUser();
        $allowedSalesPointIds = $this->allowedSalesPointIds($user->id, (int) $user->company_id);
        $selectedSalesPointId = $this->resolveSelectedSalesPointId(request()->integer('sales_point_id'), $allowedSalesPointIds);
        $salesPoints = SalesPoint::query()
            ->whereIn('id', $allowedSalesPointIds)
            ->orderBy('name')
            ->get();
        abort_unless((int) $productCategory->company_id === (int) $user->company_id, 403);
        abort_unless(
            strtolower((string) $user->business_role) === 'owner' ||
            $productCategory->salesPoints()->whereIn('sales_points.id', $allowedSalesPointIds)->exists(),
            403
        );

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
            'canSelectSalesPoint' => $this->canSelectSalesPoint($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->ensureBusinessUser();
        $allowedSalesPointIds = $this->allowedSalesPointIds($user->id, (int) $user->company_id);
        $data = $this->validatedData($request, (int) $user->company_id, $allowedSalesPointIds);
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
        $user = $this->ensureBusinessUser();
        $allowedSalesPointIds = $this->allowedSalesPointIds($user->id, (int) $user->company_id);
        abort_unless((int) $productCategory->company_id === (int) $user->company_id, 403);
        abort_unless(
            strtolower((string) $user->business_role) === 'owner' ||
            $productCategory->salesPoints()->whereIn('sales_points.id', $allowedSalesPointIds)->exists(),
            403
        );

        $data = $this->validatedData($request, (int) $user->company_id, $allowedSalesPointIds, $productCategory->id);
        $salesPointId = (int) $data['sales_point_id'];
        unset($data['sales_point_id']);
        $productCategory->update($data);
        $productCategory->salesPoints()->sync([$salesPointId]);

        return redirect()->route('product-categories.index')->with('success', 'Categoría actualizada correctamente.');
    }

    public function destroy(ProductCategory $productCategory): RedirectResponse
    {
        $user = $this->ensureBusinessUser();
        $allowedSalesPointIds = $this->allowedSalesPointIds($user->id, (int) $user->company_id);
        abort_unless((int) $productCategory->company_id === (int) $user->company_id, 403);
        abort_unless(
            strtolower((string) $user->business_role) === 'owner' ||
            $productCategory->salesPoints()->whereIn('sales_points.id', $allowedSalesPointIds)->exists(),
            403
        );

        $productCategory->delete();

        return redirect()->route('product-categories.index')->with('success', 'Categoría eliminada correctamente.');
    }

    private function validatedData(Request $request, int $companyId, array $allowedSalesPointIds, ?int $ignoreId = null): array
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
            'sales_point_id' => ['required', 'integer', Rule::exists('sales_points', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->whereIn('id', $allowedSalesPointIds))],
        ]);
    }

    private function ensureBusinessUser()
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->company_id, 403);

        return $user;
    }

    private function allowedSalesPointIds(int $userId, int $companyId): array
    {
        $owner = strtolower((string) Auth::user()?->business_role) === 'owner';
        if ($owner) {
            return SalesPoint::query()
                ->where('company_id', $companyId)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $ids = SalesPoint::query()
            ->where('company_id', $companyId)
            ->whereHas('users', fn ($query) => $query->where('users.id', $userId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        abort_unless(!empty($ids), 403);

        return $ids;
    }

    private function resolveSelectedSalesPointId(?int $requestedId, array $allowedSalesPointIds): ?int
    {
        if ($requestedId && in_array($requestedId, $allowedSalesPointIds, true)) {
            return $requestedId;
        }

        return count($allowedSalesPointIds) === 1 ? $allowedSalesPointIds[0] : null;
    }

    private function canSelectSalesPoint($user): bool
    {
        return strtolower((string) $user->business_role) === 'owner';
    }

}
