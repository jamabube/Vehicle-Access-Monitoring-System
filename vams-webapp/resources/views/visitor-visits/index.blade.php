@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-6xl">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Visitor Visits</h1>
        @can('visitor_visits.create')
            <a href="{{ route('visitor-visits.create') }}" class="inline-flex items-center rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                + Check In Visitor
            </a>
        @endcan
    </div>

    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-lg p-4 mb-6">
        <form method="GET" action="{{ route('visitor-visits.index') }}" class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[200px]">
                <label for="search" class="block text-xs font-medium text-gray-500 mb-1">Search Visitor</label>
                <input id="search" name="search" type="text" value="{{ request('search') }}" placeholder="First or last name..."
                    class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>
            <div>
                <label for="status" class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                <select id="status" name="status" class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                    <option value="">All</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="checked_out" @selected(request('status') === 'checked_out')>Checked Out</option>
                    <option value="expired" @selected(request('status') === 'expired')>Expired</option>
                    <option value="cancelled" @selected(request('status') === 'cancelled')>Cancelled</option>
                </select>
            </div>
            <button type="submit" class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-900">Filter</button>
            <a href="{{ route('visitor-visits.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700">Reset</a>
        </form>

    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Visitor</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Vehicle</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">RFID Card</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Valid Window</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($visitorVisits as $visitorVisit)
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900 font-medium">{{ $visitorVisit->visitor?->fullName() ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $visitorVisit->vehicle?->plate_number ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500 font-mono">{{ $visitorVisit->rfidTag?->epc ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">
                            {{ $visitorVisit->valid_from?->format('M j, Y g:i A') }} &ndash; {{ $visitorVisit->valid_until?->format('M j, Y g:i A') }}
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $visitorVisit->status === 'active' ? 'bg-brand-100 text-brand-900' : ($visitorVisit->status === 'checked_out' ? 'bg-gray-100 text-gray-600' : ($visitorVisit->status === 'expired' ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800')) }}">
                                {{ ucfirst(str_replace('_', ' ', $visitorVisit->status)) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-sm space-x-3">
                            <a href="{{ route('visitor-visits.show', $visitorVisit) }}" class="font-medium text-brand-700 hover:text-brand-900">View</a>
                            @can('visitor_visits.update')
                                <a href="{{ route('visitor-visits.edit', $visitorVisit) }}" class="font-medium text-slate-600 hover:text-slate-800">Edit</a>
                                @if ($visitorVisit->isActive())
                                    <form method="POST" action="{{ route('visitor-visits.check-out', $visitorVisit) }}" class="inline" data-confirm="Check out this visitor?">
                                        @csrf
                                        <button type="submit" class="font-medium text-amber-600 hover:text-amber-800">Check Out</button>
                                    </form>
                                @endif
                            @endcan
                            @can('visitor_visits.delete')
                                <form method="POST" action="{{ route('visitor-visits.destroy', $visitorVisit) }}" class="inline" data-confirm="Delete this visit record?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="font-medium text-red-600 hover:text-red-800">Delete</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">No visitor visits found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $visitorVisits->links() }}
    </div>
</div>
@endsection

    </div>
