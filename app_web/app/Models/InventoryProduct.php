<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class InventoryProduct extends Model
{
    protected $fillable = [
        'company_id',
        'code',
        'name',
        'product_category_id',
        'price',
        'tracks_inventory',
        'stock_quantity',
        'minimum_stock',
        'is_combo',
        'combo_product_codes',
    ];

    protected $casts = [
        'tracks_inventory' => 'boolean',
        'is_combo' => 'boolean',
        'combo_product_codes' => 'array',
        'price' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function salesPoints(): BelongsToMany
    {
        return $this->belongsToMany(SalesPoint::class, 'sales_point_inventory_product')
            ->withPivot(['is_available', 'stock_quantity', 'minimum_stock'])
            ->withTimestamps();
    }
}
