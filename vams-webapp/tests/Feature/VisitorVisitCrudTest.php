<?php

namespace Tests\Feature;

use App\Models\RfidAssignment;
use App\Models\RfidTag;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Visitor;
use App\Models\VisitorVisit;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitorVisitCrudTest extends TestCase
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

    public function test_security_officer_can_view_but_not_create_visit(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('security-officer');

        $this->get(route('visitor-visits.index'))->assertOk();
        $this->get(route('visitor-visits.create'))->assertForbidden();
    }

    public function test_encoder_registrar_can_check_in_visitor_without_card(): void
    {
        $this->seedRolesAndPermissions();
        $user = $this->actingAsRole('encoder-registrar');

        $visitor = Visitor::factory()->create();

        $response = $this->post(route('visitor-visits.store'), [
            'visitor_id' => $visitor->id,
            'purpose' => 'Meeting',
            'host_name' => 'HR Department',
            'valid_from' => now()->format('Y-m-d H:i:s'),
            'valid_until' => now()->addHours(4)->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect(route('visitor-visits.index'));
        $this->assertDatabaseHas('visitor_visits', [
            'visitor_id' => $visitor->id,
            'status' => 'active',
            'registered_by' => $user->id,
        ]);

        $visit = VisitorVisit::where('visitor_id', $visitor->id)->firstOrFail();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'visitor_visit.checked_in',
            'subject_type' => $visit->getMorphClass(),
            'subject_id' => $visit->id,
        ]);
    }

    public function test_encoder_registrar_can_check_in_visitor_with_rfid_card(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        $visitor = Visitor::factory()->create();
        $card = RfidTag::factory()->create(['credential_type' => 'card', 'status' => 'active']);

        $response = $this->post(route('visitor-visits.store'), [
            'visitor_id' => $visitor->id,
            'rfid_tag_id' => $card->id,
            'valid_from' => now()->format('Y-m-d H:i:s'),
            'valid_until' => now()->addHours(4)->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect(route('visitor-visits.index'));

        $visit = VisitorVisit::where('visitor_id', $visitor->id)->firstOrFail();
        $this->assertSame($card->id, $visit->rfid_tag_id);

        $this->assertDatabaseHas('rfid_assignments', [
            'rfid_tag_id' => $card->id,
            'visitor_visit_id' => $visit->id,
            'released_at' => null,
        ]);
    }

    public function test_check_in_rejects_card_with_active_assignment(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        $visitor = Visitor::factory()->create();
        $card = RfidTag::factory()->create(['credential_type' => 'card']);
        RfidAssignment::factory()->create(['rfid_tag_id' => $card->id, 'released_at' => null]);

        $response = $this->post(route('visitor-visits.store'), [
            'visitor_id' => $visitor->id,
            'rfid_tag_id' => $card->id,
            'valid_from' => now()->format('Y-m-d H:i:s'),
            'valid_until' => now()->addHours(4)->format('Y-m-d H:i:s'),
        ]);

        $response->assertSessionHasErrors('rfid_tag_id');
        $this->assertDatabaseMissing('visitor_visits', ['visitor_id' => $visitor->id]);
    }

    public function test_check_in_requires_valid_until_after_valid_from(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        $visitor = Visitor::factory()->create();

        $response = $this->post(route('visitor-visits.store'), [
            'visitor_id' => $visitor->id,
            'valid_from' => now()->format('Y-m-d H:i:s'),
            'valid_until' => now()->subHour()->format('Y-m-d H:i:s'),
        ]);

        $response->assertSessionHasErrors('valid_until');
    }

    public function test_encoder_registrar_can_check_out_visit_and_release_card(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        $card = RfidTag::factory()->create(['credential_type' => 'card']);
        $visit = VisitorVisit::factory()->create(['rfid_tag_id' => $card->id, 'status' => 'active']);
        $assignment = RfidAssignment::factory()->create([
            'rfid_tag_id' => $card->id,
            'vehicle_id' => null,
            'visitor_visit_id' => $visit->id,
            'released_at' => null,
        ]);

        $response = $this->post(route('visitor-visits.check-out', $visit));

        $response->assertRedirect(route('visitor-visits.index'));

        $visit->refresh();
        $this->assertSame('checked_out', $visit->status);
        $this->assertNotNull($visit->checked_out_at);

        $assignment->refresh();
        $this->assertNotNull($assignment->released_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'visitor_visit.checked_out',
            'subject_type' => $visit->getMorphClass(),
            'subject_id' => $visit->id,
        ]);
    }

    public function test_encoder_registrar_can_update_correctable_visit_details(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        $visit = VisitorVisit::factory()->create(['purpose' => 'Delivery']);
        $vehicle = Vehicle::factory()->create();

        $response = $this->put(route('visitor-visits.update', $visit), [
            'vehicle_id' => $vehicle->id,
            'purpose' => 'Follow-up meeting',
            'host_name' => 'Finance',
            'valid_from' => $visit->valid_from->format('Y-m-d H:i:s'),
            'valid_until' => $visit->valid_until->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect(route('visitor-visits.index'));
        $this->assertDatabaseHas('visitor_visits', [
            'id' => $visit->id,
            'vehicle_id' => $vehicle->id,
            'purpose' => 'Follow-up meeting',
        ]);
    }

    public function test_administrator_can_delete_visit(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('administrator');

        $visit = VisitorVisit::factory()->create();

        $response = $this->delete(route('visitor-visits.destroy', $visit));

        $response->assertRedirect(route('visitor-visits.index'));
        $this->assertDatabaseMissing('visitor_visits', ['id' => $visit->id]);
    }

    public function test_encoder_registrar_cannot_delete_visit(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('encoder-registrar');

        $visit = VisitorVisit::factory()->create();

        $response = $this->delete(route('visitor-visits.destroy', $visit));

        $response->assertForbidden();
        $this->assertDatabaseHas('visitor_visits', ['id' => $visit->id]);
    }

    public function test_security_officer_cannot_check_in_or_delete(): void
    {
        $this->seedRolesAndPermissions();
        $this->actingAsRole('security-officer');

        $visitor = Visitor::factory()->create();
        $visit = VisitorVisit::factory()->create();

        $this->post(route('visitor-visits.store'), [
            'visitor_id' => $visitor->id,
            'valid_from' => now()->format('Y-m-d H:i:s'),
            'valid_until' => now()->addHour()->format('Y-m-d H:i:s'),
        ])->assertForbidden();

        $this->delete(route('visitor-visits.destroy', $visit))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('visitor-visits.index'));

        $response->assertRedirect(route('login'));
    }
}
