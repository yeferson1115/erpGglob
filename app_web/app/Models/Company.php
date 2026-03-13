<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'nit',
        'address',
        'email',
        'contact_name',
        'plan_id',
        'plan_name',
        'service_status',
        'started_at',
        'active_until',
        'gglob_cloud_enabled',
        'gglob_pay_enabled',
        'gglob_pos_enabled',
        'pos_mode',
        'pos_boxes',
        'gglob_accounting_enabled',
    ];

    protected $casts = [
        'started_at' => 'date',
        'active_until' => 'date',
        'gglob_cloud_enabled' => 'boolean',
        'gglob_pay_enabled' => 'boolean',
        'gglob_pos_enabled' => 'boolean',
        'gglob_accounting_enabled' => 'boolean',
    ];


    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
    public function creditApplications(): HasMany
    {
        return $this->hasMany(CreditApplication::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function owners(): HasMany
    {
        return $this->users()->where('business_role', 'owner');
    }

    public function cashiers(): HasMany
    {
        return $this->users()->where('business_role', 'cashier');
    }
}
