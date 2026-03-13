<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
    ];

    public function creditApplications(): HasMany
    {
        return $this->hasMany(CreditApplication::class);
    }
}
