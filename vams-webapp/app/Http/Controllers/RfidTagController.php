<?php

namespace App\Http\Controllers;

use App\Http\Requests\RfidTag\StoreRfidTagRequest;
use App\Http\Requests\RfidTag\UpdateRfidTagRequest;
use App\Models\AuditLog;
use App\Models\RfidDetection;
use App\Models\RfidTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class RfidTagController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:rfid_tags.view', only: ['index', 'show']),
            new Middleware('can:rfid_tags.create', only: ['create', 'store', 'recentScans']),
            new Middleware('can:rfid_tags.update', only: ['edit', 'update']),
            new Middleware('can:rfid_tags.delete', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of RFID tags.
     */
    public function index(Request $request): View
    {
        $rfidTags = RfidTag::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                // Match either identifier: the tag code is what staff read off
                // the physical sticker, the EPC is what the reader reports.
                $query->where(function ($query) use ($search) {
                    $query->where('tag_code', 'like', "%{$search}%")
                        ->orWhere('epc', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('credential_type'), fn ($query) => $query->where('credential_type', $request->string('credential_type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('rfid-tags.index', compact('rfidTags'));
    }

    /**
     * Show the form for creating a new RFID tag.
     */
    public function create(): View
    {
        return view('rfid-tags.create', ['rfidTag' => new RfidTag]);
    }

    /**
     * Tags the reader has seen in the last few minutes, for the "scan a tag"
     * helper on the create/edit form.
     *
     * Registering a tag used to mean reading its EPC off a terminal
     * (`php artisan rfid:listen --dry-run`) and typing it in by hand — fine for
     * a developer, not for the gate staff who actually enrol vehicles. This
     * endpoint lets the form show whatever was just waved at the reader.
     *
     * Note this does *not* talk to the reader: the listener already POSTs every
     * read to the ingestion API, unregistered EPCs included (they land in
     * rfid_detections and are denied as `unknown_credential`), so the recent
     * reads are already in the database. That keeps the "only the listener
     * talks to the reader" boundary intact — see NETWORK_CONFIG.md — and avoids
     * competing with the listener for the reader's single TCP socket.
     */
    public function recentScans(): JsonResponse
    {
        $windowSeconds = 120;

        $detections = RfidDetection::query()
            ->where('detected_at', '>=', now()->subSeconds($windowSeconds))
            ->orderByDesc('detected_at')
            ->limit(60)
            ->get(['epc', 'rssi', 'detected_at']);

        // One entry per tag, keeping its most recent read.
        $latestPerEpc = $detections->unique('epc')->values();

        $registered = RfidTag::whereIn('epc', $latestPerEpc->pluck('epc'))
            ->get(['epc', 'tag_code'])
            ->keyBy('epc');

        return response()->json([
            'window_seconds' => $windowSeconds,
            'scans' => $latestPerEpc->map(fn (RfidDetection $detection) => [
                'epc' => $detection->epc,
                'rssi' => $detection->rssi,
                'seen_at' => $detection->detected_at->format('g:i:s A'),
                'seconds_ago' => (int) $detection->detected_at->diffInSeconds(now()),
                'registered_as' => $registered->get($detection->epc)?->tag_code,
            ])->all(),
        ]);
    }

    /**
     * Store a newly created RFID tag.
     */
    public function store(StoreRfidTagRequest $request): RedirectResponse
    {
        $rfidTag = RfidTag::create($request->validated());

        AuditLog::record('rfid_tag.created', $rfidTag, $request, $request->validated());

        return redirect()->route('rfid-tags.index')->with('status', 'RFID tag created successfully.');
    }

    /**
     * Display the specified RFID tag.
     */
    public function show(RfidTag $rfidTag): View
    {
        $rfidTag->load(['assignments.vehicle', 'assignments.visitorVisit.visitor']);

        return view('rfid-tags.show', compact('rfidTag'));
    }

    /**
     * Show the form for editing the specified RFID tag.
     */
    public function edit(RfidTag $rfidTag): View
    {
        return view('rfid-tags.edit', compact('rfidTag'));
    }

    /**
     * Update the specified RFID tag.
     */
    public function update(UpdateRfidTagRequest $request, RfidTag $rfidTag): RedirectResponse
    {
        $rfidTag->update($request->validated());

        AuditLog::record('rfid_tag.updated', $rfidTag, $request, $request->validated());

        return redirect()->route('rfid-tags.index')->with('status', 'RFID tag updated successfully.');
    }

    /**
     * Remove the specified RFID tag.
     */
    public function destroy(Request $request, RfidTag $rfidTag): RedirectResponse
    {
        $rfidTag->delete();

        AuditLog::record('rfid_tag.deleted', $rfidTag, $request);

        return redirect()->route('rfid-tags.index')->with('status', 'RFID tag deleted successfully.');
    }
}
