<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_products', function (Blueprint $table) {
            $table->foreignId('product_category_id')
                ->nullable()
                ->after('name')
                ->constrained('product_categories')
                ->nullOnDelete();
            $table->decimal('price', 12, 2)->default(0)->after('product_category_id');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_category_id');
            $table->dropColumn('price');
        });
    }
};
