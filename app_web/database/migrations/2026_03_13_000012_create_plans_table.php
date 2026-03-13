<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->boolean('gglob_cloud_enabled')->default(false);
            $table->boolean('gglob_pay_enabled')->default(false);
            $table->boolean('gglob_pos_enabled')->default(false);
            $table->enum('pos_mode', ['mono', 'multi'])->default('mono');
            $table->unsignedInteger('pos_boxes')->default(1);
            $table->boolean('gglob_accounting_enabled')->default(false);
            $table->timestamps();
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('contact_name')->constrained('plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
        });

        Schema::dropIfExists('plans');
    }
};
