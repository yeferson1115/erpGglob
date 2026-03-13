<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryProduct extends Model
{
    protected $fillable = [
        'code',
        'name',
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
    ];
}
