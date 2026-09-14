<?php

namespace App\Models;

use App\Services\CodeGenerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RfidReader extends Model
{
    use HasFactory;

    protected $fillable = [
        'device_name',
        'device_code',
        'model',
        'location',
        'ip_address',
        'api_key',
        'api_secret_hash',
        'status',
        'last_heartbeat_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (RfidReader $rfidReader) {
            if (empty($rfidReader->device_code)) {
                $rfidReader->device_code = CodeGenerator::generate('rfid_readers');
            }
        });
    }

    protected $hidden = [
        'api_secret_hash',
    ];

    protected function casts(): array
    {
        return [
            'last_heartbeat_at' => 'datetime',
        ];
    }

    public function detections(): HasMany
    {
        return $this->hasMany(RfidDetection::class);
    }

    public function apiRequestNonces(): HasMany
    {
        return $this->hasMany(ApiRequestNonce::class);
    }

    public function systemLogs(): HasMany
    {
        return $this->hasMany(SystemLog::class);
    }

    public function isOnline(): bool
    {
        return $this->status === 'online';
    }
}
