<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->boolean('tracks_inventory')->default(false);
            $table->integer('stock_quantity')->nullable();
            $table->integer('minimum_stock')->nullable();
            $table->boolean('is_combo')->default(false);
            $table->json('combo_product_codes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_products');
    }
};
