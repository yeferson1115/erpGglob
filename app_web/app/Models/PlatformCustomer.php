<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformCustomer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_name',
        'subscription_status',
        'is_paid',
        'started_at',
        'active_until',
        'contact_phone',
        'gglob_cloud_enabled',
        'gglob_pay_enabled',
        'gglob_pos_enabled',
        'pos_mode',
        'pos_boxes',
        'gglob_accounting_enabled',
        'electronic_billing_enabled',
        'electronic_billing_scope',
        'electronic_billing_boxes',
        'electronic_billing_monthly_limit',
        'electronic_billing_status',
        'electronic_billing_config',
        'electronic_docs_issued',
        'electronic_docs_sent',
        'electronic_docs_accepted',
        'electronic_docs_rejected',
        'electronic_docs_pending',
        'sales_total',
        'sales_gglob_pay',
        'sales_gglob_pos',
        'sales_count',
    ];

    protected $casts = [
        'started_at' => 'date',
        'active_until' => 'date',
        'is_paid' => 'boolean',
        'gglob_cloud_enabled' => 'boolean',
        'gglob_pay_enabled' => 'boolean',
        'gglob_pos_enabled' => 'boolean',
        'gglob_accounting_enabled' => 'boolean',
        'electronic_billing_enabled' => 'boolean',
        'electronic_billing_config' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
