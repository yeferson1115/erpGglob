<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use App\Support\BusinessPermissionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $user = $this->ensureBusinessUser();
        BusinessPermissionCatalog::ensure($user, BusinessPermissionCatalog::VIEW_CATEGORIES);
        $salesPointId = request()->integer('sales_point_id');
        $allowedSalesPointIds = $this->resolveAllowedSalesPointIds($user->id, $user->company_id);

        $categories = ProductCategory::query()
            ->with('salesPoints:id,name')
            ->where('company_id', $user->company_id)
            ->when(!$this->isOwnerUser($user), fn ($query) => $query->whereHas('salesPoints', fn ($sq) => $sq->whereIn('sales_points.id', $allowedSalesPointIds)))
            ->when($salesPointId, fn ($query) => $query->whereHas('salesPoints', fn ($sq) => $sq->where('sales_points.id', $salesPointId)))
            ->orderBy('name')
            ->get()
            ->map(fn (ProductCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'is_active' => $category->is_active,
                'sales_point_ids' => $category->salesPoints->pluck('id')->values()->all(),
                'sales_point_names' => $category->salesPoints->pluck('name')->values()->all(),
            ]);

        return response()->json(['data' => $categories]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->ensureBusinessUser();
        BusinessPermissionCatalog::ensure($user, BusinessPermissionCatalog::CREATE_CATEGORIES);
        $data = $this->validatedData($request, $user->company_id);

        $category = ProductCategory::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'company_id' => $user->company_id,
        ]);
        $category->salesPoints()->sync($data['sales_point_ids']);

        return response()->json([
            'message' => 'Categoría creada correctamente.',
            'data' => $category->fresh('salesPoints:id,name'),
        ], 201);
    }

    public function update(Request $request, ProductCategory $productCategory): JsonResponse
    {
        $user = $this->ensureBusinessUser();
        BusinessPermissionCatalog::ensure($user, BusinessPermissionCatalog::EDIT_CATEGORIES);
        abort_unless((int) $productCategory->company_id === (int) $user->company_id, 403);

        $data = $this->validatedData($request, $user->company_id, $productCategory->id);
        $productCategory->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
        $productCategory->salesPoints()->sync($data['sales_point_ids']);

        return response()->json([
            'message' => 'Categoría actualizada correctamente.',
            'data' => $productCategory->fresh('salesPoints:id,name'),
        ]);
    }

    public function destroy(ProductCategory $productCategory): JsonResponse
    {
        $user = $this->ensureBusinessUser();
        BusinessPermissionCatalog::ensure($user, BusinessPermissionCatalog::DELETE_CATEGORIES);
        abort_unless((int) $productCategory->company_id === (int) $user->company_id, 403);

        $productCategory->delete();

        return response()->json(['message' => 'Categoría eliminada correctamente.']);
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
            'sales_point_ids' => ['required', 'array', 'min:1'],
            'sales_point_ids.*' => ['integer', Rule::exists('sales_points', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
        ]);
    }

    private function ensureBusinessUser()
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->company_id, 403);

        return $user;
    }

    private function resolveAllowedSalesPointIds(int $userId, int $companyId): array
    {
        $explicit = DB::table('sales_point_user')
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->pluck('sales_point_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (!empty($explicit)) {
            return $explicit;
        }

        return DB::table('cash_register_user as cru')
            ->join('cash_registers as cr', 'cr.id', '=', 'cru.cash_register_id')
            ->where('cru.user_id', $userId)
            ->where('cr.company_id', $companyId)
            ->whereNotNull('cr.sales_point_id')
            ->pluck('cr.sales_point_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function isOwnerUser($user): bool
    {
        return strtolower((string) $user->business_role) === 'owner';
    }
}
