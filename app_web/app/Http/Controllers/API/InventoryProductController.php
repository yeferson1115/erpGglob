<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\InventoryProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryProductController extends Controller
{
    public function index(): JsonResponse
    {
        $products = InventoryProduct::with('category')
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
            ]);

        return response()->json(['data' => $products]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedData($request);
        $product = InventoryProduct::create($data);

        return response()->json([
            'message' => 'Producto creado correctamente.',
            'data' => $product->fresh('category'),
        ], 201);
    }

    public function update(Request $request, InventoryProduct $inventoryProduct): JsonResponse
    {
        $data = $this->validatedData($request, $inventoryProduct->id);
        $inventoryProduct->update($data);

        return response()->json([
            'message' => 'Producto actualizado correctamente.',
            'data' => $inventoryProduct->fresh('category'),
        ]);
    }

    public function destroy(InventoryProduct $inventoryProduct): JsonResponse
    {
        $inventoryProduct->delete();

        return response()->json(['message' => 'Producto eliminado correctamente.']);
    }

    private function validatedData(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:80', Rule::unique('inventory_products', 'code')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:255'],
            'product_category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'tracks_inventory' => ['nullable', 'boolean'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'minimum_stock' => ['nullable', 'integer', 'min:0'],
            'is_combo' => ['nullable', 'boolean'],
            'combo_product_codes' => ['nullable', 'string'],
            'combo_product_ids' => ['nullable', 'array'],
            'combo_product_ids.*' => ['integer', 'exists:inventory_products,id'],
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
            'name' => $data['name'],
            'product_category_id' => $data['product_category_id'] ?? null,
            'price' => (float) $data['price'],
            'tracks_inventory' => $tracksInventory,
            'stock_quantity' => $tracksInventory ? (int) ($data['stock_quantity'] ?? 0) : null,
            'minimum_stock' => $tracksInventory ? (int) ($data['minimum_stock'] ?? 0) : null,
            'is_combo' => $isCombo,
            'combo_product_codes' => $isCombo ? $comboCodes : null,
        ];
    }
}
