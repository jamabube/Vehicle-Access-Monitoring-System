<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitor_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_id')->constrained('visitors')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('rfid_tag_id')->nullable()->constrained('rfid_tags')->nullOnDelete();
            $table->string('purpose')->nullable();
            $table->string('host_name')->nullable(); // employee or department being visited
            $table->dateTime('valid_from');
            $table->dateTime('valid_until');
            $table->enum('status', ['active', 'checked_out', 'expired', 'cancelled'])->default('active');
            $table->timestamp('checked_out_at')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_visits');
    }
};
