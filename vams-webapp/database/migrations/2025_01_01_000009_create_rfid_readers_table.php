<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfid_readers', function (Blueprint $table) {
            $table->id();
            $table->string('device_name'); // e.g. "Main Gate Reader"
            $table->string('device_code')->unique(); // internal identifier
            $table->string('model')->default('S4A UHF-202415');
            $table->string('location')->nullable();
            $table->string('ip_address', 45)->nullable();
            // Credential used by the RFID Listener/Device Service to authenticate to the API.
            $table->string('api_key')->unique();
            $table->string('api_secret_hash');
            $table->enum('status', ['online', 'offline', 'disabled'])->default('offline');
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfid_readers');
    }
};
