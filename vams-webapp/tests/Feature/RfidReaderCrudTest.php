<?php

namespace Tests\Feature;

use App\Models\CodeSetting;
use App\Models\RfidReader;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class RfidReaderCrudTest extends TestCase
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

    public function test_security_officer_can_view_rfid_reader_index_but_not_create(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('security-officer');

        RfidReader::factory()->count(2)->create();

        $this->get(route('rfid-readers.index'))->assertOk();
        $this->get(route('rfid-readers.create'))->assertForbidden();
    }

    public function test_encoder_registrar_can_create_rfid_reader_with_generated_credentials(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        $response = $this->post(route('rfid-readers.store'), [
            'device_name' => 'Main Gate Reader',
            'device_code' => 'RDR-0001',
            'model' => 'S4A UHF-202415',
            'location' => 'Main Gate',
            'ip_address' => '192.168.1.10',
            'status' => 'offline',
        ]);

        $rfidReader = RfidReader::where('device_code', 'RDR-0001')->firstOrFail();

        $response->assertRedirect(route('rfid-readers.show', $rfidReader));
        $response->assertSessionHas('plain_api_secret');

        $this->assertNotEmpty($rfidReader->api_key);
        $this->assertNotEmpty($rfidReader->api_secret_hash);
        $this->assertSame(session('plain_api_secret'), Crypt::decryptString($rfidReader->api_secret_hash));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'rfid_reader.created',
            'subject_type' => $rfidReader->getMorphClass(),
            'subject_id' => $rfidReader->id,
        ]);
    }

    public function test_rfid_reader_device_code_is_generated_server_side(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        RfidReader::factory()->create(['device_code' => 'RDR-0001']);
        CodeSetting::where('entity', 'rfid_readers')->update(['next_number' => 2]);

        $response = $this->post(route('rfid-readers.store'), [
            'device_name' => 'Gate 2',
            'device_code' => 'RDR-0001',
            'status' => 'offline',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('rfid_readers', [
            'device_name' => 'Gate 2',
            'device_code' => 'RDR-0002',
        ]);
    }

    public function test_encoder_registrar_can_regenerate_reader_credentials(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        $rfidReader = RfidReader::factory()->create();
        $originalApiKey = $rfidReader->api_key;
        $originalSecretHash = $rfidReader->api_secret_hash;

        $response = $this->post(route('rfid-readers.regenerate-credentials', $rfidReader));

        $response->assertRedirect(route('rfid-readers.show', $rfidReader));
        $response->assertSessionHas('plain_api_secret');

        $rfidReader->refresh();

        $this->assertNotSame($originalApiKey, $rfidReader->api_key);
        $this->assertNotSame($originalSecretHash, $rfidReader->api_secret_hash);
        $this->assertSame(session('plain_api_secret'), Crypt::decryptString($rfidReader->api_secret_hash));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'rfid_reader.credentials_regenerated',
            'subject_type' => $rfidReader->getMorphClass(),
            'subject_id' => $rfidReader->id,
        ]);
    }

    public function test_encoder_registrar_can_update_rfid_reader_but_not_delete_it(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        $rfidReader = RfidReader::factory()->create(['status' => 'offline']);

        $updateResponse = $this->put(route('rfid-readers.update', $rfidReader), [
            'device_name' => $rfidReader->device_name,
            'device_code' => $rfidReader->device_code,
            'status' => 'online',
        ]);

        $updateResponse->assertRedirect(route('rfid-readers.index'));
        $this->assertDatabaseHas('rfid_readers', ['id' => $rfidReader->id, 'status' => 'online']);

        $deleteResponse = $this->delete(route('rfid-readers.destroy', $rfidReader));

        $deleteResponse->assertForbidden();
        $this->assertDatabaseHas('rfid_readers', ['id' => $rfidReader->id]);
    }

    public function test_administrator_can_delete_rfid_reader(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('administrator');

        $rfidReader = RfidReader::factory()->create();

        $deleteResponse = $this->delete(route('rfid-readers.destroy', $rfidReader));

        $deleteResponse->assertRedirect(route('rfid-readers.index'));
        $this->assertDatabaseMissing('rfid_readers', ['id' => $rfidReader->id]);
    }

    public function test_security_officer_cannot_update_or_regenerate_credentials(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('security-officer');

        $rfidReader = RfidReader::factory()->create();

        $this->put(route('rfid-readers.update', $rfidReader), [
            'device_name' => $rfidReader->device_name,
            'device_code' => $rfidReader->device_code,
            'status' => 'online',
        ])->assertForbidden();

        $this->post(route('rfid-readers.regenerate-credentials', $rfidReader))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('rfid-readers.index'));

        $response->assertRedirect(route('login'));
    }
}
