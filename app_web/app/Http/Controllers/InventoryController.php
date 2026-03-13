<?php

namespace App\Http\Controllers;

use App\Models\InventoryProduct;
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

        $products = InventoryProduct::orderBy('name')->get();

        return view('admin.inventories.index', [
            'products' => $products,
            'editingProduct' => null,
        ]);
    }

    public function edit(InventoryProduct $inventory): View
    {
        $this->ensureAdmin();

        $products = InventoryProduct::orderBy('name')->get();

        return view('admin.inventories.index', [
            'products' => $products,
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
            'tracks_inventory' => ['nullable', 'boolean'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'minimum_stock' => ['nullable', 'integer', 'min:0'],
            'is_combo' => ['nullable', 'boolean'],
            'combo_product_codes' => ['nullable', 'string'],
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

        return [
            'code' => $data['code'],
            'name' => $data['name'],
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
