<?php

namespace App\Http\Controllers;

use App\Http\Requests\Visitor\StoreVisitorRequest;
use App\Http\Requests\Visitor\UpdateVisitorRequest;
use App\Models\AuditLog;
use App\Models\Visitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class VisitorController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:visitors.view', only: ['index', 'show']),
            new Middleware('can:visitors.create', only: ['create', 'store']),
            new Middleware('can:visitors.update', only: ['edit', 'update']),
            new Middleware('can:visitors.delete', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of visitors.
     */
    public function index(Request $request): View
    {
        $visitors = Visitor::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('contact_number', 'like', "%{$search}%")
                        ->orWhere('valid_id_number', 'like', "%{$search}%");
                });
            })
            ->orderBy('last_name')
            ->paginate(15)
            ->withQueryString();

        return view('visitors.index', compact('visitors'));
    }

    /**
     * Show the form for creating a new visitor.
     */
    public function create(): View
    {
        return view('visitors.create', ['visitor' => new Visitor]);
    }

    /**
     * Store a newly created visitor.
     */
    public function store(StoreVisitorRequest $request): RedirectResponse
    {
        $visitor = Visitor::create($request->validated());

        AuditLog::record('visitor.created', $visitor, $request, $request->validated());

        return redirect()->route('visitors.index')->with('status', 'Visitor created successfully.');
    }

    /**
     * Display the specified visitor.
     */
    public function show(Visitor $visitor): View
    {
        $visitor->load('visits');

        return view('visitors.show', compact('visitor'));
    }

    /**
     * Show the form for editing the specified visitor.
     */
    public function edit(Visitor $visitor): View
    {
        return view('visitors.edit', compact('visitor'));
    }

    /**
     * Update the specified visitor.
     */
    public function update(UpdateVisitorRequest $request, Visitor $visitor): RedirectResponse
    {
        $visitor->update($request->validated());

        AuditLog::record('visitor.updated', $visitor, $request, $request->validated());

        return redirect()->route('visitors.index')->with('status', 'Visitor updated successfully.');
    }

    /**
     * Remove the specified visitor (soft delete).
     */
    public function destroy(Request $request, Visitor $visitor): RedirectResponse
    {
        $visitor->delete();

        AuditLog::record('visitor.deleted', $visitor, $request);

        return redirect()->route('visitors.index')->with('status', 'Visitor deleted successfully.');
    }
}
