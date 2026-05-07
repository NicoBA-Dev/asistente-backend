<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->uuid('user_id');
            $table->string('push_token', 255);
            $table->string('platform', 50); // 'Android', 'iOS', 'Web'
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_seen_at')->useCurrent();
            $table->timestampsTz();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'push_token']);
        });

        DB::statement('CREATE TRIGGER update_devices_modtime BEFORE UPDATE ON user_devices FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};