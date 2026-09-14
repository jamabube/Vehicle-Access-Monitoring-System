@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-6xl">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">RFID Assignments</h1>
        @can('rfid_assignments.create')
            <a href="{{ route('rfid-assignments.create') }}" class="inline-flex items-center rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                + Add Assignment
            </a>
        @endcan
    </div>

    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-lg p-4 mb-6">
        <form method="GET" action="{{ route('rfid-assignments.index') }}" class="flex flex-wrap items-end gap-4">
            <div>
                <label for="status" class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                <select id="status" name="status" class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                    <option value="">All</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="released" @selected(request('status') === 'released')>Released</option>
                </select>
            </div>
            <button type="submit" class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-900">Filter</button>
            <a href="{{ route('rfid-assignments.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700">Reset</a>
        </form>
    </div>

    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">RFID Tag</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Assigned To</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Assigned At</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($rfidAssignments as $rfidAssignment)
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900 font-medium font-mono">{{ $rfidAssignment->rfidTag?->epc ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">
                            @if ($rfidAssignment->vehicle)
                                Vehicle {{ $rfidAssignment->vehicle->plate_number }}
                            @elseif ($rfidAssignment->visitorVisit)
                                Visit — {{ $rfidAssignment->visitorVisit->visitor?->fullName() ?? 'Visitor' }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $rfidAssignment->assigned_at?->format('M j, Y g:i A') }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $rfidAssignment->isActive() ? 'bg-brand-100 text-brand-900' : 'bg-gray-100 text-gray-600' }}">
                                {{ $rfidAssignment->isActive() ? 'Active' : 'Released' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-sm space-x-3">
                            <a href="{{ route('rfid-assignments.show', $rfidAssignment) }}" class="font-medium text-brand-700 hover:text-brand-900">View</a>
                            @can('rfid_assignments.update')
                                @if ($rfidAssignment->isActive())
                                    <form method="POST" action="{{ route('rfid-assignments.release', $rfidAssignment) }}" class="inline" data-confirm="Release this assignment?">
                                        @csrf
                                        <button type="submit" class="font-medium text-amber-600 hover:text-amber-800">Release</button>
                                    </form>
                                @endif
                            @endcan
                            @can('rfid_assignments.delete')
                                <form method="POST" action="{{ route('rfid-assignments.destroy', $rfidAssignment) }}" class="inline" data-confirm="Delete this RFID assignment?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="font-medium text-red-600 hover:text-red-800">Delete</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">No RFID assignments found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $rfidAssignments->links() }}
    </div>
</div>
@endsection
