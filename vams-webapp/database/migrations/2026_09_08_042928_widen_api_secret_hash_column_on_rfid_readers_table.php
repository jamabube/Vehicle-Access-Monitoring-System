<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Widen rfid_readers.api_secret_hash from VARCHAR(255) to TEXT.
     *
     * The column now stores Crypt::encryptString() ciphertext (AES-256 +
     * base64 + JSON envelope), not a bcrypt hash — see context/RULES.md §5
     * for why. That ciphertext runs ~300+ characters, which overflows the
     * original VARCHAR(255) definition on strict-mode MySQL. Raw SQL is used
     * instead of Schema::table()->change() to avoid requiring doctrine/dbal.
     *
     * SQLite (used by the test suite) has no fixed-length column enforcement
     * and does not support `ALTER TABLE ... MODIFY`, so this only runs the
     * statement on MySQL; it is a no-op elsewhere.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE rfid_readers MODIFY api_secret_hash TEXT NOT NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE rfid_readers MODIFY api_secret_hash VARCHAR(255) NOT NULL');
        }
    }
};
