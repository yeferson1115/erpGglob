<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotion_codes', function (Blueprint $table) {
            $table->string('service_action')->nullable()->after('target_service');
            $table->foreignId('target_customer_id')->nullable()->after('service_action')->constrained('platform_customers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('promotion_codes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('target_customer_id');
            $table->dropColumn('service_action');
        });
    }
};
