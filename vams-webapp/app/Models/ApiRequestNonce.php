<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores nonces already seen from device requests, so a captured and
 * replayed request is rejected within the configured TTL window.
 */
class ApiRequestNonce extends Model
{
    use HasFactory, MassPrunable;

    protected $fillable = [
        'rfid_reader_id',
        'nonce',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function rfidReader(): BelongsTo
    {
        return $this->belongsTo(RfidReader::class);
    }

    /**
     * Get the prunable model query.
     *
     * Prune nonces that expired more than 1 hour ago (stale beyond any tolerance).
     * This prevents unbounded growth of the api_request_nonces table.
     */
    public function prunable(): Builder
    {
        return static::where('expires_at', '<', now()->subHour());
    }
}
