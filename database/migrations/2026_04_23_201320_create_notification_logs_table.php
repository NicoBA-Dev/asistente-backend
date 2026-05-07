<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('reminder_id');
            $table->uuid('device_id')->nullable();
            $table->timestampTz('sent_at')->useCurrent();
            $table->string('status', 20)->default('success');
            $table->text('error_message')->nullable();

            $table->foreign('reminder_id')->references('id')->on('reminders')->onDelete('cascade');
            $table->foreign('device_id')->references('id')->on('user_devices')->onDelete('set null');
        });

        // Esta tabla NO lleva trigger de updated_at por diseño inmutable.
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};