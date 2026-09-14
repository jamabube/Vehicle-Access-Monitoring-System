<?php

namespace App\Models;

use App\Services\CodeGenerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfidAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'assignment_code',
        'rfid_tag_id',
        'vehicle_id',
        'visitor_visit_id',
        'assigned_at',
        'released_at',
        'assigned_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (RfidAssignment $rfidAssignment) {
            if (empty($rfidAssignment->assignment_code)) {
                $rfidAssignment->assignment_code = CodeGenerator::generate('rfid_assignments');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'released_at' => 'datetime',
        ];
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

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function isActive(): bool
    {
        return $this->released_at === null;
    }
}
