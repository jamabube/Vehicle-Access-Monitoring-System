<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'rfid_detection_id',
        'rfid_tag_id',
        'vehicle_id',
        'visitor_visit_id',
        'direction',
        'decision',
        'denial_reason',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function rfidDetection(): BelongsTo
    {
        return $this->belongsTo(RfidDetection::class);
    }

    public function rfidTag(): BelongsTo
    {
        return $this->belongsTo(RfidTag::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function visitorVisit(): BelongsTo
    {
        return $this->belongsTo(VisitorVisit::class);
    }

    public function isAuthorized(): bool
    {
        return $this->decision === 'authorized';
    }
}
