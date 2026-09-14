<?php

namespace App\Http\Controllers;

use App\Http\Requests\VisitorVisit\StoreVisitorVisitRequest;
use App\Http\Requests\VisitorVisit\UpdateVisitorVisitRequest;
use App\Models\AuditLog;
use App\Models\RfidAssignment;
use App\Models\RfidTag;
use App\Models\Vehicle;
use App\Models\Visitor;
use App\Models\VisitorVisit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VisitorVisitController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:visitor_visits.view', only: ['index', 'show']),
            new Middleware('can:visitor_visits.create', only: ['create', 'store']),
            new Middleware('can:visitor_visits.update', only: ['edit', 'update', 'checkOut']),
            new Middleware('can:visitor_visits.delete', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of visitor visits.
     */
    public function index(Request $request): View
    {
        $visitorVisits = VisitorVisit::query()
            ->with(['visitor', 'vehicle', 'rfidTag'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->whereHas('visitor', function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('valid_from')
            ->paginate(15)
            ->withQueryString();

        return view('visitor-visits.index', compact('visitorVisits'));
    }

    /**
     * Show the form for checking in a new visitor visit.
     */
    public function create(): View
    {
        $visitors = Visitor::query()->orderBy('last_name')->get();
        $vehicles = Vehicle::query()->orderBy('plate_number')->get();
        $rfidCards = RfidTag::query()
            ->where('credential_type', 'card')
            ->where('status', 'active')
            ->whereDoesntHave('assignments', fn ($query) => $query->whereNull('released_at'))
            ->orderBy('epc')
            ->get();

        return view('visitor-visits.create', [
            'visitorVisit' => new VisitorVisit,
            'visitors' => $visitors,
            'vehicles' => $vehicles,
            'rfidCards' => $rfidCards,
        ]);
    }

    /**
     * Check in a new visitor visit, optionally assigning an RFID card.
     */
    public function store(StoreVisitorVisitRequest $request): RedirectResponse
    {
        $visitorVisit = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $rfidTagId = $data['rfid_tag_id'] ?? null;
            unset($data['rfid_tag_id']);

            $visitorVisit = VisitorVisit::create([
                ...$data,
                'rfid_tag_id' => $rfidTagId,
                'status' => 'active',
                'registered_by' => $request->user()->id,
            ]);

            if ($rfidTagId !== null) {
                RfidAssignment::create([
                    'rfid_tag_id' => $rfidTagId,
                    'visitor_visit_id' => $visitorVisit->id,
                    'assigned_at' => now(),
                    'assigned_by' => $request->user()->id,
                ]);
            }

            return $visitorVisit;
        });

        AuditLog::record('visitor_visit.checked_in', $visitorVisit, $request, $request->validated());

        return redirect()->route('visitor-visits.index')->with('status', 'Visitor checked in successfully.');
    }

    /**
     * Display the specified visitor visit.
     */
    public function show(VisitorVisit $visitorVisit): View
    {
        $visitorVisit->load(['visitor', 'vehicle', 'rfidTag', 'registeredBy', 'rfidAssignments.rfidTag']);

        return view('visitor-visits.show', compact('visitorVisit'));
    }

    /**
     * Show the form for editing the specified visitor visit.
     */
    public function edit(VisitorVisit $visitorVisit): View
    {
        $vehicles = Vehicle::query()->orderBy('plate_number')->get();

        return view('visitor-visits.edit', compact('visitorVisit', 'vehicles'));
    }

    /**
     * Update the specified visitor visit (correctable details only).
     */
    public function update(UpdateVisitorVisitRequest $request, VisitorVisit $visitorVisit): RedirectResponse
    {
        $visitorVisit->update($request->validated());

        AuditLog::record('visitor_visit.updated', $visitorVisit, $request, $request->validated());

        return redirect()->route('visitor-visits.index')->with('status', 'Visitor visit updated successfully.');
    }

    /**
     * Check out the specified visitor visit, releasing any assigned RFID card.
     */
    public function checkOut(Request $request, VisitorVisit $visitorVisit): RedirectResponse
    {
        if ($visitorVisit->status === 'active') {
            DB::transaction(function () use ($visitorVisit) {
                $visitorVisit->update([
                    'status' => 'checked_out',
                    'checked_out_at' => now(),
                ]);

                RfidAssignment::query()
                    ->where('visitor_visit_id', $visitorVisit->id)
                    ->whereNull('released_at')
                    ->update(['released_at' => now()]);
            });

            AuditLog::record('visitor_visit.checked_out', $visitorVisit, $request);
        }

        return redirect()->route('visitor-visits.index')->with('status', 'Visitor checked out successfully.');
    }

    /**
     * Remove the specified visitor visit.
     */
    public function destroy(Request $request, VisitorVisit $visitorVisit): RedirectResponse
    {
        $visitorVisit->delete();

        AuditLog::record('visitor_visit.deleted', $visitorVisit, $request);

        return redirect()->route('visitor-visits.index')->with('status', 'Visitor visit deleted successfully.');
    }
}
