<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_point_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_point_id')->constrained('sales_points')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['sales_point_id', 'user_id']);
            $table->index(['company_id', 'user_id']);
        });

        Schema::create('cash_register_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_point_id')->constrained('sales_points')->cascadeOnDelete();
            $table->foreignId('cash_register_id')->constrained('cash_registers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->nullable()->index();
            $table->string('status', 20)->default('open')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'sales_point_id', 'cash_register_id', 'starts_at'], 'cash_register_shifts_scope_idx');
            $table->index(['company_id', 'user_id', 'status'], 'cash_register_shifts_user_status_idx');
        });

        Schema::create('sales_point_product_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_point_id')->constrained('sales_points')->cascadeOnDelete();
            $table->foreignId('product_category_id')->constrained('product_categories')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['sales_point_id', 'product_category_id'], 'sp_category_unique');
        });

        Schema::create('sales_point_inventory_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_point_id')->constrained('sales_points')->cascadeOnDelete();
            $table->foreignId('inventory_product_id')->constrained('inventory_products')->cascadeOnDelete();
            $table->boolean('is_available')->default(true);
            $table->integer('stock_quantity')->nullable();
            $table->integer('minimum_stock')->nullable();
            $table->timestamps();

            $table->unique(['sales_point_id', 'inventory_product_id'], 'sp_product_unique');
        });

        Schema::table('inventory_products', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->index(['company_id', 'product_category_id'], 'inventory_products_company_category_idx');
        });

        DB::statement('UPDATE inventory_products ip LEFT JOIN product_categories pc ON pc.id = ip.product_category_id SET ip.company_id = pc.company_id WHERE ip.company_id IS NULL');

        Schema::table('inventory_products', function (Blueprint $table) {
            $table->dropUnique('inventory_products_code_unique');
            $table->unique(['company_id', 'code'], 'inventory_products_company_code_unique');
        });

        Schema::table('pos_shifts', function (Blueprint $table) {
            $table->foreignId('sales_point_id')->nullable()->after('company_id')->constrained('sales_points')->nullOnDelete();
            $table->foreignId('cash_register_id')->nullable()->after('sales_point_id')->constrained('cash_registers')->nullOnDelete();
            $table->foreignId('cash_register_shift_id')->nullable()->after('cash_register_id')->constrained('cash_register_shifts')->nullOnDelete();
            $table->index(['company_id', 'sales_point_id', 'cash_register_id', 'occurred_at'], 'pos_shifts_company_point_register_at_idx');
        });

        Schema::table('pos_sales', function (Blueprint $table) {
            $table->foreignId('sales_point_id')->nullable()->after('company_id')->constrained('sales_points')->nullOnDelete();
            $table->foreignId('cash_register_id')->nullable()->after('sales_point_id')->constrained('cash_registers')->nullOnDelete();
            $table->foreignId('cash_register_shift_id')->nullable()->after('cash_register_id')->constrained('cash_register_shifts')->nullOnDelete();
            $table->index(['company_id', 'sales_point_id', 'cash_register_id', 'sold_at'], 'pos_sales_company_point_register_sold_idx');
        });

        Schema::table('pos_cash_movements', function (Blueprint $table) {
            $table->foreignId('sales_point_id')->nullable()->after('company_id')->constrained('sales_points')->nullOnDelete();
            $table->foreignId('cash_register_id')->nullable()->after('sales_point_id')->constrained('cash_registers')->nullOnDelete();
            $table->foreignId('cash_register_shift_id')->nullable()->after('cash_register_id')->constrained('cash_register_shifts')->nullOnDelete();
            $table->index(['company_id', 'sales_point_id', 'cash_register_id', 'occurred_at'], 'pos_movements_company_point_register_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pos_cash_movements', function (Blueprint $table) {
            $table->dropIndex('pos_movements_company_point_register_at_idx');
            $table->dropConstrainedForeignId('cash_register_shift_id');
            $table->dropConstrainedForeignId('cash_register_id');
            $table->dropConstrainedForeignId('sales_point_id');
        });

        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropIndex('pos_sales_company_point_register_sold_idx');
            $table->dropConstrainedForeignId('cash_register_shift_id');
            $table->dropConstrainedForeignId('cash_register_id');
            $table->dropConstrainedForeignId('sales_point_id');
        });

        Schema::table('pos_shifts', function (Blueprint $table) {
            $table->dropIndex('pos_shifts_company_point_register_at_idx');
            $table->dropConstrainedForeignId('cash_register_shift_id');
            $table->dropConstrainedForeignId('cash_register_id');
            $table->dropConstrainedForeignId('sales_point_id');
        });

        Schema::table('inventory_products', function (Blueprint $table) {
            $table->dropUnique('inventory_products_company_code_unique');
            $table->unique('code');
            $table->dropIndex('inventory_products_company_category_idx');
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::dropIfExists('sales_point_inventory_product');
        Schema::dropIfExists('sales_point_product_category');
        Schema::dropIfExists('cash_register_shifts');
        Schema::dropIfExists('sales_point_user');
    }
};
