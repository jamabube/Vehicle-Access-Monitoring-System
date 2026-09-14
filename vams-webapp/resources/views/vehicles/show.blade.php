@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ $vehicle->plate_number }}</h1>
        @can('vehicles.update')
            <a href="{{ route('vehicles.edit', $vehicle) }}" class="text-sm font-medium text-slate-600 hover:text-slate-800">Edit</a>
        @endcan
    </div>

    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-lg p-8">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Vehicle Type</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $vehicle->vehicle_type ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Make/Model</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ trim("{$vehicle->make} {$vehicle->model}") ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Color</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $vehicle->color ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Owner</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    @if ($vehicle->employee)
                        <a href="{{ route('employees.show', $vehicle->employee) }}" class="text-brand-700 hover:text-brand-900">{{ $vehicle->employee->fullName() }}</a>
                    @else
                        {{ ucfirst($vehicle->owner_type) }}
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Status</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ ucfirst($vehicle->status) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Current State</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ ucfirst($vehicle->current_state) }}</dd>
            </div>
            @can('vehicles.update_state')
                <div class="sm:col-span-2 rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-800">Correct gate state</p>
                    <p class="mt-1 text-sm text-amber-900">Use this only to fix a missed exit scan. Ordinary in/out changes come from the RFID reader.</p>
                    <form method="POST" action="{{ route('vehicles.update-state', $vehicle) }}" class="mt-3 flex flex-wrap items-end gap-3">
                        @csrf
                        <div>
                            <label for="current_state" class="block text-xs font-medium text-amber-800 mb-1">Set state to</label>
                            <select id="current_state" name="current_state" required
                                class="block px-3 py-2 border border-amber-300 rounded-lg text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                                <option value="outside" @selected($vehicle->current_state === 'outside')>Outside</option>
                                <option value="inside" @selected($vehicle->current_state === 'inside')>Inside</option>
                            </select>
                        </div>
                        <button type="submit" class="rounded-lg bg-amber-700 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-800">
                            Update state
                        </button>
                    </form>
                </div>
            @endcan
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Last Seen</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $vehicle->last_seen_at?->format('M j, Y g:i A') ?? '—' }}</dd>
            </div>
        </dl>
    </div>

    <div class="mt-6">
        <a href="{{ route('vehicles.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">&larr; Back to vehicles</a>
    </div>
</div>
@endsection
