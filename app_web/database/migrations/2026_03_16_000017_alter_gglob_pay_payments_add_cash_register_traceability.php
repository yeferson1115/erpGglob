<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gglob_pay_payments', function (Blueprint $table) {
            $table->uuid('payment_intent_id')->nullable()->after('id');
            $table->string('source_channel', 40)->default('ahorros')->after('reference_code');
            $table->foreignId('destination_account_id')->nullable()->after('source_channel')->constrained('gglob_pay_destination_accounts')->nullOnDelete();
            $table->foreignId('cash_register_id')->nullable()->after('user_id')->constrained('cash_registers')->nullOnDelete();
            $table->foreignId('cashier_user_id')->nullable()->after('cash_register_id')->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('VERIFIED')->after('bank');
            $table->string('verification_provider', 40)->nullable()->after('status');
            $table->text('verification_trace')->nullable()->after('verification_provider');

            $table->unique(['company_id', 'reference_code'], 'gglob_pay_company_reference_unique');
            $table->index(['company_id', 'cashier_user_id', 'verified_at'], 'gglob_pay_company_cashier_verified_idx');
            $table->index(['company_id', 'cash_register_id', 'verified_at'], 'gglob_pay_company_register_verified_idx');
        });
    }

    public function down(): void
    {
        Schema::table('gglob_pay_payments', function (Blueprint $table) {
            $table->dropIndex('gglob_pay_company_cashier_verified_idx');
            $table->dropIndex('gglob_pay_company_register_verified_idx');
            $table->dropUnique('gglob_pay_company_reference_unique');
            $table->dropConstrainedForeignId('destination_account_id');
            $table->dropConstrainedForeignId('cash_register_id');
            $table->dropConstrainedForeignId('cashier_user_id');
            $table->dropColumn([
                'payment_intent_id',
                'source_channel',
                'status',
                'verification_provider',
                'verification_trace',
            ]);
        });
    }
};
