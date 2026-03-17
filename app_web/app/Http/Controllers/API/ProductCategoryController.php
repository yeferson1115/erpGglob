<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProductCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $user = $this->ensureBusinessUser();

        $categories = ProductCategory::query()
            ->where('company_id', $user->company_id)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $categories]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->ensureBusinessUser();
        $data = $this->validatedData($request, $user->company_id);

        $category = ProductCategory::create([
            ...$data,
            'company_id' => $user->company_id,
        ]);

        return response()->json([
            'message' => 'Categoría creada correctamente.',
            'data' => $category,
        ], 201);
    }

    public function update(Request $request, ProductCategory $productCategory): JsonResponse
    {
        $user = $this->ensureBusinessUser();
        abort_unless((int) $productCategory->company_id === (int) $user->company_id, 403);

        $data = $this->validatedData($request, $user->company_id, $productCategory->id);
        $productCategory->update($data);

        return response()->json([
            'message' => 'Categoría actualizada correctamente.',
            'data' => $productCategory->fresh(),
        ]);
    }

    public function destroy(ProductCategory $productCategory): JsonResponse
    {
        $user = $this->ensureBusinessUser();
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
        ]);
    }

    private function ensureBusinessUser()
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->company_id, 403);

        return $user;
    }
}
