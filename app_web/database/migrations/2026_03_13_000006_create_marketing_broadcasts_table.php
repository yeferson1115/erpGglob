<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->string('channel'); // email, whatsapp, banner, sms
            $table->string('audience_type')->default('segment');
            $table->string('audience_segment')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('frequency')->nullable(); // daily, weekly, monthly, specific_date
            $table->timestamp('scheduled_for')->nullable();
            $table->boolean('is_automated')->default(true);
            $table->text('message');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_broadcasts');
    }
};
