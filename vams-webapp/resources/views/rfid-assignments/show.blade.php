@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Assignment #{{ $rfidAssignment->id }}</h1>
        @can('rfid_assignments.update')
            <a href="{{ route('rfid-assignments.edit', $rfidAssignment) }}" class="text-sm font-medium text-slate-600 hover:text-slate-800">Edit</a>
        @endcan
    </div>

    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-lg p-8">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">RFID Tag</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    @if ($rfidAssignment->rfidTag)
                        <a href="{{ route('rfid-tags.show', $rfidAssignment->rfidTag) }}" class="text-brand-700 hover:text-brand-900 font-mono">{{ $rfidAssignment->rfidTag->epc }}</a>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Assigned To</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    @if ($rfidAssignment->vehicle)
                        <a href="{{ route('vehicles.show', $rfidAssignment->vehicle) }}" class="text-brand-700 hover:text-brand-900">Vehicle {{ $rfidAssignment->vehicle->plate_number }}</a>
                    @elseif ($rfidAssignment->visitorVisit)
                        Visit #{{ $rfidAssignment->visitorVisit->id }} — {{ $rfidAssignment->visitorVisit->visitor?->fullName() ?? 'Visitor' }}
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Assigned At</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $rfidAssignment->assigned_at?->format('M j, Y g:i A') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Released At</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $rfidAssignment->released_at?->format('M j, Y g:i A') ?? 'Active' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Assigned By</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $rfidAssignment->assignedBy?->name ?? '—' }}</dd>
            </div>
        </dl>
    </div>

    @can('rfid_assignments.update')
        @if ($rfidAssignment->isActive())
            <div class="mt-6">
                <form method="POST" action="{{ route('rfid-assignments.release', $rfidAssignment) }}" data-confirm="Release this assignment?">
                    @csrf
                    <button type="submit" class="inline-flex justify-center rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                        Release Assignment
                    </button>
                </form>
            </div>
        @endif
    @endcan

    <div class="mt-6">
        <a href="{{ route('rfid-assignments.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">&larr; Back to RFID assignments</a>
    </div>
</div>
@endsection
