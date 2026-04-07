<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosCashMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'cashier_user_id',
        'cashier',
        'type',
        'detail',
        'amount',
        'occurred_at',
        'sync_hash',
        'synced_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'synced_at' => 'datetime',
    ];
}
