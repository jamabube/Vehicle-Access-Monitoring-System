<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RfidDetection extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_uuid',
        'rfid_reader_id',
        'epc',
        'rfid_tag_id',
        'rssi',
        'antenna',
        'detected_at',
        'received_at',
        'is_duplicate',
    ];

    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
            'received_at' => 'datetime',
            'is_duplicate' => 'boolean',
        ];
    }

    public function rfidReader(): BelongsTo
    {
        return $this->belongsTo(RfidReader::class);
    }

    public function rfidTag(): BelongsTo
    {
        return $this->belongsTo(RfidTag::class);
    }

    public function accessLog(): HasOne
    {
        return $this->hasOne(AccessLog::class);
    }
}
