<?php

namespace App\Http\Controllers;

use App\Models\AccessLog;
use App\Models\Employee;
use App\Models\RfidTag;
use App\Models\Vehicle;
use App\Models\Visitor;
use App\Models\VisitorVisit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;

/**
 * The gate monitoring dashboard.
 *
 * This is the digital replacement for the paper logbook described in the
 * capstone manuscript §1.1, so the vehicle in/out record is the primary
 * content of the page — not a summary tucked under management counters. The
 * record management tiles are secondary and sit below it.
 */
class DashboardController extends Controller implements HasMiddleware
{
    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:dashboard.view'),
        ];
    }
    /** How many of today's gate events to render. */
    private const ACTIVITY_LIMIT = 100;

    /** Fallback size when today has no activity yet. */
    private const FALLBACK_LIMIT = 25;

    /**
     * Display the gate activity log plus supporting statistics.
     */
    public function index(): View
    {
        [$gateActivity, $showingToday] = $this->gateActivityLog();
        $vehiclesInside = $this->vehiclesInside();

        return view('dashboard', [
            'gateActivity' => $gateActivity,
            'showingToday' => $showingToday,
            'vehiclesInside' => $vehiclesInside,
            'overstayCount' => $this->overstayCount($vehiclesInside),
            'overstayHours' => (int) config('rfid.overstay_alert_hours', 24),
            'todayStats' => $this->todayStats(),
            'stats' => [
                'employees' => Employee::count(),
                'vehicles' => Vehicle::count(),
                'vehicles_inside' => Vehicle::where('current_state', 'inside')->count(),
                'active_visits' => VisitorVisit::where('status', 'active')->count(),
                'visitors' => Visitor::count(),
                'rfid_tags' => RfidTag::count(),
                'rfid_tags_assigned' => RfidTag::whereHas('currentAssignment')->count(),
            ],
        ]);
    }

    /**
     * Refreshed gate activity, polled by the dashboard so a guard watching the
     * screen sees vehicles appear without reloading the page.
     */
    public function gateActivity(): JsonResponse
    {
        [$gateActivity, $showingToday] = $this->gateActivityLog();
        $vehiclesInside = $this->vehiclesInside();
        $overstayCount = $this->overstayCount($vehiclesInside);

        return response()->json([
            'today' => $this->todayStats(),
            'inside_count' => $vehiclesInside->count(),
            'overstay_count' => $overstayCount,
            'overstay_hours' => (int) config('rfid.overstay_alert_hours', 24),
            'activity_html' => view('dashboard._gate-activity', [
                'gateActivity' => $gateActivity,
                'showingToday' => $showingToday,
            ])->render(),
            'inside_html' => view('dashboard._vehicles-inside', [
                'vehiclesInside' => $vehiclesInside,
            ])->render(),
        ]);
    }

    /**
     * How many of the currently-inside vehicles have overstayed (entered but
     * did not exit within the configured window). Computed from the loaded
     * collection so it needs no extra query.
     *
     * @param  Collection<int, Vehicle>  $vehiclesInside
     */
    private function overstayCount(Collection $vehiclesInside): int
    {
        return $vehiclesInside->filter->hasOverstayed()->count();
    }

    /**
     * Today's gate events, newest first. Falls back to the most recent activity
     * of any date when today is still empty, so the page never opens blank on a
     * quiet morning — the caller is told which of the two it received.
     *
     * @return array{0: Collection<int, AccessLog>, 1: bool}
     */
    private function gateActivityLog(): array
    {
        // Load soft-deleted vehicles/employees/visitors too. A gate log is a
        // historical record: if a vehicle is later removed from the fleet, the
        // entries it already made must keep showing its plate rather than
        // silently degrading to "unknown" (manuscript §1.2.2 objective 9,
        // accountability and traceability).
        $relations = [
            'rfidTag',
            'vehicle' => fn ($query) => $query->withTrashed(),
            'vehicle.employee' => fn ($query) => $query->withTrashed(),
            'visitorVisit.visitor' => fn ($query) => $query->withTrashed(),
        ];

        $today = AccessLog::with($relations)
            ->where('occurred_at', '>=', now()->startOfDay())
            ->orderByDesc('occurred_at')
            ->limit(self::ACTIVITY_LIMIT)
            ->get();

        if ($today->isNotEmpty()) {
            return [$today, true];
        }

        $recent = AccessLog::with($relations)
            ->orderByDesc('occurred_at')
            ->limit(self::FALLBACK_LIMIT)
            ->get();

        return [$recent, false];
    }

    /**
     * Vehicles the system currently believes are on the premises — the
     * "who is still inside" question a guard is actually asked.
     *
     * @return Collection<int, Vehicle>
     */
    private function vehiclesInside(): Collection
    {
        return Vehicle::with('employee')
            ->where('current_state', 'inside')
            ->orderByDesc('last_seen_at')
            ->get();
    }

    /**
     * @return array<string, int>
     */
    private function todayStats(): array
    {
        $todayStart = now()->startOfDay();

        $counts = AccessLog::where('occurred_at', '>=', $todayStart)
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when decision = 'authorized' then 1 else 0 end) as authorized")
            ->selectRaw("sum(case when decision = 'denied' then 1 else 0 end) as denied")
            ->selectRaw("sum(case when direction = 'entry' then 1 else 0 end) as entries")
            ->selectRaw("sum(case when direction = 'exit' then 1 else 0 end) as exits")
            ->first();

        return [
            'total' => (int) $counts->total,
            'authorized' => (int) $counts->authorized,
            'denied' => (int) $counts->denied,
            'entries' => (int) $counts->entries,
            'exits' => (int) $counts->exits,
        ];
    }
}
