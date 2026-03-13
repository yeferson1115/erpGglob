<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plan_name')->default('Sin plan');
            $table->string('subscription_status')->default('inactive')->index();
            $table->boolean('is_paid')->default(false)->index();
            $table->date('started_at')->nullable();
            $table->date('active_until')->nullable();
            $table->string('contact_phone')->nullable();

            $table->boolean('gglob_cloud_enabled')->default(false);
            $table->boolean('gglob_pay_enabled')->default(false);
            $table->boolean('gglob_pos_enabled')->default(false);
            $table->string('pos_mode')->default('mono');
            $table->unsignedTinyInteger('pos_boxes')->default(1);
            $table->boolean('gglob_accounting_enabled')->default(false);

            $table->boolean('electronic_billing_enabled')->default(false);
            $table->string('electronic_billing_scope')->default('single_branch');
            $table->unsignedTinyInteger('electronic_billing_boxes')->default(1);
            $table->unsignedInteger('electronic_billing_monthly_limit')->nullable();
            $table->string('electronic_billing_status')->default('pending');
            $table->json('electronic_billing_config')->nullable();
            $table->unsignedInteger('electronic_docs_issued')->default(0);
            $table->unsignedInteger('electronic_docs_sent')->default(0);
            $table->unsignedInteger('electronic_docs_accepted')->default(0);
            $table->unsignedInteger('electronic_docs_rejected')->default(0);
            $table->unsignedInteger('electronic_docs_pending')->default(0);

            $table->decimal('sales_total', 14, 2)->default(0);
            $table->decimal('sales_gglob_pay', 14, 2)->default(0);
            $table->decimal('sales_gglob_pos', 14, 2)->default(0);
            $table->unsignedInteger('sales_count')->default(0);

            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_customers');
    }
};
