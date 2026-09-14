@if ($vehiclesInside->isEmpty())
    <div class="px-4 py-10 text-center">
        <p class="text-sm font-medium text-gray-900">No vehicles inside</p>
        <p class="mt-1 text-xs text-gray-500">The premises are clear.</p>
    </div>
@else
    <ul class="divide-y divide-gray-100">
        @foreach ($vehiclesInside as $vehicle)
            <li class="flex items-center justify-between gap-3 px-4 py-2.5">
                <div class="min-w-0">
                    <p class="font-mono text-sm font-semibold uppercase text-gray-900">{{ $vehicle->plate_number }}</p>
                    <p class="truncate text-xs text-gray-500">
                        {{ $vehicle->employee?->fullName() ?? 'Visitor / unassigned' }}
                    </p>
                </div>
                <span class="whitespace-nowrap text-xs text-gray-500">
                    {{ $vehicle->last_seen_at?->diffForHumans(short: true) ?? '—' }}
                </span>
            </li>
        @endforeach
    </ul>
@endif
