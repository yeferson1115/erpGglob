<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CashRegister extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'sales_point_id',
        'name',
        'code',
        'status',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'cash_register_user')
            ->withPivot(['assigned_by', 'assigned_at', 'is_primary'])
            ->withTimestamps();
    }

    public function salesPoint(): BelongsTo
    {
        return $this->belongsTo(SalesPoint::class);
    }
}
