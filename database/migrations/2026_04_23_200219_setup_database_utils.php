<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Habilitar la extensión para generar UUIDs v4
        DB::statement('CREATE EXTENSION IF NOT EXISTS "uuid-ossp";');

        // Función universal para actualizar el campo updated_at automáticamente
        DB::statement('
            CREATE OR REPLACE FUNCTION update_updated_at_column()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.updated_at = CURRENT_TIMESTAMP;
                RETURN NEW;
            END;
            $$ language \'plpgsql\';
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS update_updated_at_column();');
        DB::statement('DROP EXTENSION IF EXISTS "uuid-ossp";');
    }
};