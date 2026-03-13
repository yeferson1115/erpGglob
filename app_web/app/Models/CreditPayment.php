<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'credit_application_id',
        'document_number',
        'payer_name',
        'reference',
        'amount',
        'currency',
        'status',
        'wompi_transaction_id',
        'wompi_response',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'wompi_response' => 'array',
        'paid_at' => 'datetime',
    ];

    public function creditApplication(): BelongsTo
    {
        return $this->belongsTo(CreditApplication::class);
    }
}
