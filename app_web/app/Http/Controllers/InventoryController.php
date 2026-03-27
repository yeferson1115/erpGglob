<?php

namespace App\Http\Controllers;

use App\Models\InventoryProduct;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(): View
    {
        $this->ensureAdmin();

        $products = InventoryProduct::with('category')->orderBy('name')->get();
        $categories = ProductCategory::where('is_active', true)->orderBy('name')->get();

        return view('admin.inventories.index', [
            'products' => $products,
            'categories' => $categories,
            'editingProduct' => null,
        ]);
    }

    public function edit(InventoryProduct $inventory): View
    {
        $this->ensureAdmin();

        $products = InventoryProduct::with('category')->orderBy('name')->get();
        $categories = ProductCategory::where('is_active', true)->orderBy('name')->get();

        return view('admin.inventories.index', [
            'products' => $products,
            'categories' => $categories,
            'editingProduct' => $inventory,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdmin();

        $data = $this->validatedData($request);
        InventoryProduct::create($data);

        return back()->with('success', 'Producto de inventario creado correctamente.');
    }

    public function update(Request $request, InventoryProduct $inventory): RedirectResponse
    {
        $this->ensureAdmin();

        $data = $this->validatedData($request, $inventory->id);
        $inventory->update($data);

        return redirect()->route('inventories.index')->with('success', 'Producto actualizado correctamente.');
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
                ->filter()
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

    private function ensureAdmin(): void
    {
        abort_unless(Auth::user()?->hasRole('admin'), 403);
    }
}
