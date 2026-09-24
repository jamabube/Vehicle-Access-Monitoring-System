<?php

namespace App\Console\Commands;

use App\Models\SystemLog;
use App\Models\Vehicle;
use Illuminate\Console\Command;

/**
 * Records a system-log warning for every vehicle that entered but has not
 * exited within the configured window (rfid.overstay_alert_hours, one day by
 * default). The dashboard already warns about these in real time; this command
 * writes the warning into the persistent system log so it is captured even when
 * nobody is watching the screen.
 *
 * It is safe to run repeatedly: each vehicle is logged at most once per day, so
 * scheduling it hourly (or running it by hand) will not flood the log.
 *
 *   php artisan vehicles:flag-overstays
 */
class FlagOverstayingVehiclesCommand extends Command
{
    protected $signature = 'vehicles:flag-overstays {--hours= : Override the overstay threshold in hours}';

    protected $description = 'Warn about vehicles that entered but did not exit within the allowed time';

    public function handle(): int
    {
        $hours = $this->option('hours') !== null
            ? (int) $this->option('hours')
            : (int) config('rfid.overstay_alert_hours', 24);

        $vehicles = Vehicle::with('employee')->overstaying($hours)->get();

        if ($vehicles->isEmpty()) {
            $this->info('No overstaying vehicles.');

            return self::SUCCESS;
        }

        $logged = 0;

        foreach ($vehicles as $vehicle) {
            // Log each vehicle at most once per calendar day, so an hourly
            // schedule does not write the same warning over and over.
            $alreadyLoggedToday = SystemLog::where('source', 'overstay_monitor')
                ->where('created_at', '>=', now()->startOfDay())
                ->whereJsonContains('context->vehicle_id', $vehicle->id)
                ->exists();

            if ($alreadyLoggedToday) {
                continue;
            }

            SystemLog::create([
                'level' => 'warning',
                'source' => 'overstay_monitor',
                'message' => sprintf(
                    'Vehicle %s has been inside for over %d hour(s) without exiting.',
                    $vehicle->plate_number,
                    $hours,
                ),
                'context' => [
                    'vehicle_id' => $vehicle->id,
                    'plate_number' => $vehicle->plate_number,
                    'owner' => $vehicle->employee?->fullName(),
                    'entered_at' => $vehicle->last_seen_at?->toIso8601String(),
                    'threshold_hours' => $hours,
                ],
            ]);

            $logged++;
            $this->warn(sprintf('Flagged %s (inside since %s).', $vehicle->plate_number, $vehicle->last_seen_at?->diffForHumans() ?? 'unknown'));
        }

        $this->info(sprintf('%d vehicle(s) overstaying; %d newly logged.', $vehicles->count(), $logged));

        return self::SUCCESS;
    }
}
