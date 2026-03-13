<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('credit_applications', function (Blueprint $table) {
            $table->string('phone_verification_code_hash')->nullable()->after('selfie_with_id_path');
            $table->timestamp('phone_verification_expires_at')->nullable()->after('phone_verification_code_hash');
            $table->timestamp('phone_verified_at')->nullable()->after('phone_verification_expires_at');
            $table->string('phone_verified_number', 20)->nullable()->after('phone_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('credit_applications', function (Blueprint $table) {
            $table->dropColumn([
                'phone_verification_code_hash',
                'phone_verification_expires_at',
                'phone_verified_at',
                'phone_verified_number',
            ]);
        });
    }
};
