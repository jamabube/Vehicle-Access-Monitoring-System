<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores nonces already seen from device requests, so a captured and
     * replayed request is rejected within the configured TTL window.
     */
    public function up(): void
    {
        Schema::create('api_request_nonces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfid_reader_id')->constrained('rfid_readers')->cascadeOnDelete();
            $table->string('nonce');
            $table->dateTime('expires_at');
            $table->timestamps();

            $table->unique(['rfid_reader_id', 'nonce']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_request_nonces');
    }
};
