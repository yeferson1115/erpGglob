<?php

namespace App\Http\Controllers;

use App\Models\InventoryProduct;
use App\Models\ProductCategory;
use App\Models\SalesPoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(): View
    {
        $user = $this->ensureOwner();
        $selectedSalesPointId = request()->integer('sales_point_id');
        $salesPoints = SalesPoint::query()
            ->where('company_id', $user->company_id)
            ->orderBy('name')
            ->get();

        $products = InventoryProduct::query()
            ->with(['category', 'salesPoints:id,name'])
            ->where('company_id', $user->company_id)
            ->when($selectedSalesPointId, fn ($query) => $query->whereHas('salesPoints', fn ($sq) => $sq->where('sales_points.id', $selectedSalesPointId)))
            ->orderBy('name')
            ->get();

        $categories = ProductCategory::query()
            ->with('salesPoints:id')
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.inventories.index', [
            'products' => $products,
            'categories' => $categories,
            'editingProduct' => null,
            'salesPoints' => $salesPoints,
            'selectedSalesPointId' => $selectedSalesPointId,
        ]);
    }

    public function edit(InventoryProduct $inventory): View
    {
        $user = $this->ensureOwner();
        abort_unless((int) $inventory->company_id === (int) $user->company_id, 403);
        $selectedSalesPointId = request()->integer('sales_point_id');
        $salesPoints = SalesPoint::query()
            ->where('company_id', $user->company_id)
            ->orderBy('name')
            ->get();

        $products = InventoryProduct::query()
            ->with(['category', 'salesPoints:id,name'])
            ->where('company_id', $user->company_id)
            ->when($selectedSalesPointId, fn ($query) => $query->whereHas('salesPoints', fn ($sq) => $sq->where('sales_points.id', $selectedSalesPointId)))
            ->orderBy('name')
            ->get();

        $categories = ProductCategory::query()
            ->with('salesPoints:id')
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.inventories.index', [
            'products' => $products,
            'categories' => $categories,
            'editingProduct' => $inventory,
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
        $product = InventoryProduct::create($data);
        $product->salesPoints()->sync([$salesPointId]);

        return back()->with('success', 'Producto de inventario creado correctamente.');
    }

    public function update(Request $request, InventoryProduct $inventory): RedirectResponse
    {
        $user = $this->ensureOwner();
        abort_unless((int) $inventory->company_id === (int) $user->company_id, 403);

        $data = $this->validatedData($request, $user->company_id, $inventory->id);
        $salesPointId = (int) $data['sales_point_id'];
        unset($data['sales_point_id']);
        $inventory->update($data);
        $inventory->salesPoints()->sync([$salesPointId]);

        return redirect()->route('inventories.index')->with('success', 'Producto actualizado correctamente.');
    }

    private function validatedData(Request $request, int $companyId, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:80', Rule::unique('inventory_products', 'code')->where(fn ($query) => $query->where('company_id', $companyId))->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:255'],
            'product_category_id' => ['nullable', 'integer', Rule::exists('product_categories', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'price' => ['required', 'numeric', 'min:0'],
            'tracks_inventory' => ['nullable', 'boolean'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'minimum_stock' => ['nullable', 'integer', 'min:0'],
            'is_combo' => ['nullable', 'boolean'],
            'combo_product_codes' => ['nullable', 'string'],
            'combo_product_ids' => ['nullable', 'array'],
            'combo_product_ids.*' => ['integer', Rule::exists('inventory_products', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'sales_point_id' => ['required', 'integer', Rule::exists('sales_points', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
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
            'company_id' => $companyId,
            'name' => $data['name'],
            'product_category_id' => $data['product_category_id'] ?? null,
            'price' => (float) $data['price'],
            'tracks_inventory' => $tracksInventory,
            'stock_quantity' => $tracksInventory ? (int) ($data['stock_quantity'] ?? 0) : null,
            'minimum_stock' => $tracksInventory ? (int) ($data['minimum_stock'] ?? 0) : null,
            'is_combo' => $isCombo,
            'combo_product_codes' => $isCombo ? $comboCodes : null,
            'sales_point_id' => (int) $data['sales_point_id'],
        ];
    }

    private function ensureOwner()
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->company_id, 403);
        abort_unless(strtolower((string) $user->business_role) === 'owner', 403);

        return $user;
    }
}
