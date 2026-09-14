<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'details',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Sensitive field patterns that should never be logged in audit_logs.details.
     *
     * These are filtered out to prevent passwords, API secrets, and other
     * credentials from being persisted in plaintext audit records (FINDING #10).
     *
     * @var array<int, string>
     */
    private const SENSITIVE_FIELDS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'api_secret',
        'api_secret_hash',
        'plain_api_secret',
        'secret',
        'token',
        'api_key',
    ];

    /**
     * Record a human-driven administrative action against a model.
     *
     * Automatically masks sensitive fields from $details to prevent credentials
     * from being logged (security finding #10 - audit logs storing raw form data).
     *
     * @param  array<string, mixed>  $details
     */
    public static function record(string $action, Model $subject, ?Request $request = null, array $details = []): self
    {
        return static::create([
            'user_id' => $request?->user()?->id,
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'details' => static::maskSensitiveFields($details),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    /**
     * Remove sensitive fields from an array to prevent credential leakage in logs.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function maskSensitiveFields(array $data): array
    {
        $masked = [];

        foreach ($data as $key => $value) {
            $keyLower = strtolower((string) $key);

            // Check if the key matches any sensitive pattern
            $isSensitive = false;
            foreach (self::SENSITIVE_FIELDS as $sensitivePattern) {
                if (str_contains($keyLower, strtolower($sensitivePattern))) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $masked[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                // Recursively mask nested arrays
                $masked[$key] = static::maskSensitiveFields($value);
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }
}
