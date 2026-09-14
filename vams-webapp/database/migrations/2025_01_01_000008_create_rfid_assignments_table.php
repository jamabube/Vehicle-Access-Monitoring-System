<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfid_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfid_tag_id')->constrained('rfid_tags')->cascadeOnDelete();
            // A sticker is assigned to a vehicle for its lifetime; a card is assigned to a visitor visit.
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('visitor_visit_id')->nullable()->constrained('visitor_visits')->nullOnDelete();
            $table->dateTime('assigned_at');
            $table->dateTime('released_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfid_assignments');
    }
};
