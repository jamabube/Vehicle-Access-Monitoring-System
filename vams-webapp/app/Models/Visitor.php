<?php

namespace App\Models;

use App\Services\CodeGenerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Visitor extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'visitor_code',
        'first_name',
        'last_name',
        'contact_number',
        'valid_id_type',
        'valid_id_number',
        'address',
    ];

    protected static function booted(): void
    {
        static::creating(function (Visitor $visitor) {
            if (empty($visitor->visitor_code)) {
                $visitor->visitor_code = CodeGenerator::generate('visitors');
            }
        });
    }

    public function visits(): HasMany
    {
        return $this->hasMany(VisitorVisit::class);
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}
