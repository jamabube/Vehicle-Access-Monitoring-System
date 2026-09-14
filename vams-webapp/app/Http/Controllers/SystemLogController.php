<?php

namespace App\Http\Controllers;

use App\Models\SystemLog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Operational and security events — rejected device requests, replayed nonces,
 * reader problems (manuscript objective 9, troubleshooting and monitoring).
 *
 * This is where `VerifyRfidSignature` records every failed authentication
 * attempt against the ingestion API, so it doubles as the security-event view.
 */
class SystemLogController extends Controller implements HasMiddleware
{
    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:system_logs.view'),
        ];
    }

    public function index(Request $request): View
    {
        $logs = SystemLog::query()
            ->with('rfidReader')
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->when($request->filled('level'), fn ($query) => $query->where('level', $request->string('level')))
            ->when($request->filled('source'), fn ($query) => $query->where('source', $request->string('source')))
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = trim((string) $request->string('search'));

                $query->where(fn (Builder $query) => $query
                    ->where('message', 'like', "%{$search}%")
                    ->orWhere('source', 'like', "%{$search}%"));
            })
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        return view('system-logs.index', [
            'logs' => $logs,
            'levels' => SystemLog::query()->distinct()->orderBy('level')->pluck('level'),
            'sources' => SystemLog::query()->distinct()->orderBy('source')->pluck('source'),
        ]);
    }
}
