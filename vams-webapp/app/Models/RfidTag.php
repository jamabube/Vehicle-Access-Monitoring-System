<?php

namespace App\Models;

use App\Services\CodeGenerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RfidTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'tag_code',
        'epc',
        'credential_type',
        'status',
        'issued_at',
        'expires_at',
        'notes',
    ];

    protected static function booted(): void
    {
        static::creating(function (RfidTag $rfidTag) {
            if (empty($rfidTag->tag_code)) {
                $rfidTag->tag_code = CodeGenerator::generate('rfid_tags');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RfidAssignment::class);
    }

    public function detections(): HasMany
    {
        return $this->hasMany(RfidDetection::class);
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(AccessLog::class);
    }

    public function visitorVisits(): HasMany
    {
        return $this->hasMany(VisitorVisit::class);
    }

    /**
     * The assignment currently in effect for this tag (not yet released).
     */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(RfidAssignment::class)->whereNull('released_at')->latestOfMany();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
