<?php

namespace App\Http\Controllers;

use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Models\AuditLog;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class EmployeeController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:employees.view', only: ['index', 'show']),
            new Middleware('can:employees.create', only: ['create', 'store']),
            new Middleware('can:employees.update', only: ['edit', 'update']),
            new Middleware('can:employees.delete', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of employees.
     */
    public function index(Request $request): View
    {
        $employees = Employee::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(function ($q) use ($search) {
                    $q->where('employee_code', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('department', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('last_name')
            ->paginate(15)
            ->withQueryString();

        return view('employees.index', compact('employees'));
    }

    /**
     * Show the form for creating a new employee.
     */
    public function create(): View
    {
        return view('employees.create', ['employee' => new Employee]);
    }

    /**
     * Store a newly created employee.
     */
    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $employee = Employee::create($request->validated());

        AuditLog::record('employee.created', $employee, $request, $request->validated());

        return redirect()->route('employees.index')->with('status', 'Employee created successfully.');
    }

    /**
     * Display the specified employee.
     */
    public function show(Employee $employee): View
    {
        $employee->load('vehicles');

        return view('employees.show', compact('employee'));
    }

    /**
     * Show the form for editing the specified employee.
     */
    public function edit(Employee $employee): View
    {
        return view('employees.edit', compact('employee'));
    }

    /**
     * Update the specified employee.
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $employee->update($request->validated());

        AuditLog::record('employee.updated', $employee, $request, $request->validated());

        return redirect()->route('employees.index')->with('status', 'Employee updated successfully.');
    }

    /**
     * Remove the specified employee (soft delete).
     */
    public function destroy(Request $request, Employee $employee): RedirectResponse
    {
        $employee->delete();

        AuditLog::record('employee.deleted', $employee, $request);

        return redirect()->route('employees.index')->with('status', 'Employee deleted successfully.');
    }
}
