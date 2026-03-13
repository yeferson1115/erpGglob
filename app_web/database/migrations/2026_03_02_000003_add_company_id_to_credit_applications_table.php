<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('credit_applications', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('city')->constrained('companies')->nullOnDelete();
            $table->dropColumn('company_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_applications', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('company_id');
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
