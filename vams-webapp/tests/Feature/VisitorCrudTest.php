<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Visitor;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitorCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function seedRolesAndPermissions(): void
    {
        $this->seedCoreSettings();
    }

    protected function actingAsRole(string $slug): User
    {
        $role = Role::where('slug', $slug)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $this->actingAs($user);

        return $user;
    }

    public function test_encoder_registrar_can_view_visitor_index(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        Visitor::factory()->count(3)->create();

        $response = $this->get(route('visitors.index'));

        $response->assertOk();
        $response->assertViewIs('visitors.index');
    }

    public function test_security_officer_can_view_but_not_create_visitor(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('security-officer');

        $this->get(route('visitors.index'))->assertOk();
        $this->get(route('visitors.create'))->assertForbidden();
    }

    public function test_encoder_registrar_can_create_visitor_and_audit_log_is_recorded(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        $payload = [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'contact_number' => '09171234567',
            'valid_id_type' => 'Driver License',
            'valid_id_number' => 'N01-23-456789',
            'address' => '123 Main St',
        ];

        $response = $this->post(route('visitors.store'), $payload);

        $response->assertRedirect(route('visitors.index'));
        $this->assertDatabaseHas('visitors', ['first_name' => 'Maria', 'last_name' => 'Santos']);

        $visitor = Visitor::where('valid_id_number', 'N01-23-456789')->firstOrFail();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'visitor.created',
            'subject_type' => $visitor->getMorphClass(),
            'subject_id' => $visitor->id,
        ]);
    }

    public function test_visitor_creation_requires_first_and_last_name(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        $response = $this->post(route('visitors.store'), [
            'contact_number' => '09171234567',
        ]);

        $response->assertSessionHasErrors(['first_name', 'last_name']);
    }

    public function test_encoder_registrar_can_update_visitor(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        $visitor = Visitor::factory()->create(['contact_number' => '09170000000']);

        $response = $this->put(route('visitors.update', $visitor), [
            'first_name' => $visitor->first_name,
            'last_name' => $visitor->last_name,
            'contact_number' => '09179999999',
        ]);

        $response->assertRedirect(route('visitors.index'));
        $this->assertDatabaseHas('visitors', ['id' => $visitor->id, 'contact_number' => '09179999999']);
    }

    public function test_administrator_can_delete_visitor_soft_delete(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('administrator');

        $visitor = Visitor::factory()->create();

        $response = $this->delete(route('visitors.destroy', $visitor));

        $response->assertRedirect(route('visitors.index'));
        $this->assertSoftDeleted('visitors', ['id' => $visitor->id]);
    }

    public function test_encoder_registrar_cannot_delete_visitor(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        $visitor = Visitor::factory()->create();

        $response = $this->delete(route('visitors.destroy', $visitor));

        $response->assertForbidden();
        $this->assertDatabaseHas('visitors', ['id' => $visitor->id, 'deleted_at' => null]);
    }

    public function test_security_officer_cannot_delete_visitor(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('security-officer');

        $visitor = Visitor::factory()->create();

        $response = $this->delete(route('visitors.destroy', $visitor));

        $response->assertForbidden();
        $this->assertDatabaseHas('visitors', ['id' => $visitor->id, 'deleted_at' => null]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('visitors.index'));

        $response->assertRedirect(route('login'));
    }
}
