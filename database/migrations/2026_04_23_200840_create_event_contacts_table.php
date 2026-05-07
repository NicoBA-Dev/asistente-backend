<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_contacts', function (Blueprint $table) {
            $table->uuid('event_id');
            $table->uuid('contact_id');
            $table->string('role', 50)->default('participant');
            
            $table->primary(['event_id', 'contact_id']);
            $table->foreign('event_id')->references('id')->on('events')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            
            $table->index('contact_id', 'idx_event_contacts_contact_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_contacts');
    }
};