<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('event_id');
            $table->integer('minutes_before');
            $table->string('method', 20)->default('push');
            $table->timestampTz('notify_at');
            $table->timestampsTz();

            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->unique(['event_id', 'minutes_before', 'method']);
        });

        // Restricción para asegurar que el tiempo sea siempre positivo
        DB::statement("ALTER TABLE reminders ADD CONSTRAINT chk_minutes_before CHECK (minutes_before > 0);");
        
        DB::statement('CREATE INDEX idx_reminders_notify_at ON reminders(notify_at);');
        DB::statement('CREATE TRIGGER update_reminders_modtime BEFORE UPDATE ON reminders FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();');
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};