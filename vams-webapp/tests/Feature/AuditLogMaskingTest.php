<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\RfidReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FINDING #10: Sensitive field masking in audit logs.
 */
class AuditLogMaskingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCoreSettings();
    }

    public function test_audit_log_masks_password_fields(): void
    {
        $employee = Employee::factory()->create();

        AuditLog::record('employee.updated', $employee, null, [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'password' => 'SuperSecret123!',
            'password_confirmation' => 'SuperSecret123!',
        ]);

        $log = AuditLog::latest()->first();

        $this->assertEquals('[REDACTED]', $log->details['password']);
        $this->assertEquals('[REDACTED]', $log->details['password_confirmation']);
        $this->assertEquals('John', $log->details['first_name']);
        $this->assertEquals('Doe', $log->details['last_name']);
    }

    public function test_audit_log_masks_api_secrets(): void
    {
        $reader = RfidReader::factory()->create();

        AuditLog::record('rfid_reader.updated', $reader, null, [
            'device_name' => 'Main Gate Reader',
            'api_secret' => 'very-secret-key-12345',
            'api_secret_hash' => 'hashed-value',
            'api_key' => 'key-abc',
        ]);

        $log = AuditLog::latest()->first();

        $this->assertEquals('[REDACTED]', $log->details['api_secret']);
        $this->assertEquals('[REDACTED]', $log->details['api_secret_hash']);
        $this->assertEquals('[REDACTED]', $log->details['api_key']);
        $this->assertEquals('Main Gate Reader', $log->details['device_name']);
    }

    public function test_audit_log_masks_nested_sensitive_fields(): void
    {
        $employee = Employee::factory()->create();

        AuditLog::record('employee.updated', $employee, null, [
            'first_name' => 'Jane',
            'credentials' => [
                'username' => 'jdoe',
                'password' => 'secret123',
                'api_key' => 'key-12345',
            ],
        ]);

        $log = AuditLog::latest()->first();

        $this->assertEquals('Jane', $log->details['first_name']);
        $this->assertEquals('jdoe', $log->details['credentials']['username']);
        $this->assertEquals('[REDACTED]', $log->details['credentials']['password']);
        $this->assertEquals('[REDACTED]', $log->details['credentials']['api_key']);
    }
}
