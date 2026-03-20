<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedSmallInteger('pos_locations_count')->default(0)->after('pos_boxes');
            $table->json('pos_locations')->nullable()->after('pos_locations_count');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['pos_locations_count', 'pos_locations']);
        });
    }
};
