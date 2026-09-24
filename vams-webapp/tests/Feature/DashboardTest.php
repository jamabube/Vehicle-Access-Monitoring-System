<?php

namespace Tests\Feature;

use App\Models\AccessLog;
use App\Models\Employee;
use App\Models\RfidDetection;
use App\Models\RfidTag;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Visitor;
use App\Models\VisitorVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard is the digital logbook (manuscript §1.1), so these tests are
 * mostly about one thing: the vehicle in/out record must be the page's primary
 * content, present and correct the moment it is opened.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function seedRolesAndPermissions(): void
    {
        // Also seeds code_settings, which the model observers need to generate
        // employee/vehicle/tag codes when factories create records.
        $this->seedCoreSettings();
    }

    protected function createUserWithRole(string $roleSlug): User
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();

        return User::factory()->create([
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }

    /**
     * An authorized entry for a named employee's vehicle.
     */
    private function logEntry(array $overrides = []): AccessLog
    {
        return AccessLog::factory()->create([
            'rfid_detection_id' => RfidDetection::factory()->create()->id,
            'rfid_tag_id' => RfidTag::factory()->create()->id,
            'decision' => 'authorized',
            'direction' => 'entry',
            'denial_reason' => null,
            'visitor_visit_id' => null,
            'occurred_at' => now(),
            ...$overrides,
        ]);
    }

    public function test_administrator_can_view_dashboard_with_statistics(): void
    {
        $this->seedRolesAndPermissions();
        $admin = $this->createUserWithRole('administrator');

        Employee::factory()->count(3)->create();
        Vehicle::factory()->count(5)->create(['current_state' => 'outside']);
        Vehicle::factory()->count(2)->create(['current_state' => 'inside']);
        Visitor::factory()->count(4)->create();
        VisitorVisit::factory()->count(2)->create(['status' => 'active']);
        RfidTag::factory()->count(6)->create();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Gate Activity');
        $response->assertSee('Entries today');
        $response->assertSee('Currently inside');
    }

    public function test_the_gate_log_shows_a_vehicle_entry_with_plate_and_driver(): void
    {
        $this->seedRolesAndPermissions();
        $admin = $this->createUserWithRole('administrator');

        $employee = Employee::factory()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz']);
        $vehicle = Vehicle::factory()->create([
            'plate_number' => 'ABC1234',
            'employee_id' => $employee->id,
            'owner_type' => 'employee',
        ]);

        $this->logEntry(['vehicle_id' => $vehicle->id]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('ABC1234');
        $response->assertSee('Juan Dela Cruz');
        $response->assertSee('IN');
        $response->assertSee('Allowed');
    }

    public function test_the_gate_log_shows_a_denied_attempt_with_its_reason(): void
    {
        $this->seedRolesAndPermissions();
        $admin = $this->createUserWithRole('administrator');

        $this->logEntry([
            'vehicle_id' => null,
            'decision' => 'denied',
            'direction' => null,
            'denial_reason' => 'unknown_credential',
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Denied');
        $response->assertSee('Unknown credential');
    }

    public function test_it_lists_the_vehicles_currently_inside(): void
    {
        $this->seedRolesAndPermissions();
        $admin = $this->createUserWithRole('administrator');

        Vehicle::factory()->create(['plate_number' => 'INSIDE1', 'current_state' => 'inside', 'last_seen_at' => now()]);
        Vehicle::factory()->create(['plate_number' => 'GONE999', 'current_state' => 'outside']);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Inside Now');
        $response->assertSee('INSIDE1');
        $response->assertDontSee('GONE999');
    }

    public function test_it_falls_back_to_recent_activity_when_today_is_empty(): void
    {
        $this->seedRolesAndPermissions();
        $admin = $this->createUserWithRole('administrator');

        $vehicle = Vehicle::factory()->create(['plate_number' => 'OLD1234']);
        $this->logEntry(['vehicle_id' => $vehicle->id, 'occurred_at' => now()->subDays(3)]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('No activity today yet');
        $response->assertSee('OLD1234');
    }

    public function test_the_log_keeps_the_plate_of_a_vehicle_that_was_later_deleted(): void
    {
        $this->seedRolesAndPermissions();
        $admin = $this->createUserWithRole('administrator');

        $employee = Employee::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
        $vehicle = Vehicle::factory()->create([
            'plate_number' => 'OLDCAR1',
            'employee_id' => $employee->id,
            'owner_type' => 'employee',
        ]);

        $this->logEntry(['vehicle_id' => $vehicle->id]);

        // The vehicle (and its owner) are removed from the active records after
        // the fact; the historical gate entry must not lose their identity.
        $vehicle->delete();
        $employee->delete();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('OLDCAR1');
        $response->assertSee('Maria Santos');
        $response->assertSee('record since removed');
    }

    public function test_dashboard_shows_empty_state_when_no_access_logs(): void
    {
        $this->seedRolesAndPermissions();
        $admin = $this->createUserWithRole('administrator');

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('No vehicles recorded yet');
    }

    public function test_the_refresh_endpoint_returns_the_log_and_counters(): void
    {
        $this->seedRolesAndPermissions();
        $admin = $this->createUserWithRole('administrator');

        $vehicle = Vehicle::factory()->create(['plate_number' => 'LIVE123', 'current_state' => 'inside']);
        $this->logEntry(['vehicle_id' => $vehicle->id]);

        $response = $this->actingAs($admin)->getJson(route('dashboard.gate-activity'));

        $response->assertOk();
        $response->assertJsonStructure([
            'today' => ['total', 'authorized', 'denied', 'entries', 'exits'],
            'inside_count',
            'activity_html',
            'inside_html',
        ]);
        $response->assertJsonPath('today.entries', 1);
        $response->assertJsonPath('today.authorized', 1);
        $response->assertJsonPath('inside_count', 1);
        $this->assertStringContainsString('LIVE123', $response->json('activity_html'));
        $this->assertStringContainsString('LIVE123', $response->json('inside_html'));
    }

    public function test_it_warns_when_a_vehicle_did_not_exit_within_a_day(): void
    {
        $this->seedRolesAndPermissions();
        $admin = $this->createUserWithRole('administrator');

        // Entered two days ago and still inside — an overstay.
        Vehicle::factory()->create([
            'plate_number' => 'STUCK01',
            'current_state' => 'inside',
            'last_seen_at' => now()->subDays(2),
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('still inside');
        $response->assertSee('not yet exited');
    }

    public function test_a_recently_entered_vehicle_is_not_flagged_as_overstaying(): void
    {
        $this->seedRolesAndPermissions();
        $admin = $this->createUserWithRole('administrator');

        Vehicle::factory()->create([
            'plate_number' => 'FRESH01',
            'current_state' => 'inside',
            'last_seen_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('not yet exited');
    }

    public function test_the_refresh_endpoint_reports_the_overstay_count(): void
    {
        $this->seedRolesAndPermissions();
        $admin = $this->createUserWithRole('administrator');

        Vehicle::factory()->create(['current_state' => 'inside', 'last_seen_at' => now()->subDays(3)]);
        Vehicle::factory()->create(['current_state' => 'inside', 'last_seen_at' => now()]);

        $response = $this->actingAs($admin)->getJson(route('dashboard.gate-activity'));

        $response->assertOk();
        $response->assertJsonPath('overstay_count', 1);
        $response->assertJsonPath('inside_count', 2);
    }

    public function test_security_officer_can_watch_the_gate_log(): void
    {
        $this->seedRolesAndPermissions();
        $officer = $this->createUserWithRole('security-officer');

        $vehicle = Vehicle::factory()->create(['plate_number' => 'SEC0001']);
        $this->logEntry(['vehicle_id' => $vehicle->id]);

        $this->actingAs($officer)->get(route('dashboard'))->assertOk()->assertSee('SEC0001');
        $this->actingAs($officer)->getJson(route('dashboard.gate-activity'))->assertOk();
    }

    public function test_guest_cannot_view_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('dashboard.gate-activity'))->assertRedirect(route('login'));
    }
}
