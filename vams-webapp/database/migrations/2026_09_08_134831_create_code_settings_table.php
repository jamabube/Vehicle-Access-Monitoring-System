<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the code_settings table for auto-generated code configuration.
     *
     * Each entity type (employees, visitors, vehicles, etc.) has a row defining
     * its code format (prefix, length, suffix) and a counter (next_number) that
     * increments with each new record. The CodeGenerator service uses these
     * settings to produce codes like "EMP-00001", "VIS-00042", "VEH-00123".
     */
    public function up(): void
    {
        Schema::create('code_settings', function (Blueprint $table) {
            $table->id();
            $table->string('entity')->unique(); // e.g. 'employees', 'visitors', 'rfid_tags'
            $table->string('prefix')->default(''); // e.g. 'EMP-', 'VIS-'
            $table->unsignedInteger('length')->default(5); // zero-padded numeric part length
            $table->string('suffix')->default(''); // e.g. '', '-2026'
            $table->unsignedBigInteger('next_number')->default(1); // atomically incremented counter
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('code_settings');
    }
};
