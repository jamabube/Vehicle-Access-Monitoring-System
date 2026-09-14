<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * This historical migration is intentionally a no-op. Laravel's base users
     * migration already owns the password_reset_tokens table.
     */
    public function up(): void
    {
        // Intentionally empty.
    }

    /**
     * The baseline password-reset table must not be removed by this no-op.
     */
    public function down(): void
    {
        // Intentionally empty.
    }
};
