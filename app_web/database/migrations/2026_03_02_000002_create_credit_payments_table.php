<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_application_id')->constrained()->cascadeOnDelete();
            $table->string('document_number', 60)->index();
            $table->string('payer_name')->nullable();
            $table->string('reference')->unique();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('COP');
            $table->string('status', 30)->default('pending')->index();
            $table->string('wompi_transaction_id')->nullable()->index();
            $table->json('wompi_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_payments');
    }
};
