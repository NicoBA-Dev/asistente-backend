<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuid_generate_v4()'));
            $table->string('name', 100);
            $table->string('email', 150);
            $table->string('password_hash', 255);
            $table->string('timezone', 50)->default('America/La_Paz');
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        // Índice parcial único: permite el mismo email si el anterior fue borrado (deleted_at IS NOT NULL)
        DB::statement('CREATE UNIQUE INDEX idx_users_email_active ON users(email) WHERE deleted_at IS NULL;');
        
        // Índice para optimizar búsquedas de login
        DB::statement('CREATE INDEX idx_users_active ON users(email) WHERE deleted_at IS NULL;');

        // Activar el trigger de updated_at
        DB::statement('CREATE TRIGGER update_users_modtime BEFORE UPDATE ON users FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();');
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};