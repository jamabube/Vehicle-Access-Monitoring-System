<?php

namespace App\Http\Controllers;

use App\Http\Requests\RfidAssignment\StoreRfidAssignmentRequest;
use App\Http\Requests\RfidAssignment\UpdateRfidAssignmentRequest;
use App\Models\AuditLog;
use App\Models\RfidAssignment;
use App\Models\RfidTag;
use App\Models\Vehicle;
use App\Models\VisitorVisit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class RfidAssignmentController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:rfid_assignments.view', only: ['index', 'show']),
            new Middleware('can:rfid_assignments.create', only: ['create', 'store']),
            new Middleware('can:rfid_assignments.update', only: ['edit', 'update', 'release']),
            new Middleware('can:rfid_assignments.delete', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of RFID assignments.
     */
    public function index(Request $request): View
    {
        $rfidAssignments = RfidAssignment::query()
            ->with(['rfidTag', 'vehicle', 'visitorVisit.visitor'])
            ->when($request->filled('status'), function ($query) use ($request) {
                if ($request->string('status') === 'active') {
                    $query->whereNull('released_at');
                } elseif ($request->string('status') === 'released') {
                    $query->whereNotNull('released_at');
                }
            })
            ->orderByDesc('assigned_at')
            ->paginate(15)
            ->withQueryString();

        return view('rfid-assignments.index', compact('rfidAssignments'));
    }

    /**
     * Show the form for creating a new RFID assignment.
     */
    public function create(): View
    {
        // Only offer credentials that are actually available. A tag with an
        // unreleased assignment is already on a vehicle or a visitor, and the
        // store validator rejects it anyway — listing it here only invites the
        // mistake. Matches the card picker in VisitorVisitController::create().
        $rfidTags = RfidTag::query()
            ->where('status', 'active')
            ->whereDoesntHave('assignments', fn ($query) => $query->whereNull('released_at'))
            ->orderBy('tag_code')
            ->get();
        // Same rule for the two targets: a vehicle or a visit that already holds
        // an unreleased credential cannot take a second one, so neither is
        // offered. Every option in this form is one that can actually be saved.
        $vehicles = Vehicle::query()
            ->with('employee')
            ->where('status', 'active')
            ->whereDoesntHave('rfidAssignments', fn ($query) => $query->whereNull('released_at'))
            ->orderBy('plate_number')
            ->get();

        $visitorVisits = VisitorVisit::query()
            ->with('visitor')
            ->where('status', 'active')
            ->whereDoesntHave('rfidAssignments', fn ($query) => $query->whereNull('released_at'))
            ->orderByDesc('valid_from')
            ->get();

        return view('rfid-assignments.create', [
            'rfidAssignment' => new RfidAssignment,
            'rfidTags' => $rfidTags,
            'vehicles' => $vehicles,
            'visitorVisits' => $visitorVisits,
        ]);
    }

    /**
     * Store a newly created RFID assignment.
     */
    public function store(StoreRfidAssignmentRequest $request): RedirectResponse
    {
        $rfidAssignment = RfidAssignment::create([
            ...$request->validated(),
            'assigned_by' => $request->user()->id,
        ]);

        AuditLog::record('rfid_assignment.created', $rfidAssignment, $request, $request->validated());

        return redirect()->route('rfid-assignments.index')->with('status', 'RFID assignment created successfully.');
    }

    /**
     * Display the specified RFID assignment.
     */
    public function show(RfidAssignment $rfidAssignment): View
    {
        $rfidAssignment->load(['rfidTag', 'vehicle', 'visitorVisit.visitor', 'assignedBy']);

        return view('rfid-assignments.show', compact('rfidAssignment'));
    }

    /**
     * Show the form for editing the specified RFID assignment.
     */
    public function edit(RfidAssignment $rfidAssignment): View
    {
        return view('rfid-assignments.edit', compact('rfidAssignment'));
    }

    /**
     * Update the specified RFID assignment (assigned_at only).
     */
    public function update(UpdateRfidAssignmentRequest $request, RfidAssignment $rfidAssignment): RedirectResponse
    {
        $rfidAssignment->update($request->validated());

        AuditLog::record('rfid_assignment.updated', $rfidAssignment, $request, $request->validated());

        return redirect()->route('rfid-assignments.index')->with('status', 'RFID assignment updated successfully.');
    }

    /**
     * Release the specified RFID assignment, freeing the tag for reuse.
     */
    public function release(Request $request, RfidAssignment $rfidAssignment): RedirectResponse
    {
        if ($rfidAssignment->released_at === null) {
            $rfidAssignment->update(['released_at' => now()]);

            AuditLog::record('rfid_assignment.released', $rfidAssignment, $request);
        }

        return redirect()->route('rfid-assignments.index')->with('status', 'RFID assignment released successfully.');
    }

    /**
     * Remove the specified RFID assignment.
     */
    public function destroy(Request $request, RfidAssignment $rfidAssignment): RedirectResponse
    {
        $rfidAssignment->delete();

        AuditLog::record('rfid_assignment.deleted', $rfidAssignment, $request);

        return redirect()->route('rfid-assignments.index')->with('status', 'RFID assignment deleted successfully.');
    }
}
