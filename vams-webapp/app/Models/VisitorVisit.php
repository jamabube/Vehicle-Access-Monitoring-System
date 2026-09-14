<?php

namespace App\Models;

use App\Services\CodeGenerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VisitorVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'visit_code',
        'visitor_id',
        'vehicle_id',
        'rfid_tag_id',
        'purpose',
        'host_name',
        'valid_from',
        'valid_until',
        'status',
        'checked_out_at',
        'registered_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (VisitorVisit $visitorVisit) {
            if (empty($visitorVisit->visit_code)) {
                $visitorVisit->visit_code = CodeGenerator::generate('visitor_visits');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'checked_out_at' => 'datetime',
        ];
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function rfidTag(): BelongsTo
    {
        return $this->belongsTo(RfidTag::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function rfidAssignments(): HasMany
    {
        return $this->hasMany(RfidAssignment::class);
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(AccessLog::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->isPast();
    }
}
