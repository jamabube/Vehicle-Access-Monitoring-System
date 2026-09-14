<?php

namespace Tests\Feature;

use App\Models\RfidTag;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfidTagCrudTest extends TestCase
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

    public function test_security_officer_can_view_rfid_tag_index_but_not_create(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('security-officer');

        RfidTag::factory()->count(2)->create();

        $this->get(route('rfid-tags.index'))->assertOk();
        $this->get(route('rfid-tags.create'))->assertForbidden();
    }

    public function test_encoder_registrar_can_create_rfid_tag_and_audit_log_is_recorded(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        $response = $this->post(route('rfid-tags.store'), [
            'epc' => 'E2000019760801234567890A',
            'credential_type' => 'sticker',
            'status' => 'active',
            'issued_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect(route('rfid-tags.index'));
        $this->assertDatabaseHas('rfid_tags', ['epc' => 'E2000019760801234567890A']);

        $rfidTag = RfidTag::where('epc', 'E2000019760801234567890A')->firstOrFail();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'rfid_tag.created',
            'subject_type' => $rfidTag->getMorphClass(),
            'subject_id' => $rfidTag->id,
        ]);
    }

    public function test_rfid_tag_epc_must_be_unique(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        RfidTag::factory()->create(['epc' => 'DUPLICATE-EPC']);

        $response = $this->post(route('rfid-tags.store'), [
            'epc' => 'DUPLICATE-EPC',
            'credential_type' => 'card',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('epc');
    }

    public function test_rfid_tag_requires_valid_credential_type_and_status(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        $response = $this->post(route('rfid-tags.store'), [
            'epc' => 'SOME-EPC-1',
            'credential_type' => 'invalid-type',
            'status' => 'invalid-status',
        ]);

        $response->assertSessionHasErrors(['credential_type', 'status']);
    }

    public function test_encoder_registrar_can_update_rfid_tag_but_not_delete_it(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        $rfidTag = RfidTag::factory()->create(['status' => 'active']);

        $updateResponse = $this->put(route('rfid-tags.update', $rfidTag), [
            'epc' => $rfidTag->epc,
            'credential_type' => $rfidTag->credential_type,
            'status' => 'lost',
        ]);

        $updateResponse->assertRedirect(route('rfid-tags.index'));
        $this->assertDatabaseHas('rfid_tags', ['id' => $rfidTag->id, 'status' => 'lost']);

        $deleteResponse = $this->delete(route('rfid-tags.destroy', $rfidTag));

        $deleteResponse->assertForbidden();
        $this->assertDatabaseHas('rfid_tags', ['id' => $rfidTag->id]);
    }

    public function test_administrator_can_delete_rfid_tag(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('administrator');

        $rfidTag = RfidTag::factory()->create();

        $deleteResponse = $this->delete(route('rfid-tags.destroy', $rfidTag));

        $deleteResponse->assertRedirect(route('rfid-tags.index'));
        $this->assertDatabaseMissing('rfid_tags', ['id' => $rfidTag->id]);
    }

    public function test_security_officer_cannot_update_rfid_tag(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('security-officer');

        $rfidTag = RfidTag::factory()->create();

        $response = $this->put(route('rfid-tags.update', $rfidTag), [
            'epc' => $rfidTag->epc,
            'credential_type' => $rfidTag->credential_type,
            'status' => 'inactive',
        ]);

        $response->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('rfid-tags.index'));

        $response->assertRedirect(route('login'));
    }
}
