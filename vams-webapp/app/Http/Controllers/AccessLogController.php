<?php

namespace App\Http\Controllers;

use App\Models\AccessLog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Browsable history of gate activity — the searchable archive behind the
 * dashboard's live "today" view.
 *
 * This is the record the capstone manuscript (§1.1) says a paper logbook makes
 * hard to retrieve: "later retrieve records when historical access information
 * is needed". Filters are therefore the point of the page, not a nicety.
 */
class AccessLogController extends Controller implements HasMiddleware
{
    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:access_logs.view'),
        ];
    }

    /**
     * Display a filterable, paginated list of access logs.
     */
    public function index(Request $request): View
    {
        $logs = $this->filtered($request)
            ->with([
                'rfidTag',
                'vehicle' => fn ($query) => $query->withTrashed(),
                'vehicle.employee' => fn ($query) => $query->withTrashed(),
                'visitorVisit.visitor' => fn ($query) => $query->withTrashed(),
            ])
            ->orderByDesc('occurred_at')
            ->paginate(25)
            ->withQueryString();

        return view('access-logs.index', [
            'logs' => $logs,
            'summary' => $this->summary($request),
        ]);
    }

    /**
     * Stream the filtered results as CSV.
     *
     * Streamed rather than built in memory so a full year of gate activity
     * exports without exhausting PHP's memory limit; the same filters as the
     * page apply, so "export" always means "export what I am looking at".
     */
    public function export(Request $request): StreamedResponse
    {
        $filename = 'vams-access-logs-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($request) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Date', 'Time', 'Plate Number', 'Driver / Owner', 'Type', 'Direction', 'Decision', 'Denial Reason', 'Tag Code', 'EPC']);

            $this->filtered($request)
                ->with([
                    'rfidTag',
                    'vehicle' => fn ($query) => $query->withTrashed(),
                    'vehicle.employee' => fn ($query) => $query->withTrashed(),
                    'visitorVisit.visitor' => fn ($query) => $query->withTrashed(),
                ])
                ->orderByDesc('occurred_at')
                ->chunk(500, function ($chunk) use ($handle) {
                    foreach ($chunk as $log) {
                        fputcsv($handle, [
                            $log->occurred_at->format('Y-m-d'),
                            $log->occurred_at->format('H:i:s'),
                            $log->vehicle?->plate_number ?? '',
                            $log->vehicle?->employee?->fullName() ?? $log->visitorVisit?->visitor?->fullName() ?? '',
                            $log->visitorVisit ? 'Visitor' : ($log->vehicle ? 'Employee' : 'Unregistered'),
                            $log->direction ?? '',
                            $log->decision ?? '',
                            $log->denial_reason ?? '',
                            $log->rfidTag?->tag_code ?? '',
                            $log->rfidTag?->epc ?? '',
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Apply the page's filters to a fresh query.
     *
     * @return Builder<AccessLog>
     */
    private function filtered(Request $request): Builder
    {
        return AccessLog::query()
            ->when($request->filled('from'), fn ($query) => $query->whereDate('occurred_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('occurred_at', '<=', $request->date('to')))
            ->when($request->filled('decision'), fn ($query) => $query->where('decision', $request->string('decision')))
            ->when($request->filled('direction'), fn ($query) => $query->where('direction', $request->string('direction')))
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = trim((string) $request->string('search'));

                // One box, searched across the things a person actually knows:
                // a plate they saw, a name they were given, or a tag's EPC.
                $query->where(function (Builder $query) use ($search) {
                    $query->whereHas('vehicle', fn ($q) => $q->withTrashed()->where('plate_number', 'like', "%{$search}%"))
                        ->orWhereHas('vehicle.employee', fn ($q) => $q->withTrashed()
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%"))
                        ->orWhereHas('visitorVisit.visitor', fn ($q) => $q->withTrashed()
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%"))
                        ->orWhereHas('rfidTag', fn ($q) => $q->where('epc', 'like', "%{$search}%")
                            ->orWhere('tag_code', 'like', "%{$search}%"));
                });
            });
    }

    /**
     * Totals for the current filter, so the numbers describe the results being
     * looked at rather than the whole table.
     *
     * @return array<string, int>
     */
    private function summary(Request $request): array
    {
        $counts = $this->filtered($request)
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
