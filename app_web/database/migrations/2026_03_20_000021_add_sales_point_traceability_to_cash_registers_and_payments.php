<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->foreignId('sales_point_id')->nullable()->after('company_id')->constrained('sales_points')->nullOnDelete();
        });

        Schema::table('gglob_pay_payments', function (Blueprint $table) {
            $table->foreignId('sales_point_id')->nullable()->after('cash_register_id')->constrained('sales_points')->nullOnDelete();
        });

        DB::statement('UPDATE gglob_pay_payments p JOIN cash_registers c ON c.id = p.cash_register_id SET p.sales_point_id = c.sales_point_id WHERE p.sales_point_id IS NULL');
    }

    public function down(): void
    {
        Schema::table('gglob_pay_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_point_id');
        });

        Schema::table('cash_registers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_point_id');
        });
    }
};
