<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cashier_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('cashier', 120);
            $table->string('cash_register_name', 120);
            $table->enum('event_type', ['open', 'close']);
            $table->dateTime('occurred_at')->index();
            $table->decimal('opening_fund', 14, 2)->default(0);
            $table->decimal('counted_cash', 14, 2)->nullable();
            $table->decimal('total_sales', 14, 2)->default(0);
            $table->decimal('difference', 14, 2)->default(0);
            $table->string('biometric_method', 60);
            $table->string('biometric_evidence', 250);
            $table->string('biometric_photo_path', 255);
            $table->string('sync_hash', 64)->unique();
            $table->timestamp('synced_at')->nullable()->index();
            $table->timestamps();

            $table->index(['company_id', 'cashier_user_id', 'occurred_at'], 'pos_shifts_company_cashier_at_idx');
        });

        Schema::create('pos_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cashier_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('ticket_code', 80)->index();
            $table->string('payment_type', 60);
            $table->decimal('total', 14, 2);
            $table->dateTime('sold_at')->index();
            $table->string('sync_hash', 64)->unique();
            $table->timestamp('synced_at')->nullable()->index();
            $table->timestamps();

            $table->index(['company_id', 'cashier_user_id', 'sold_at'], 'pos_sales_company_cashier_sold_idx');
        });

        Schema::create('pos_cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cashier_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('cashier', 120);
            $table->string('type', 80)->index();
            $table->string('detail', 250);
            $table->decimal('amount', 14, 2);
            $table->dateTime('occurred_at')->index();
            $table->string('sync_hash', 64)->unique();
            $table->timestamp('synced_at')->nullable()->index();
            $table->timestamps();

            $table->index(['company_id', 'cashier_user_id', 'occurred_at'], 'pos_movements_company_cashier_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_cash_movements');
        Schema::dropIfExists('pos_sales');
        Schema::dropIfExists('pos_shifts');
    }
};
