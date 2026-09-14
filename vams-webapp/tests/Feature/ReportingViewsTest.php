<?php

namespace Tests\Feature;

use App\Models\AccessLog;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\RfidDetection;
use App\Models\RfidTag;
use App\Models\Role;
use App\Models\SystemLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The searchable history behind the dashboard's live "today" view.
 *
 * The manuscript (§1.1) names retrieval of historical records as one of the
 * paper logbook's failings, so the filters are the feature — these tests are
 * mostly about a filter including the right rows and excluding the wrong ones.
 */
class ReportingViewsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRole(string $slug): User
    {
        $this->seedCoreSettings();

        $role = Role::where('slug', $slug)->firstOrFail();
        $user = User::factory()->create(['role_id' => $role->id, 'status' => 'active']);

        $this->actingAs($user);

        return $user;
    }

    private function logFor(Vehicle $vehicle, array $overrides = []): AccessLog
    {
        return AccessLog::factory()->create([
            'rfid_detection_id' => RfidDetection::factory()->create()->id,
            'rfid_tag_id' => RfidTag::factory()->create()->id,
            'vehicle_id' => $vehicle->id,
            'visitor_visit_id' => null,
            'decision' => 'authorized',
            'direction' => 'entry',
            'denial_reason' => null,
            'occurred_at' => now(),
            ...$overrides,
        ]);
    }

    public function test_access_logs_page_lists_history(): void
    {
        $this->actingAsRole('administrator');

        $employee = Employee::factory()->create(['first_name' => 'Jesusimo', 'last_name' => 'Dioses']);
        $vehicle = Vehicle::factory()->create(['plate_number' => 'BAF0812', 'employee_id' => $employee->id, 'owner_type' => 'employee']);
        $this->logFor($vehicle);

        $response = $this->get(route('access-logs.index'));

        $response->assertOk();
        $response->assertSee('Access Logs');
        $response->assertSee('BAF0812');
        $response->assertSee('Jesusimo Dioses');
    }

    public function test_it_filters_access_logs_by_date_range(): void
    {
        $this->actingAsRole('administrator');

        $old = Vehicle::factory()->create(['plate_number' => 'OLD0001']);
        $new = Vehicle::factory()->create(['plate_number' => 'NEW0001']);
        $this->logFor($old, ['occurred_at' => now()->subDays(30)]);
        $this->logFor($new, ['occurred_at' => now()]);

        $response = $this->get(route('access-logs.index', [
            'from' => now()->subDays(2)->format('Y-m-d'),
            'to' => now()->format('Y-m-d'),
        ]));

        $response->assertOk();
        $response->assertSee('NEW0001');
        $response->assertDontSee('OLD0001');
    }

    public function test_it_searches_access_logs_by_plate_number(): void
    {
        $this->actingAsRole('administrator');

        $this->logFor(Vehicle::factory()->create(['plate_number' => 'FIND123']));
        $this->logFor(Vehicle::factory()->create(['plate_number' => 'OTHER99']));

        $response = $this->get(route('access-logs.index', ['search' => 'FIND']));

        $response->assertOk();
        $response->assertSee('FIND123');
        $response->assertDontSee('OTHER99');
    }

    public function test_it_searches_access_logs_by_driver_name(): void
    {
        $this->actingAsRole('administrator');

        $employee = Employee::factory()->create(['first_name' => 'Jesusimo', 'last_name' => 'Dioses']);
        $this->logFor(Vehicle::factory()->create(['plate_number' => 'HIS0001', 'employee_id' => $employee->id, 'owner_type' => 'employee']));
        $this->logFor(Vehicle::factory()->create(['plate_number' => 'NOTHIS1']));

        $response = $this->get(route('access-logs.index', ['search' => 'Jesusimo']));

        $response->assertOk();
        $response->assertSee('HIS0001');
        $response->assertDontSee('NOTHIS1');
    }

    public function test_it_filters_access_logs_by_decision_and_direction(): void
    {
        $this->actingAsRole('administrator');

        $this->logFor(Vehicle::factory()->create(['plate_number' => 'ALLOW11']), ['decision' => 'authorized', 'direction' => 'entry']);
        $this->logFor(Vehicle::factory()->create(['plate_number' => 'DENY111']), ['decision' => 'denied', 'direction' => null, 'denial_reason' => 'unknown_credential']);

        $this->get(route('access-logs.index', ['decision' => 'denied']))
            ->assertOk()
            ->assertSee('DENY111')
            ->assertDontSee('ALLOW11');

        $this->get(route('access-logs.index', ['direction' => 'entry']))
            ->assertOk()
            ->assertSee('ALLOW11')
            ->assertDontSee('DENY111');
    }

    public function test_the_csv_export_respects_the_active_filters(): void
    {
        $this->actingAsRole('administrator');

        $this->logFor(Vehicle::factory()->create(['plate_number' => 'KEEP001']), ['decision' => 'denied', 'denial_reason' => 'unknown_credential']);
        $this->logFor(Vehicle::factory()->create(['plate_number' => 'DROP001']), ['decision' => 'authorized']);

        $response = $this->get(route('access-logs.export', ['decision' => 'denied']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Plate Number', $csv);
        $this->assertStringContainsString('KEEP001', $csv);
        $this->assertStringNotContainsString('DROP001', $csv);
    }

    public function test_audit_logs_page_lists_recorded_actions(): void
    {
        $user = $this->actingAsRole('administrator');

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'vehicle.created',
            'subject_type' => Vehicle::class,
            'subject_id' => 1,
            'ip_address' => '192.168.1.33',
        ]);

        $response = $this->get(route('audit-logs.index'));

        $response->assertOk();
        $response->assertSee('Audit Logs');
        $response->assertSee('vehicle.created');
        $response->assertSee('192.168.1.33');
    }

    public function test_system_logs_page_lists_events_and_filters_by_level(): void
    {
        $this->actingAsRole('administrator');

        SystemLog::create(['level' => 'warning', 'source' => 'rfid_ingestion_api', 'message' => 'Replayed nonce rejected.']);
        SystemLog::create(['level' => 'info', 'source' => 'rfid_ingestion_api', 'message' => 'Routine heartbeat.']);

        $this->get(route('system-logs.index'))
            ->assertOk()
            ->assertSee('Replayed nonce rejected.')
            ->assertSee('Routine heartbeat.');

        $this->get(route('system-logs.index', ['level' => 'warning']))
            ->assertOk()
            ->assertSee('Replayed nonce rejected.')
            ->assertDontSee('Routine heartbeat.');
    }

    public function test_system_logs_can_be_filtered_by_source(): void
    {
        $this->actingAsRole('administrator');

        SystemLog::create(['level' => 'warning', 'source' => 'rfid_ingestion_api', 'message' => 'Device request refused.']);
        SystemLog::create(['level' => 'warning', 'source' => 'authentication', 'message' => 'Login attempt refused.']);

        // The source picker must be rendered, not just supported by the query.
        $this->get(route('system-logs.index'))
            ->assertOk()
            ->assertSee('All sources')
            ->assertSee('rfid_ingestion_api')
            ->assertSee('authentication');

        $this->get(route('system-logs.index', ['source' => 'authentication']))
            ->assertOk()
            ->assertSee('Login attempt refused.')
            ->assertDontSee('Device request refused.');
    }

    public function test_security_officer_sees_access_logs_but_not_audit_or_system_logs(): void
    {
        $this->actingAsRole('security-officer');

        $this->get(route('access-logs.index'))->assertOk();
        $this->get(route('audit-logs.index'))->assertForbidden();
        $this->get(route('system-logs.index'))->assertForbidden();
    }

    public function test_encoder_registrar_cannot_view_the_reports(): void
    {
        $this->actingAsRole('encoder-registrar');

        $this->get(route('access-logs.index'))->assertForbidden();
        $this->get(route('audit-logs.index'))->assertForbidden();
        $this->get(route('system-logs.index'))->assertForbidden();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->seedCoreSettings();

        $this->get(route('access-logs.index'))->assertRedirect(route('login'));
        $this->get(route('audit-logs.index'))->assertRedirect(route('login'));
        $this->get(route('system-logs.index'))->assertRedirect(route('login'));
    }
}
