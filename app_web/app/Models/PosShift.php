<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosShift extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'cashier_user_id',
        'cashier',
        'cash_register_name',
        'event_type',
        'occurred_at',
        'opening_fund',
        'counted_cash',
        'total_sales',
        'difference',
        'biometric_method',
        'biometric_evidence',
        'biometric_photo_path',
        'sync_hash',
        'synced_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'synced_at' => 'datetime',
    ];
}
