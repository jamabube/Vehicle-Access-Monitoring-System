<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected function seedRolesAndPermissions(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    public function test_administrator_role_receives_every_permission(): void
    {
        $this->seedRolesAndPermissions();

        $admin = Role::where('slug', 'administrator')->firstOrFail();
        $totalPermissions = Permission::count();

        $this->assertSame($totalPermissions, $admin->permissions()->count());
    }

    public function test_security_officer_role_is_limited_to_read_only_permissions(): void
    {
        $this->seedRolesAndPermissions();

        $officer = Role::where('slug', 'security-officer')->firstOrFail();
        $slugs = $officer->permissions()->pluck('slug')->all();

        $this->assertContains('access_logs.view', $slugs);
        $this->assertContains('dashboard.view', $slugs);
        $this->assertNotContains('employees.create', $slugs);
        $this->assertNotContains('users.view', $slugs);
    }

    public function test_encoder_registrar_role_can_manage_operational_resources_but_not_users(): void
    {
        $this->seedRolesAndPermissions();

        $encoder = Role::where('slug', 'encoder-registrar')->firstOrFail();
        $slugs = $encoder->permissions()->pluck('slug')->all();

        $this->assertContains('employees.create', $slugs);
        $this->assertContains('vehicles.update', $slugs);
        $this->assertContains('rfid_tags.update', $slugs);
        $this->assertNotContains('users.view', $slugs);
        $this->assertNotContains('system_settings.update', $slugs);
        $this->assertNotContains('vehicles.delete', $slugs);
        $this->assertNotContains('employees.delete', $slugs);
        $this->assertNotContains('visitors.delete', $slugs);
        $this->assertNotContains('visitor_visits.delete', $slugs);
        $this->assertNotContains('rfid_tags.delete', $slugs);
        $this->assertNotContains('rfid_assignments.delete', $slugs);
        $this->assertNotContains('rfid_readers.delete', $slugs);
    }

    public function test_user_has_permission_helper_reflects_role_permissions(): void
    {
        $this->seedRolesAndPermissions();

        $officerRole = Role::where('slug', 'security-officer')->firstOrFail();
        $user = User::factory()->create(['role_id' => $officerRole->id]);

        $this->assertTrue($user->hasPermission('access_logs.view'));
        $this->assertFalse($user->hasPermission('employees.create'));
    }

    public function test_user_without_a_role_has_no_permissions(): void
    {
        $user = User::factory()->create(['role_id' => null]);

        $this->assertFalse($user->hasPermission('dashboard.view'));
    }

    public function test_role_middleware_blocks_users_without_the_required_role(): void
    {
        $this->seedRolesAndPermissions();

        Route::middleware(['web', 'auth', 'role:administrator'])
            ->get('/__test/admin-only', fn () => 'ok');

        $officerRole = Role::where('slug', 'security-officer')->firstOrFail();
        $user = User::factory()->create(['role_id' => $officerRole->id]);

        $response = $this->actingAs($user)->get('/__test/admin-only');

        $response->assertForbidden();
    }

    public function test_role_middleware_allows_users_with_the_required_role(): void
    {
        $this->seedRolesAndPermissions();

        Route::middleware(['web', 'auth', 'role:administrator'])
            ->get('/__test/admin-only-2', fn () => 'ok');

        $adminRole = Role::where('slug', 'administrator')->firstOrFail();
        $user = User::factory()->create(['role_id' => $adminRole->id]);

        $response = $this->actingAs($user)->get('/__test/admin-only-2');

        $response->assertOk();
        $response->assertSee('ok');
    }

    public function test_gate_before_hook_authorizes_based_on_permission_slug(): void
    {
        $this->seedRolesAndPermissions();

        $encoderRole = Role::where('slug', 'encoder-registrar')->firstOrFail();
        $user = User::factory()->create(['role_id' => $encoderRole->id]);

        $this->assertTrue($user->can('employees.create'));
        $this->assertFalse($user->can('users.create'));
    }

    public function test_suspended_account_is_logged_out_on_next_request(): void
    {
        $this->seedRolesAndPermissions();

        $adminRole = Role::where('slug', 'administrator')->firstOrFail();
        $user = User::factory()->create(['role_id' => $adminRole->id, 'status' => 'active']);

        $this->actingAs($user);

        // Simulate the account being suspended mid-session.
        $user->update(['status' => 'suspended']);

        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_locked_account_is_logged_out_on_next_request(): void
    {
        $this->seedRolesAndPermissions();

        $adminRole = Role::where('slug', 'administrator')->firstOrFail();
        $user = User::factory()->create(['role_id' => $adminRole->id]);

        $this->actingAs($user);

        $user->update(['locked_until' => now()->addMinutes(15)]);

        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
