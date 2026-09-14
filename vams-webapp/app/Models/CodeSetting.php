<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CodeSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity',
        'prefix',
        'length',
        'suffix',
        'next_number',
    ];

    protected function casts(): array
    {
        return [
            'length' => 'integer',
            'next_number' => 'integer',
        ];
    }

    /**
     * Get the code setting for a specific entity.
     */
    public static function forEntity(string $entity): ?self
    {
        return static::where('entity', $entity)->first();
    }

    /**
     * Generate the next code for this entity and increment the counter atomically.
     */
    public function generateNext(): string
    {
        // Fetch the current number and increment atomically using a database-level increment
        $number = $this->next_number;
        $this->increment('next_number');

        // Format: prefix + zero-padded number + suffix
        $paddedNumber = str_pad((string) $number, $this->length, '0', STR_PAD_LEFT);

        return $this->prefix . $paddedNumber . $this->suffix;
    }
}
