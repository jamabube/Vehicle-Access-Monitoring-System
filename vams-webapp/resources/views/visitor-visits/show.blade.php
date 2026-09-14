@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Visit #{{ $visitorVisit->id }}</h1>
        @can('visitor_visits.update')
            <a href="{{ route('visitor-visits.edit', $visitorVisit) }}" class="text-sm font-medium text-slate-600 hover:text-slate-800">Edit</a>
        @endcan
    </div>

    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-lg p-8">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Visitor</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    @if ($visitorVisit->visitor)
                        <a href="{{ route('visitors.show', $visitorVisit->visitor) }}" class="text-brand-700 hover:text-brand-900">{{ $visitorVisit->visitor->fullName() }}</a>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Status</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ ucfirst(str_replace('_', ' ', $visitorVisit->status)) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Host / Department</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $visitorVisit->host_name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Purpose</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $visitorVisit->purpose ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Vehicle</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    @if ($visitorVisit->vehicle)
                        <a href="{{ route('vehicles.show', $visitorVisit->vehicle) }}" class="text-brand-700 hover:text-brand-900">{{ $visitorVisit->vehicle->plate_number }}</a>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">RFID Card</dt>
                <dd class="mt-1 text-sm text-gray-900 font-mono">
                    @if ($visitorVisit->rfidTag)
                        <a href="{{ route('rfid-tags.show', $visitorVisit->rfidTag) }}" class="text-brand-700 hover:text-brand-900">{{ $visitorVisit->rfidTag->epc }}</a>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Valid From</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $visitorVisit->valid_from?->format('M j, Y g:i A') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Valid Until</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $visitorVisit->valid_until?->format('M j, Y g:i A') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Checked Out At</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $visitorVisit->checked_out_at?->format('M j, Y g:i A') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Registered By</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $visitorVisit->registeredBy?->name ?? '—' }}</dd>
            </div>
        </dl>
    </div>

    @can('visitor_visits.update')
        @if ($visitorVisit->isActive())
            <div class="mt-6">
                <form method="POST" action="{{ route('visitor-visits.check-out', $visitorVisit) }}" data-confirm="Check out this visitor?">
                    @csrf
                    <button type="submit" class="inline-flex justify-center rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                        Check Out Visitor
                    </button>
                </form>
            </div>
        @endif
    @endcan

    <div class="mt-6">
        <a href="{{ route('visitor-visits.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">&larr; Back to visitor visits</a>
    </div>
</div>
@endsection
