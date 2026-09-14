<?php

namespace App\Models;

use App\Services\CodeGenerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Selectable vehicle types, kept here so the form, the validator and the
     * index filter all read from one list. Free text produced inconsistent
     * records ("van", "Sedan", "SUV"), which made filtering unreliable.
     *
     * @var array<int, string>
     */
    public const TYPES = [
        'Motorcycle',
        'Tricycle',
        'Sedan',
        'SUV',
        'Van',
        'Pickup',
        'Multicab',
        'Jeepney',
        'Truck',
        'Bus',
        'Other',
    ];

    /**
     * Suggestions only — these appear as a picklist but the field stays
     * typeable, because no fixed list of makes or colours can be complete.
     *
     * @var array<int, string>
     */
    public const COMMON_MAKES = [
        'Toyota', 'Mitsubishi', 'Honda', 'Nissan', 'Isuzu', 'Suzuki', 'Hyundai',
        'Kia', 'Ford', 'Chevrolet', 'Mazda', 'Foton', 'Yamaha', 'Kawasaki',
    ];

    /**
     * Common models for each make, so the Model field can narrow itself once a
     * make is chosen. Suggestions only — the field stays typeable.
     *
     * @var array<string, array<int, string>>
     */
    public const COMMON_MODELS = [
        'Toyota' => ['Vios', 'Wigo', 'Innova', 'Fortuner', 'Hilux', 'Avanza', 'Rush', 'Corolla Altis', 'Hiace', 'Raize', 'Camry'],
        'Mitsubishi' => ['Mirage', 'Mirage G4', 'Xpander', 'Montero Sport', 'Strada', 'L300', 'Adventure'],
        'Honda' => ['Civic', 'City', 'BR-V', 'CR-V', 'Brio', 'Mobilio', 'Click', 'Beat', 'XRM'],
        'Nissan' => ['Almera', 'Navara', 'Terra', 'Juke', 'X-Trail', 'Urvan'],
        'Isuzu' => ['D-Max', 'MU-X', 'Crosswind', 'Traviz'],
        'Suzuki' => ['Ertiga', 'Swift', 'Celerio', 'Jimny', 'Carry', 'Raider'],
        'Hyundai' => ['Accent', 'Tucson', 'Starex', 'Eon', 'Reina'],
        'Kia' => ['Picanto', 'Soluto', 'Seltos', 'Sportage', 'Carnival'],
        'Ford' => ['Ranger', 'Everest', 'EcoSport', 'Territory'],
        'Chevrolet' => ['Colorado', 'Trailblazer', 'Spark'],
        'Mazda' => ['Mazda3', 'CX-5', 'CX-30', 'BT-50'],
        'Foton' => ['Gratour', 'Tornado', 'Transvan'],
        'Yamaha' => ['Mio', 'Nmax', 'Aerox', 'Sniper', 'YZF-R15'],
        'Kawasaki' => ['Barako', 'Rouser', 'Ninja', 'CT100'],
    ];

    /** @var array<int, string> */
    public const COMMON_COLORS = [
        'White', 'Pearl White', 'Black', 'Silver', 'Grey', 'Red', 'Blue',
        'Green', 'Yellow', 'Orange', 'Brown', 'Beige', 'Maroon',
    ];

    protected $fillable = [
        'vehicle_code',
        'plate_number',
        'vehicle_type',
        'make',
        'model',
        'color',
        'owner_type',
        'employee_id',
        'status',
        // current_state / last_seen_at are RFID-driven (RfidAccessResolver
        // forceFill) or admin-overridden via updateState(). Kept off fillable
        // so ordinary vehicle updates cannot mass-assign gate state (FINDING #11).
    ];

    protected static function booted(): void
    {
        static::creating(function (Vehicle $vehicle) {
            if (empty($vehicle->vehicle_code)) {
                $vehicle->vehicle_code = CodeGenerator::generate('vehicles');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function visitorVisits(): HasMany
    {
        return $this->hasMany(VisitorVisit::class);
    }

    public function rfidAssignments(): HasMany
    {
        return $this->hasMany(RfidAssignment::class);
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(AccessLog::class);
    }

    public function isInside(): bool
    {
        return $this->current_state === 'inside';
    }
}
