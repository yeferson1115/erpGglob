<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'gglob_cloud_enabled',
        'gglob_pay_enabled',
        'gglob_pos_enabled',
        'pos_mode',
        'pos_boxes',
        'gglob_accounting_enabled',
    ];

    protected $casts = [
        'gglob_cloud_enabled' => 'boolean',
        'gglob_pay_enabled' => 'boolean',
        'gglob_pos_enabled' => 'boolean',
        'gglob_accounting_enabled' => 'boolean',
    ];

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }
}
