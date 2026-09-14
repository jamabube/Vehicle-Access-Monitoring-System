@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900 font-mono">{{ $rfidTag->epc }}</h1>
        @can('rfid_tags.update')
            <a href="{{ route('rfid-tags.edit', $rfidTag) }}" class="text-sm font-medium text-slate-600 hover:text-slate-800">Edit</a>
        @endcan
    </div>

    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-lg p-8">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Credential Type</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ ucfirst($rfidTag->credential_type) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Status</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ ucfirst($rfidTag->status) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Issued At</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $rfidTag->issued_at?->format('M j, Y g:i A') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Expires At</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $rfidTag->expires_at?->format('M j, Y g:i A') ?? '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs font-medium text-gray-500 uppercase">Notes</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $rfidTag->notes ?? '—' }}</dd>
            </div>
        </dl>
    </div>

    <div class="mt-6 bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-lg p-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Assignment History</h2>
        @if ($rfidTag->assignments->isEmpty())
            <p class="text-sm text-gray-500">No assignments recorded for this tag yet.</p>
        @else
            <ul class="divide-y divide-gray-200">
                @foreach ($rfidTag->assignments as $assignment)
                    <li class="py-3 text-sm">
                        <span class="font-medium text-gray-900">
                            @if ($assignment->vehicle)
                                Vehicle {{ $assignment->vehicle->plate_number }}
                            @elseif ($assignment->visitorVisit)
                                Visit for {{ $assignment->visitorVisit->visitor?->fullName() ?? 'visitor' }}
                            @else
                                Unassigned
                            @endif
                        </span>
                        <span class="text-gray-500">
                            — assigned {{ $assignment->assigned_at?->format('M j, Y g:i A') }}
                            @if ($assignment->released_at)
                                , released {{ $assignment->released_at->format('M j, Y g:i A') }}
                            @else
                                (active)
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="mt-6">
        <a href="{{ route('rfid-tags.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">&larr; Back to RFID tags</a>
    </div>
</div>
@endsection
