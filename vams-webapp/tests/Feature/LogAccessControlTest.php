<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Security regression tests for access-control gates on log-viewing routes.
 *
 * FINDING-14: The audit-logs route must be gated by 'can:audit_logs.view'.
 * Only the Administrator role has this permission; Security Officer and
 * Encoder/Registrar must be denied (403).
 */
class LogAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    private function userWithRole(string $roleSlug): User
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'status'  => 'active',
        ]);
    }

    // ── audit-logs ────────────────────────────────────────────────────────────

    public function test_administrator_can_view_audit_logs(): void
    {
        $response = $this->actingAs($this->userWithRole('administrator'))
            ->get(route('audit-logs.index'));

        // Administrator has all permissions, including audit_logs.view.
        $response->assertOk();
    }

    public function test_security_officer_is_denied_audit_logs(): void
    {
        $response = $this->actingAs($this->userWithRole('security-officer'))
            ->get(route('audit-logs.index'));

        // Security Officer has no audit_logs.view permission → must receive 403.
        $response->assertForbidden();
    }

    public function test_encoder_registrar_is_denied_audit_logs(): void
    {
        $response = $this->actingAs($this->userWithRole('encoder-registrar'))
            ->get(route('audit-logs.index'));

        // Encoder/Registrar has no audit_logs.view permission → must receive 403.
        $response->assertForbidden();
    }

    // ── access-logs (baseline — must still work correctly after any changes) ──

    public function test_administrator_can_view_access_logs(): void
    {
        $response = $this->actingAs($this->userWithRole('administrator'))
            ->get(route('access-logs.index'));

        $response->assertOk();
    }

    public function test_security_officer_can_view_access_logs(): void
    {
        // Security Officer has access_logs.view — read-only monitoring role.
        $response = $this->actingAs($this->userWithRole('security-officer'))
            ->get(route('access-logs.index'));

        $response->assertOk();
    }

    public function test_encoder_registrar_is_denied_access_logs(): void
    {
        // Encoder/Registrar does not have access_logs.view.
        $response = $this->actingAs($this->userWithRole('encoder-registrar'))
            ->get(route('access-logs.index'));

        $response->assertForbidden();
    }

    // ── system-logs ──────────────────────────────────────────────────────────

    public function test_administrator_can_view_system_logs(): void
    {
        $response = $this->actingAs($this->userWithRole('administrator'))
            ->get(route('system-logs.index'));

        $response->assertOk();
    }

    public function test_security_officer_is_denied_system_logs(): void
    {
        $response = $this->actingAs($this->userWithRole('security-officer'))
            ->get(route('system-logs.index'));

        $response->assertForbidden();
    }
}
