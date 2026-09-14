<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfid_detection_id')->nullable()->constrained('rfid_detections')->nullOnDelete();
            $table->foreignId('rfid_tag_id')->nullable()->constrained('rfid_tags')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('visitor_visit_id')->nullable()->constrained('visitor_visits')->nullOnDelete();
            $table->enum('direction', ['entry', 'exit'])->nullable();
            $table->enum('decision', ['authorized', 'denied'])->default('denied');
            $table->string('denial_reason')->nullable(); // unknown_credential, inactive, expired, no_vehicle, etc.
            $table->dateTime('occurred_at');
            $table->timestamps();

            $table->index(['decision', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_logs');
    }
};
