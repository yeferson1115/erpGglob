<?php

namespace App\Models;

use App\Notifications\CustomResetPasswordNotification;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'last_name',
        'email',
        'phone',
        'password',
        'gender',
        'company_id',
        'business_role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new CustomResetPasswordNotification($token));
    }

    public function platformCustomer(): HasOne
    {
        return $this->hasOne(PlatformCustomer::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function cashRegisters(): BelongsToMany
    {
        return $this->belongsToMany(CashRegister::class, 'cash_register_user')
            ->withPivot(['assigned_by', 'assigned_at', 'is_primary'])
            ->withTimestamps();
    }

    public function salesPoints(): BelongsToMany
    {
        return $this->belongsToMany(SalesPoint::class, 'sales_point_user')
            ->withPivot(['company_id', 'assigned_by', 'assigned_at', 'is_active'])
            ->withTimestamps();
    }
}
