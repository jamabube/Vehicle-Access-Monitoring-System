@php
    // Label for the overstay warning, derived from the configured threshold so
    // the wording follows the setting (e.g. 24h -> "Over 1 day").
    $overstayHours = (int) config('rfid.overstay_alert_hours', 24);
    $overstayLabel = $overstayHours % 24 === 0
        ? 'Over ' . ($overstayHours / 24) . ' day' . ($overstayHours / 24 > 1 ? 's' : '')
        : 'Over ' . $overstayHours . 'h';
@endphp
@if ($vehiclesInside->isEmpty())
    <div class="px-4 py-10 text-center">
        <p class="text-sm font-medium text-gray-900">No vehicles inside</p>
        <p class="mt-1 text-xs text-gray-500">The premises are clear.</p>
    </div>
@else
    <ul class="divide-y divide-gray-100">
        @foreach ($vehiclesInside as $vehicle)
            @php $overstayed = $vehicle->hasOverstayed(); @endphp
            <li class="flex items-center justify-between gap-3 px-4 py-2.5 {{ $overstayed ? 'bg-red-50' : '' }}">
                <div class="min-w-0">
                    <p class="font-mono text-sm font-semibold uppercase text-gray-900">{{ $vehicle->plate_number }}</p>
                    <p class="truncate text-xs text-gray-500">
                        {{ $vehicle->employee?->fullName() ?? 'Visitor / unassigned' }}
                    </p>
                    @if ($overstayed)
                        <span class="mt-1 inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-semibold text-red-700">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                            </svg>
                            {{ $overstayLabel }} &mdash; not yet exited
                        </span>
                    @endif
                </div>
                <span class="whitespace-nowrap text-xs {{ $overstayed ? 'font-semibold text-red-700' : 'text-gray-500' }}">
                    {{ $vehicle->last_seen_at?->diffForHumans(short: true) ?? '—' }}
                </span>
            </li>
        @endforeach
    </ul>
@endif
