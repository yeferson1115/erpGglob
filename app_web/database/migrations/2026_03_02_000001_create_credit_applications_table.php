<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('credit_applications', function (Blueprint $table) {
            $table->id();
            $table->string('public_token')->unique();
            $table->string('status')->default('draft');
            $table->date('request_date')->nullable();

            $table->string('full_name')->nullable();
            $table->string('document_type')->nullable();
            $table->string('document_number')->nullable();
            $table->date('document_issue_date')->nullable();
            $table->string('phone_primary')->nullable();
            $table->string('phone_secondary')->nullable();
            $table->string('email')->nullable();
            $table->string('residential_address')->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('city')->nullable();

            $table->string('company_name')->nullable();
            $table->string('work_site')->nullable();
            $table->date('hire_date')->nullable();
            $table->string('contract_type')->nullable();
            $table->decimal('monthly_income', 12, 2)->nullable();

            $table->text('requested_products')->nullable();
            $table->decimal('net_value_without_interest', 12, 2)->nullable();
            $table->decimal('installment_value', 12, 2)->nullable();
            $table->date('first_installment_date')->nullable();
            $table->unsignedInteger('installments_count')->nullable();
            $table->string('payment_frequency')->nullable();
            $table->text('observations')->nullable();

            $table->string('employer_name')->nullable();
            $table->date('discount_authorization_date')->nullable();
            $table->string('employer_nit')->nullable();
            $table->string('employee_name')->nullable();
            $table->string('employee_document')->nullable();
            $table->string('employee_position')->nullable();
            $table->string('discount_concept')->nullable();
            $table->decimal('discount_total_value', 12, 2)->nullable();

            $table->string('signature_path')->nullable();
            $table->string('id_front_path')->nullable();
            $table->string('id_back_path')->nullable();
            $table->string('selfie_with_id_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_applications');
    }
};
