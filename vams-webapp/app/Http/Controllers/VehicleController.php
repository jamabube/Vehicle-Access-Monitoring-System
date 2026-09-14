<?php

namespace App\Http\Controllers;

use App\Http\Requests\Vehicle\StoreVehicleRequest;
use App\Http\Requests\Vehicle\UpdateVehicleRequest;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class VehicleController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:vehicles.view', only: ['index', 'show']),
            new Middleware('can:vehicles.create', only: ['create', 'store']),
            new Middleware('can:vehicles.update', only: ['edit', 'update']),
            new Middleware('can:vehicles.update_state', only: ['updateState']),
            new Middleware('can:vehicles.delete', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of vehicles.
     */
    public function index(Request $request): View
    {
        $vehicles = Vehicle::query()
            ->with('employee')
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($q) use ($search) {
                    $q->where('plate_number', 'like', "%{$search}%")
                        ->orWhere('make', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('current_state'), fn ($query) => $query->where('current_state', $request->string('current_state')))
            ->orderBy('plate_number')
            ->paginate(15)
            ->withQueryString();

        return view('vehicles.index', compact('vehicles'));
    }

    /**
     * Show the form for creating a new vehicle.
     */
    public function create(): View
    {
        $employees = Employee::query()->orderBy('last_name')->get();

        return view('vehicles.create', ['vehicle' => new Vehicle, 'employees' => $employees]);
    }

    /**
     * Store a newly created vehicle.
     */
    public function store(StoreVehicleRequest $request): RedirectResponse
    {
        $vehicle = Vehicle::create($request->validated());

        AuditLog::record('vehicle.created', $vehicle, $request, $request->validated());

        return redirect()->route('vehicles.index')->with('status', 'Vehicle created successfully.');
    }

    /**
     * Display the specified vehicle.
     */
    public function show(Vehicle $vehicle): View
    {
        $vehicle->load('employee');

        return view('vehicles.show', compact('vehicle'));
    }

    /**
     * Show the form for editing the specified vehicle.
     */
    public function edit(Vehicle $vehicle): View
    {
        $employees = Employee::query()->orderBy('last_name')->get();

        return view('vehicles.edit', compact('vehicle', 'employees'));
    }

    /**
     * Update the specified vehicle.
     */
    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $vehicle->update($request->validated());

        AuditLog::record('vehicle.updated', $vehicle, $request, $request->validated());

        return redirect()->route('vehicles.index')->with('status', 'Vehicle updated successfully.');
    }

    /**
     * Manually override the RFID-driven inside/outside state.
     *
     * Used to correct missed-exit-scan drift (a vehicle stuck `inside` because
     * its exit read was never captured). Gated separately from ordinary
     * metadata updates so encoder/registrar staff cannot rewrite gate history
     * (FINDING #11).
     */
    public function updateState(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $validated = $request->validate([
            'current_state' => ['required', 'in:inside,outside'],
        ]);

        $from = $vehicle->current_state;

        $vehicle->forceFill([
            'current_state' => $validated['current_state'],
        ])->save();

        AuditLog::record('vehicle.state_updated', $vehicle, $request, [
            'from' => $from,
            'to' => $validated['current_state'],
        ]);

        return redirect()->route('vehicles.show', $vehicle)
            ->with('status', 'Vehicle state updated successfully.');
    }

    /**
     * Remove the specified vehicle (soft delete).
     */
    public function destroy(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $vehicle->delete();

        AuditLog::record('vehicle.deleted', $vehicle, $request);

        return redirect()->route('vehicles.index')->with('status', 'Vehicle deleted successfully.');
    }
}
