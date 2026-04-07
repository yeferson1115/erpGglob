<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\InventoryProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryProductController extends Controller
{
    public function index(): JsonResponse
    {
        $user = $this->ensureBusinessUser();
        $salesPointId = request()->integer('sales_point_id');
        $allowedSalesPointIds = $this->resolveAllowedSalesPointIds($user->id, $user->company_id);

        $products = InventoryProduct::with(['category', 'salesPoints:id,name'])
            ->where('company_id', $user->company_id)
            ->when(!$this->isOwnerUser($user), fn ($query) => $query->whereHas('salesPoints', fn ($sq) => $sq->whereIn('sales_points.id', $allowedSalesPointIds)))
            ->when($salesPointId, fn ($query) => $query->whereHas('salesPoints', fn ($sq) => $sq->where('sales_points.id', $salesPointId)))
            ->orderBy('name')
            ->get()
            ->map(fn (InventoryProduct $product) => [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'product_category_id' => $product->product_category_id,
                'category_name' => $product->category?->name,
                'price' => $product->price,
                'tracks_inventory' => $product->tracks_inventory,
                'stock_quantity' => $product->stock_quantity,
                'minimum_stock' => $product->minimum_stock,
                'is_combo' => $product->is_combo,
                'combo_product_codes' => $product->combo_product_codes ?? [],
                'sales_point_ids' => $product->salesPoints->pluck('id')->values()->all(),
                'sales_point_names' => $product->salesPoints->pluck('name')->values()->all(),
            ]);

        return response()->json(['data' => $products]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->ensureOwner();

        $data = $this->validatedData($request, $user->company_id);
        $salesPointIds = $data['sales_point_ids'];
        unset($data['sales_point_ids']);
        $product = InventoryProduct::create($data);
        $product->salesPoints()->sync($salesPointIds);

        return response()->json([
            'message' => 'Producto creado correctamente.',
            'data' => $product->fresh(['category', 'salesPoints:id,name']),
        ], 201);
    }

    public function update(Request $request, InventoryProduct $inventoryProduct): JsonResponse
    {
        $user = $this->ensureOwner();
        abort_unless((int) $inventoryProduct->company_id === (int) $user->company_id, 403);

        $data = $this->validatedData($request, $user->company_id, $inventoryProduct->id);
        $salesPointIds = $data['sales_point_ids'];
        unset($data['sales_point_ids']);
        $inventoryProduct->update($data);
        $inventoryProduct->salesPoints()->sync($salesPointIds);

        return response()->json([
            'message' => 'Producto actualizado correctamente.',
            'data' => $inventoryProduct->fresh(['category', 'salesPoints:id,name']),
        ]);
    }

    public function destroy(InventoryProduct $inventoryProduct): JsonResponse
    {
        $user = $this->ensureOwner();
        abort_unless((int) $inventoryProduct->company_id === (int) $user->company_id, 403);

        $inventoryProduct->delete();

        return response()->json(['message' => 'Producto eliminado correctamente.']);
    }

    private function validatedData(Request $request, int $companyId, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:80', Rule::unique('inventory_products', 'code')->where(fn ($q) => $q->where('company_id', $companyId))->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:255'],
            'product_category_id' => ['nullable', 'integer', Rule::exists('product_categories', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'price' => ['required', 'numeric', 'min:0'],
            'tracks_inventory' => ['nullable', 'boolean'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'minimum_stock' => ['nullable', 'integer', 'min:0'],
            'is_combo' => ['nullable', 'boolean'],
            'combo_product_codes' => ['nullable', 'string'],
            'combo_product_ids' => ['nullable', 'array'],
            'combo_product_ids.*' => ['integer', Rule::exists('inventory_products', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'sales_point_ids' => ['required', 'array', 'min:1'],
            'sales_point_ids.*' => ['integer', Rule::exists('sales_points', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
        ]);

        $tracksInventory = (bool) ($data['tracks_inventory'] ?? false);
        $isCombo = (bool) ($data['is_combo'] ?? false);

        $comboCodes = [];
        if ($isCombo && !empty($data['combo_product_codes'])) {
            $comboCodes = collect(explode(',', $data['combo_product_codes']))
                ->map(fn ($code) => trim($code))
                ->filter()
                ->values()
                ->all();
        }

        if ($isCombo && !empty($data['combo_product_ids'])) {
            $selectedCodes = InventoryProduct::whereIn('id', $data['combo_product_ids'])
                ->pluck('code')
                ->all();

            $comboCodes = collect([...$comboCodes, ...$selectedCodes])
                ->unique()
                ->values()
                ->all();
        }

        return [
            'code' => $data['code'],
            'company_id' => $companyId,
            'name' => $data['name'],
            'product_category_id' => $data['product_category_id'] ?? null,
            'price' => (float) $data['price'],
            'tracks_inventory' => $tracksInventory,
            'stock_quantity' => $tracksInventory ? (int) ($data['stock_quantity'] ?? 0) : null,
            'minimum_stock' => $tracksInventory ? (int) ($data['minimum_stock'] ?? 0) : null,
            'is_combo' => $isCombo,
            'combo_product_codes' => $isCombo ? $comboCodes : null,
            'sales_point_ids' => array_values(array_unique(array_map('intval', $data['sales_point_ids'] ?? []))),
        ];
    }

    private function ensureOwner()
    {
        $user = $this->ensureBusinessUser();
        abort_unless(
            strtolower((string) $user->business_role) === 'owner',
            403
        );

        return $user;
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
