<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_token',
        'status',
        'request_date',
        'full_name',
        'document_type',
        'document_number',
        'document_issue_date',
        'phone_primary',
        'phone_secondary',
        'email',
        'residential_address',
        'neighborhood',
        'city',
        'company_id',
        'work_site',
        'hire_date',
        'contract_type',
        'monthly_income',
        'requested_products',
        'net_value_without_interest',
        'installment_value',
        'first_installment_date',
        'installments_count',
        'payment_frequency',
        'observations',
        'employer_name',
        'discount_authorization_date',
        'employer_nit',
        'employee_name',
        'employee_document',
        'employee_position',
        'discount_concept',
        'discount_total_value',
        'signature_path',
        'id_front_path',
        'id_back_path',
        'selfie_with_id_path',
        'phone_verification_code_hash',
        'phone_verification_expires_at',
        'phone_verified_at',
        'phone_verified_number',
        'pdf_path',
        'submitted_at',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CreditPayment::class);
    }

    protected $casts = [
        'request_date' => 'date',
        'document_issue_date' => 'date',
        'hire_date' => 'date',
        'first_installment_date' => 'date',
        'discount_authorization_date' => 'date',
        'phone_verification_expires_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];
}
