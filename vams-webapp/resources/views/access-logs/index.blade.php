@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Access Logs</h1>
            <p class="mt-1 text-sm text-gray-600">Complete history of vehicles entering and leaving the premises.</p>
        </div>
        <a href="{{ route('access-logs.export', request()->query()) }}"
            class="inline-flex items-center gap-2 rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800 focus:outline-none focus:ring-2 focus:ring-brand-500">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Export CSV
        </a>
    </div>

    <!-- Filters -->
    <form method="GET" action="{{ route('access-logs.index') }}" class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-900/5">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <label for="search" class="block text-xs font-semibold text-gray-700 mb-1">Search</label>
                <input id="search" name="search" type="text" value="{{ request('search') }}"
                    placeholder="Plate, name, tag code, or EPC"
                    class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>

            <div>
                <label for="from" class="block text-xs font-semibold text-gray-700 mb-1">From</label>
                <input id="from" name="from" type="date" value="{{ request('from') }}"
                    class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>

            <div>
                <label for="to" class="block text-xs font-semibold text-gray-700 mb-1">To</label>
                <input id="to" name="to" type="date" value="{{ request('to') }}"
                    class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label for="decision" class="block text-xs font-semibold text-gray-700 mb-1">Result</label>
                    <select id="decision" name="decision"
                        class="block w-full rounded-lg border border-gray-300 px-2 py-2 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <option value="">All</option>
                        <option value="authorized" @selected(request('decision') === 'authorized')>Allowed</option>
                        <option value="denied" @selected(request('decision') === 'denied')>Denied</option>
                    </select>
                </div>
                <div>
                    <label for="direction" class="block text-xs font-semibold text-gray-700 mb-1">Direction</label>
                    <select id="direction" name="direction"
                        class="block w-full rounded-lg border border-gray-300 px-2 py-2 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <option value="">All</option>
                        <option value="entry" @selected(request('direction') === 'entry')>In</option>
                        <option value="exit" @selected(request('direction') === 'exit')>Out</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="mt-4 flex items-center gap-3">
            <button type="submit" class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-900">Filter</button>
            @if (request()->hasAny(['search', 'from', 'to', 'decision', 'direction']))
                <a href="{{ route('access-logs.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700">Reset</a>
            @endif
        </div>
    </form>

    <!-- Summary for the current filter -->
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
        <div class="rounded-lg bg-white p-3 text-center shadow-sm ring-1 ring-gray-900/5">
            <p class="text-xs font-medium text-gray-500">Records</p>
            <p class="mt-0.5 text-xl font-bold text-gray-900">{{ number_format($summary['total']) }}</p>
        </div>
        <div class="rounded-lg bg-white p-3 text-center shadow-sm ring-1 ring-gray-900/5">
            <p class="text-xs font-medium text-gray-500">In</p>
            <p class="mt-0.5 text-xl font-bold text-green-700">{{ number_format($summary['entries']) }}</p>
        </div>
        <div class="rounded-lg bg-white p-3 text-center shadow-sm ring-1 ring-gray-900/5">
            <p class="text-xs font-medium text-gray-500">Out</p>
            <p class="mt-0.5 text-xl font-bold text-amber-700">{{ number_format($summary['exits']) }}</p>
        </div>
        <div class="rounded-lg bg-white p-3 text-center shadow-sm ring-1 ring-gray-900/5">
            <p class="text-xs font-medium text-gray-500">Allowed</p>
            <p class="mt-0.5 text-xl font-bold text-gray-900">{{ number_format($summary['authorized']) }}</p>
        </div>
        <div class="rounded-lg bg-white p-3 text-center shadow-sm ring-1 ring-gray-900/5">
            <p class="text-xs font-medium text-gray-500">Denied</p>
            <p class="mt-0.5 text-xl font-bold text-red-700">{{ number_format($summary['denied']) }}</p>
        </div>
    </div>

    <!-- Results -->
    <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-900/5">
        @if ($logs->isEmpty())
            <div class="px-6 py-16 text-center">
                <p class="text-sm font-medium text-gray-900">No records match these filters</p>
                <p class="mt-1 text-sm text-gray-500">Try widening the date range or clearing the search.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Date &amp; Time</th>
                            <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Vehicle</th>
                            <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Driver / Owner</th>
                            <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Type</th>
                            <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">In / Out</th>
                            <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Result</th>
                            <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Tag / EPC</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @foreach ($logs as $log)
                            <tr class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-4 py-3">
                                    <div class="text-sm font-semibold text-gray-900">{{ $log->occurred_at->format('M j, Y') }}</div>
                                    <div class="text-xs text-gray-500">{{ $log->occurred_at->format('g:i:s A') }}</div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    @if ($log->vehicle)
                                        <span class="font-mono text-sm font-semibold uppercase text-gray-900">{{ $log->vehicle->plate_number }}</span>
                                        @if ($log->vehicle->trashed())
                                            <div class="text-xs italic text-gray-400">record since removed</div>
                                        @endif
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
                                        <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-bold text-green-700 ring-1 ring-inset ring-green-600/20">IN</span>
                                    @elseif ($log->direction === 'exit')
                                        <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-1 text-xs font-bold text-amber-800 ring-1 ring-inset ring-amber-600/20">OUT</span>
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
                                <td class="whitespace-nowrap px-4 py-3">
                                    @if ($log->rfidTag)
                                        <div class="text-sm font-semibold text-gray-900">{{ $log->rfidTag->tag_code }}</div>
                                        <div class="font-mono text-xs text-gray-400">{{ $log->rfidTag->epc }}</div>
                                    @else
                                        <span class="text-sm text-gray-400">— unregistered —</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div class="border-t border-gray-200 px-4 py-3">
                    {{ $logs->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
