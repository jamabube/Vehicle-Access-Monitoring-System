<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfid_tags', function (Blueprint $table) {
            $table->id();
            $table->string('epc')->unique(); // Electronic Product Code read from the credential
            $table->enum('credential_type', ['sticker', 'card'])->default('card');
            // sticker = permanent employee windshield sticker
            // card    = temporary visitor PVC card, returned for reuse after checkout
            $table->enum('status', ['active', 'inactive', 'lost', 'disabled', 'expired'])->default('active');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfid_tags');
    }
};
