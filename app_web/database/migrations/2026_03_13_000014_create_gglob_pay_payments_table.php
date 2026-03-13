<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gglob_pay_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reference_code', 80)->index();
            $table->string('sender_name', 160);
            $table->string('account_number', 80);
            $table->decimal('amount', 14, 2);
            $table->string('cashier', 120)->index();
            $table->string('bank', 120);
            $table->dateTime('verified_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gglob_pay_payments');
    }
};
