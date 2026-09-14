@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ $employee->fullName() }}</h1>
        @can('employees.update')
            <a href="{{ route('employees.edit', $employee) }}" class="text-sm font-medium text-slate-600 hover:text-slate-800">Edit</a>
        @endcan
    </div>

    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-lg p-8 mb-6">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Employee Code</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $employee->employee_code }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Status</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ ucfirst($employee->status) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Department</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $employee->department ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Position</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $employee->position ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Contact Number</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $employee->contact_number ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Email</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $employee->email ?? '—' }}</dd>
            </div>
        </dl>
    </div>

    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-lg p-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Vehicles</h2>
        @forelse ($employee->vehicles as $vehicle)
            <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                <span class="text-sm text-gray-900">{{ $vehicle->plate_number }} — {{ $vehicle->make }} {{ $vehicle->model }}</span>
                @can('vehicles.view')
                    <a href="{{ route('vehicles.show', $vehicle) }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">View</a>
                @endcan
            </div>
        @empty
            <p class="text-sm text-gray-500">No vehicles registered for this employee.</p>
        @endforelse
    </div>

    <div class="mt-6">
        <a href="{{ route('employees.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">&larr; Back to employees</a>
    </div>
</div>
@endsection
