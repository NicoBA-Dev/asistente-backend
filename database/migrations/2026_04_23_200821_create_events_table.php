<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('user_id');
            $table->string('title', 200);
            $table->string('category', 50)->default('Otro');
            $table->timestampTz('event_date_utc');
            $table->timestampTz('end_date_utc')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('mongo_log_id', 24)->unique();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // Agregamos las restricciones CHECK mediante SQL puro para PostgreSQL
        DB::statement("ALTER TABLE events ADD CONSTRAINT chk_category CHECK (category IN ('Trabajo', 'Personal', 'Urgente', 'Reunion', 'Otro'));");
        DB::statement("ALTER TABLE events ADD CONSTRAINT chk_status CHECK (status IN ('pending', 'completed', 'cancelled', 'conflict'));");
        DB::statement("ALTER TABLE events ADD CONSTRAINT chk_end_after_start CHECK (end_date_utc IS NULL OR end_date_utc > event_date_utc);");

        DB::statement('CREATE INDEX idx_events_active ON events(user_id, event_date_utc) WHERE deleted_at IS NULL;');
        DB::statement('CREATE TRIGGER update_events_modtime BEFORE UPDATE ON events FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();');
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};