@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Audit Logs</h1>
        <p class="mt-1 text-sm text-gray-600">Who changed what, and when. This record is read-only.</p>
    </div>

    <form method="GET" action="{{ route('audit-logs.index') }}" class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-900/5">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="search" class="block text-xs font-semibold text-gray-700 mb-1">Search</label>
                <input id="search" name="search" type="text" value="{{ request('search') }}"
                    placeholder="User, action, or IP"
                    class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>
            <div>
                <label for="action" class="block text-xs font-semibold text-gray-700 mb-1">Action</label>
                <select id="action" name="action"
                    class="block w-full rounded-lg border border-gray-300 px-2 py-2 text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                    <option value="">All actions</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
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
            @if (request()->hasAny(['search', 'action', 'from', 'to']))
                <a href="{{ route('audit-logs.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700">Reset</a>
            @endif
        </div>
    </form>

    <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-900/5">
        @if ($logs->isEmpty())
            <div class="px-6 py-16 text-center">
                <p class="text-sm font-medium text-gray-900">No audit records match these filters</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">When</th>
                            <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">User</th>
                            <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Action</th>
                            <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Subject</th>
                            <th scope="col" class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">IP Address</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @foreach ($logs as $log)
                            <tr class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-4 py-3">
                                    <div class="text-sm font-semibold text-gray-900">{{ $log->created_at->format('M j, Y') }}</div>
                                    <div class="text-xs text-gray-500">{{ $log->created_at->format('g:i:s A') }}</div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-900">
                                    {{ $log->user?->name ?? 'System' }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span class="inline-flex items-center rounded-md bg-brand-50 px-2 py-0.5 font-mono text-xs font-medium text-brand-800 ring-1 ring-inset ring-brand-800/10">{{ $log->action }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    @if ($log->subject_type)
                                        {{ class_basename($log->subject_type) }} <span class="text-gray-400">#{{ $log->subject_id }}</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-gray-500">{{ $log->ip_address ?? '—' }}</td>
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
