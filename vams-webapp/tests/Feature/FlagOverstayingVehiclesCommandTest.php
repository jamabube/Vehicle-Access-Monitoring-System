<?php

namespace Tests\Feature;

use App\Models\SystemLog;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlagOverstayingVehiclesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCoreSettings();
    }

    public function test_it_logs_a_warning_for_a_vehicle_that_did_not_exit_within_a_day(): void
    {
        $vehicle = Vehicle::factory()->create([
            'plate_number' => 'OVER001',
            'current_state' => 'inside',
            'last_seen_at' => now()->subDays(2),
        ]);

        $this->artisan('vehicles:flag-overstays')->assertSuccessful();

        $this->assertDatabaseHas('system_logs', [
            'level' => 'warning',
            'source' => 'overstay_monitor',
        ]);
        $log = SystemLog::where('source', 'overstay_monitor')->firstOrFail();
        $this->assertSame($vehicle->id, $log->context['vehicle_id']);
        $this->assertStringContainsString('OVER001', $log->message);
    }

    public function test_it_does_not_flag_a_recently_entered_or_outside_vehicle(): void
    {
        Vehicle::factory()->create(['current_state' => 'inside', 'last_seen_at' => now()->subHour()]);
        Vehicle::factory()->create(['current_state' => 'outside', 'last_seen_at' => now()->subDays(5)]);

        $this->artisan('vehicles:flag-overstays')->assertSuccessful();

        $this->assertDatabaseCount('system_logs', 0);
    }

    public function test_it_does_not_log_the_same_vehicle_twice_in_one_day(): void
    {
        Vehicle::factory()->create([
            'current_state' => 'inside',
            'last_seen_at' => now()->subDays(2),
        ]);

        $this->artisan('vehicles:flag-overstays')->assertSuccessful();
        $this->artisan('vehicles:flag-overstays')->assertSuccessful();

        $this->assertDatabaseCount('system_logs', 1);
    }

    public function test_the_hours_option_overrides_the_threshold(): void
    {
        Vehicle::factory()->create([
            'current_state' => 'inside',
            'last_seen_at' => now()->subHours(3),
        ]);

        // Default 24h would not flag a 3-hour stay; --hours=2 should.
        $this->artisan('vehicles:flag-overstays --hours=2')->assertSuccessful();

        $this->assertDatabaseCount('system_logs', 1);
    }
}
