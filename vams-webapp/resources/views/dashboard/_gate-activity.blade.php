@if ($gateActivity->isEmpty())
    <div class="px-6 py-16 text-center">
        <svg class="mx-auto h-10 w-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
        </svg>
        <p class="mt-3 text-sm font-medium text-gray-900">No vehicles recorded yet</p>
        <p class="mt-1 text-sm text-gray-500">Entries and exits will appear here automatically as vehicles pass the reader.</p>
    </div>
@else
    @unless ($showingToday)
        <p class="border-b border-amber-200 bg-amber-50 px-4 py-2 text-xs font-medium text-amber-800">
            No activity today yet — showing the most recent {{ $gateActivity->count() }} events instead.
        </p>
    @endunless

    {{-- No overflow wrapper here on purpose: the scroll container is the
         persistent [data-gate-activity] element in dashboard.blade.php, so it
         survives the refresh that swaps this partial's HTML. A nested
         overflow-x div would become its own vertical scroll container and
         break the sticky header below. --}}
    <table class="min-w-full divide-y divide-gray-200">
            <thead class="sticky top-0 z-10 bg-gray-50">
                <tr>
                    <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Time</th>
                    <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Vehicle</th>
                    <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Driver / Owner</th>
                    <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Type</th>
                    <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">In / Out</th>
                    <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Result</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @foreach ($gateActivity as $log)
                    <tr class="hover:bg-gray-50">
                        <td class="whitespace-nowrap px-4 py-3">
                            <div class="text-sm font-semibold text-gray-900">{{ $log->occurred_at->format('g:i:s A') }}</div>
                            <div class="text-xs text-gray-500">{{ $log->occurred_at->format('M j, Y') }}</div>
                        </td>

                        <td class="whitespace-nowrap px-4 py-3">
                            @if ($log->vehicle)
                                <span class="font-mono text-sm font-semibold uppercase text-gray-900">{{ $log->vehicle->plate_number }}</span>
                                @if ($log->vehicle->trashed())
                                    <div class="text-xs italic text-gray-400">record since removed</div>
                                @endif
                            @elseif ($log->rfidTag)
                                {{-- No vehicle attached, so name the credential instead:
                                     during enrolment this is the only way to tell which
                                     physical sticker was just presented to the reader. --}}
                                <span class="text-sm font-semibold text-gray-700">{{ $log->rfidTag->tag_code }}</span>
                                <div class="text-xs italic text-gray-400">no vehicle assigned</div>
                            @else
                                <span class="text-sm text-gray-400">— on foot —</span>
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            @if ($log->vehicle?->employee)
                                <span class="text-sm text-gray-900">{{ $log->vehicle->employee->fullName() }}</span>
                            @elseif ($log->visitorVisit?->visitor)
                                <span class="text-sm text-gray-900">{{ $log->visitorVisit->visitor->fullName() }}</span>
                            @else
                                <span class="text-sm text-gray-400">Unknown</span>
                            @endif
                        </td>

                        <td class="whitespace-nowrap px-4 py-3">
                            @if ($log->visitorVisit)
                                <span class="inline-flex items-center rounded-md bg-purple-50 px-2 py-0.5 text-xs font-medium text-purple-700 ring-1 ring-inset ring-purple-700/10">Visitor</span>
                            @elseif ($log->vehicle)
                                <span class="inline-flex items-center rounded-md bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-800 ring-1 ring-inset ring-brand-800/10">Employee</span>
                            @else
                                <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10">Unregistered</span>
                            @endif
                        </td>

                        <td class="whitespace-nowrap px-4 py-3">
                            @if ($log->direction === 'entry')
                                <span class="inline-flex items-center gap-1 rounded-md bg-green-50 px-2 py-1 text-xs font-bold text-green-700 ring-1 ring-inset ring-green-600/20">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                    IN
                                </span>
                            @elseif ($log->direction === 'exit')
                                <span class="inline-flex items-center gap-1 rounded-md bg-amber-50 px-2 py-1 text-xs font-bold text-amber-800 ring-1 ring-inset ring-amber-600/20">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                    OUT
                                </span>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            @if ($log->decision === 'authorized')
                                <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20">Allowed</span>
                            @else
                                <span class="inline-flex items-center rounded-md bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-600/20">Denied</span>
                                @if ($log->denial_reason)
                                    <div class="mt-0.5 text-xs text-gray-500">{{ ucfirst(str_replace('_', ' ', $log->denial_reason)) }}</div>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
    </table>
@endif
