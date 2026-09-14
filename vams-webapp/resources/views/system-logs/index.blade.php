@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">System Logs</h1>
        <p class="mt-1 text-sm text-gray-600">Device events, errors, and rejected reader requests.</p>
    </div>

    <form method="GET" action="{{ route('system-logs.index') }}" class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-900/5">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-6">
            <div class="lg:col-span-2">
                <label for="search" class="block text-xs font-semibold text-gray-700 mb-1">Search</label>
                <input id="search" name="search" type="text" value="{{ request('search') }}"
                    placeholder="Message or source"
                    class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>
            <div>
                <label for="level" class="block text-xs font-semibold text-gray-700 mb-1">Level</label>
                <select id="level" name="level"
                    class="block w-full rounded-lg border border-gray-300 px-2 py-2 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                    <option value="">All levels</option>
                    @foreach ($levels as $level)
                        <option value="{{ $level }}" @selected(request('level') === $level)>{{ ucfirst($level) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="source" class="block text-xs font-semibold text-gray-700 mb-1">Source</label>
                <select id="source" name="source"
                    class="block w-full rounded-lg border border-gray-300 px-2 py-2 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                    <option value="">All sources</option>
                    @foreach ($sources as $source)
                        <option value="{{ $source }}" @selected(request('source') === $source)>{{ $source }}</option>
                    @endforeach
                </select>
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
        </div>
        <div class="mt-4 flex items-center gap-3">
            <button type="submit" class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-900">Filter</button>
            @if (request()->hasAny(['search', 'level', 'source', 'from', 'to']))
                <a href="{{ route('system-logs.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700">Reset</a>
            @endif
        </div>
    </form>

    <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-900/5">
        @if ($logs->isEmpty())
            <div class="px-6 py-16 text-center">
                <p class="text-sm font-medium text-gray-900">No system events match these filters</p>
                <p class="mt-1 text-sm text-gray-500">A quiet log is usually good news.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">When</th>
                            <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Level</th>
                            <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Source</th>
                            <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Message</th>
                            <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Reader</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @foreach ($logs as $log)
                            <tr class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-4 py-3">
                                    <div class="text-sm font-semibold text-gray-900">{{ $log->created_at->format('M j, Y') }}</div>
                                    <div class="text-xs text-gray-500">{{ $log->created_at->format('g:i:s A') }}</div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    @php
                                        $levelClass = match ($log->level) {
                                            'error', 'critical' => 'bg-red-50 text-red-700 ring-red-600/20',
                                            'warning' => 'bg-amber-50 text-amber-800 ring-amber-600/20',
                                            default => 'bg-gray-100 text-gray-600 ring-gray-500/10',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $levelClass }}">{{ ucfirst($log->level) }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="font-mono text-xs text-gray-600">{{ $log->source }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $log->message }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                                    {{ $log->rfidReader?->device_code ?? '—' }}
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
