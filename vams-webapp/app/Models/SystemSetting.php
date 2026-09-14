<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("system_setting:{$key}", function () use ($key, $default) {
            return static::query()->where('key', $key)->value('value') ?? $default;
        });
    }

    public static function set(string $key, ?string $value, ?string $description = null): self
    {
        $setting = static::query()->updateOrCreate(
            ['key' => $key],
            array_filter(['value' => $value, 'description' => $description], fn ($v) => $v !== null)
        );

        Cache::forget("system_setting:{$key}");

        return $setting;
    }
}
