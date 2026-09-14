<?php

namespace Tests\Feature;

use App\Models\RfidAssignment;
use App\Models\RfidTag;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Visitor;
use App\Models\VisitorVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfidAssignmentCrudTest extends TestCase
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

    public function test_the_tag_picker_only_offers_unassigned_tags(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        $free = RfidTag::factory()->create(['status' => 'active']);
        $taken = RfidTag::factory()->create(['status' => 'active']);
        $inactive = RfidTag::factory()->create(['status' => 'lost']);

        RfidAssignment::factory()->create([
            'rfid_tag_id' => $taken->id,
            'vehicle_id' => Vehicle::factory()->create()->id,
            'visitor_visit_id' => null,
            'assigned_at' => now()->subDay(),
            'released_at' => null,
        ]);

        $response = $this->get(route('rfid-assignments.create'));

        $response->assertOk();
        $response->assertSee($free->tag_code);
        // A tag already on a vehicle must not be offered again — the validator
        // would reject it anyway, so listing it only invites the mistake.
        $response->assertDontSee($taken->tag_code);
        $response->assertDontSee($inactive->tag_code);
    }

    public function test_the_target_pickers_only_offer_vehicles_and_visits_without_a_credential(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        $freeVehicle = Vehicle::factory()->create(['plate_number' => 'FREE001', 'status' => 'active']);
        $takenVehicle = Vehicle::factory()->create(['plate_number' => 'TAKEN01', 'status' => 'active']);

        RfidAssignment::factory()->create([
            'rfid_tag_id' => RfidTag::factory()->create(['status' => 'active'])->id,
            'vehicle_id' => $takenVehicle->id,
            'visitor_visit_id' => null,
            'assigned_at' => now()->subDay(),
            'released_at' => null,
        ]);

        $response = $this->get(route('rfid-assignments.create'));

        $response->assertOk();
        $response->assertSee('FREE001');
        // A vehicle that already carries a sticker cannot take a second one.
        $response->assertDontSee('TAKEN01');
    }

    public function test_a_released_tag_becomes_available_again(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        $tag = RfidTag::factory()->create(['status' => 'active']);
        RfidAssignment::factory()->create([
            'rfid_tag_id' => $tag->id,
            'vehicle_id' => Vehicle::factory()->create()->id,
            'visitor_visit_id' => null,
            'assigned_at' => now()->subDays(2),
            'released_at' => now()->subDay(),
        ]);

        $this->get(route('rfid-assignments.create'))
            ->assertOk()
            ->assertSee($tag->tag_code);
    }

    public function test_security_officer_cannot_view_rfid_assignment_index(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('security-officer');

        $this->get(route('rfid-assignments.index'))->assertForbidden();
    }

    public function test_encoder_registrar_can_create_assignment_for_vehicle(): void
    {
        $this->seedRolesAndPermissions();
        $user = $this->actingAsRole('encoder-registrar');

        $rfidTag = RfidTag::factory()->create(['credential_type' => 'sticker']);
        $vehicle = Vehicle::factory()->create();

        $response = $this->post(route('rfid-assignments.store'), [
            'rfid_tag_id' => $rfidTag->id,
            'vehicle_id' => $vehicle->id,
            'assigned_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect(route('rfid-assignments.index'));
        $this->assertDatabaseHas('rfid_assignments', [
            'rfid_tag_id' => $rfidTag->id,
            'vehicle_id' => $vehicle->id,
            'assigned_by' => $user->id,
        ]);

        $assignment = RfidAssignment::where('rfid_tag_id', $rfidTag->id)->firstOrFail();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'rfid_assignment.created',
            'subject_type' => $assignment->getMorphClass(),
            'subject_id' => $assignment->id,
        ]);
    }

    public function test_assignment_requires_exactly_one_target(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        $rfidTag = RfidTag::factory()->create();
        $vehicle = Vehicle::factory()->create();
        $visitor = Visitor::factory()->create();
        $visit = VisitorVisit::factory()->create([
            'visitor_id' => $visitor->id,
            'valid_from' => now(),
            'valid_until' => now()->addDay(),
        ]);

        // Neither target provided.
        $this->post(route('rfid-assignments.store'), [
            'rfid_tag_id' => $rfidTag->id,
            'assigned_at' => now()->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors(['vehicle_id', 'visitor_visit_id']);

        // Both targets provided.
        $this->post(route('rfid-assignments.store'), [
            'rfid_tag_id' => $rfidTag->id,
            'vehicle_id' => $vehicle->id,
            'visitor_visit_id' => $visit->id,
            'assigned_at' => now()->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors(['vehicle_id', 'visitor_visit_id']);
    }

    public function test_tag_with_active_assignment_cannot_be_reassigned(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        $rfidTag = RfidTag::factory()->create();
        RfidAssignment::factory()->create(['rfid_tag_id' => $rfidTag->id, 'released_at' => null]);

        $anotherVehicle = Vehicle::factory()->create();

        $response = $this->post(route('rfid-assignments.store'), [
            'rfid_tag_id' => $rfidTag->id,
            'vehicle_id' => $anotherVehicle->id,
            'assigned_at' => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertSessionHasErrors('rfid_tag_id');
    }

    public function test_encoder_registrar_can_release_assignment(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        $assignment = RfidAssignment::factory()->create(['released_at' => null]);

        $response = $this->post(route('rfid-assignments.release', $assignment));

        $response->assertRedirect(route('rfid-assignments.index'));
        $assignment->refresh();
        $this->assertNotNull($assignment->released_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'rfid_assignment.released',
            'subject_type' => $assignment->getMorphClass(),
            'subject_id' => $assignment->id,
        ]);
    }

    public function test_administrator_can_delete_assignment(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('administrator');

        $assignment = RfidAssignment::factory()->create();

        $response = $this->delete(route('rfid-assignments.destroy', $assignment));

        $response->assertRedirect(route('rfid-assignments.index'));
        $this->assertDatabaseMissing('rfid_assignments', ['id' => $assignment->id]);
    }

    public function test_encoder_registrar_cannot_delete_assignment(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        $assignment = RfidAssignment::factory()->create();

        $response = $this->delete(route('rfid-assignments.destroy', $assignment));

        $response->assertForbidden();
        $this->assertDatabaseHas('rfid_assignments', ['id' => $assignment->id]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('rfid-assignments.index'));

        $response->assertRedirect(route('login'));
    }
}
