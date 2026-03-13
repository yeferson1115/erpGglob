<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('plan_name')->default('Sin plan')->after('contact_name');
            $table->string('service_status')->default('inactive')->index()->after('plan_name');
            $table->date('started_at')->nullable()->after('service_status');
            $table->date('active_until')->nullable()->after('started_at');

            $table->boolean('gglob_cloud_enabled')->default(false)->after('active_until');
            $table->boolean('gglob_pay_enabled')->default(false)->after('gglob_cloud_enabled');
            $table->boolean('gglob_pos_enabled')->default(false)->after('gglob_pay_enabled');
            $table->string('pos_mode')->default('mono')->after('gglob_pos_enabled');
            $table->unsignedTinyInteger('pos_boxes')->default(1)->after('pos_mode');
            $table->boolean('gglob_accounting_enabled')->default(false)->after('pos_boxes');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'plan_name',
                'service_status',
                'started_at',
                'active_until',
                'gglob_cloud_enabled',
                'gglob_pay_enabled',
                'gglob_pos_enabled',
                'pos_mode',
                'pos_boxes',
                'gglob_accounting_enabled',
            ]);
        });
    }
};
