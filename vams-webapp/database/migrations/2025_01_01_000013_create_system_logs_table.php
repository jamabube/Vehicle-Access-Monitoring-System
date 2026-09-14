<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('level', ['debug', 'info', 'warning', 'error', 'critical'])->default('info');
            $table->string('source')->nullable(); // e.g. "rfid_reader", "api", "auth", "rate_limiter"
            $table->foreignId('rfid_reader_id')->nullable()->constrained('rfid_readers')->nullOnDelete();
            $table->string('message');
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['level', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_logs');
    }
};
