<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'sales_point_id',
        'cash_register_id',
        'cash_register_shift_id',
        'cashier_user_id',
        'ticket_code',
        'payment_type',
        'total',
        'sold_at',
        'sync_hash',
        'synced_at',
    ];

    protected $casts = [
        'sold_at' => 'datetime',
        'synced_at' => 'datetime',
    ];
}
