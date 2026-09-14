@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ $visitor->fullName() }}</h1>
        @can('visitors.update')
            <a href="{{ route('visitors.edit', $visitor) }}" class="text-sm font-medium text-slate-600 hover:text-slate-800">Edit</a>
        @endcan
    </div>

    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-lg p-8 mb-6">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Contact Number</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $visitor->contact_number ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Valid ID Type</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $visitor->valid_id_type ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Valid ID Number</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $visitor->valid_id_number ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Address</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $visitor->address ?? '—' }}</dd>
            </div>
        </dl>
    </div>

    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-lg p-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900">Visit History</h2>
            @can('visitor_visits.create')
                <a href="{{ route('visitor-visits.create') }}" class="text-sm font-medium text-brand-700 hover:text-brand-900">+ Check In New Visit</a>
            @endcan
        </div>
        @forelse ($visitor->visits as $visit)
            <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                <span class="text-sm text-gray-900">
                    @can('visitor_visits.view')
                        <a href="{{ route('visitor-visits.show', $visit) }}" class="text-brand-700 hover:text-brand-900">
                            {{ $visit->valid_from?->format('M j, Y g:i A') }}
                        </a>
                    @else
                        {{ $visit->valid_from?->format('M j, Y g:i A') }}
                    @endcan
                    — {{ ucfirst(str_replace('_', ' ', $visit->status)) }}
                    @if ($visit->purpose)
                        ({{ $visit->purpose }})
                    @endif
                </span>
            </div>
        @empty
            <p class="text-sm text-gray-500">No recorded visits yet.</p>
        @endforelse
    </div>

    <div class="mt-6">
        <a href="{{ route('visitors.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">&larr; Back to visitors</a>
    </div>
</div>
@endsection
