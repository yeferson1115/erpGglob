<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesPoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'status',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function cashRegisters(): HasMany
    {
        return $this->hasMany(CashRegister::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'sales_point_user')
            ->withPivot(['company_id', 'assigned_by', 'assigned_at', 'is_active'])
            ->withTimestamps();
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'sales_point_product_category');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(InventoryProduct::class, 'sales_point_inventory_product')
            ->withPivot(['is_available', 'stock_quantity', 'minimum_stock'])
            ->withTimestamps();
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(CashRegisterShift::class);
    }
}
