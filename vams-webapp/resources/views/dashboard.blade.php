@extends('layouts.app')

@section('content')
<div class="space-y-6"
    data-dashboard
    data-refresh-url="{{ route('dashboard.gate-activity') }}">

    <!-- Header -->
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Gate Activity</h1>
            <p class="mt-1 text-sm text-gray-600">Vehicles entering and leaving Forest Lawn Memorial Park, recorded automatically.</p>
        </div>
        <div class="flex items-center gap-2 text-xs text-gray-500">
            <span class="relative flex h-2 w-2" data-live-dot>
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-500 opacity-75"></span>
                <span class="relative inline-flex h-2 w-2 rounded-full bg-green-600"></span>
            </span>
            <span data-live-label>Live &mdash; updates automatically</span>
        </div>
    </div>

    <!-- Today's totals -->
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-900/5">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Entries today</p>
            <p class="mt-1 text-3xl font-bold text-green-700" data-stat="entries">{{ number_format($todayStats['entries']) }}</p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-900/5">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Exits today</p>
            <p class="mt-1 text-3xl font-bold text-amber-700" data-stat="exits">{{ number_format($todayStats['exits']) }}</p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-900/5">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Currently inside</p>
            <p class="mt-1 text-3xl font-bold text-brand-800" data-stat="inside">{{ number_format($stats['vehicles_inside']) }}</p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-900/5">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Allowed today</p>
            <p class="mt-1 text-3xl font-bold text-gray-900" data-stat="authorized">{{ number_format($todayStats['authorized']) }}</p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-900/5">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Denied today</p>
            <p class="mt-1 text-3xl font-bold text-red-700" data-stat="denied">{{ number_format($todayStats['denied']) }}</p>
        </div>
    </div>

    <!-- The log itself: the digital replacement for the paper logbook -->
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-900/5 xl:col-span-2">
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                <h2 class="text-base font-semibold text-gray-900">Today&rsquo;s Log</h2>
                <span class="text-xs text-gray-500">Newest first</span>
            </div>
            {{-- Fixed height with its own scrollbar: today's log can run to 100
                 rows, and a guard should not have to scroll the whole page past
                 it to reach anything else. This element persists across
                 refreshes (only its contents are swapped), so the scroll
                 position can be restored — see refresh() below. --}}
            <div data-gate-activity class="max-h-[32rem] overflow-auto">
                @include('dashboard._gate-activity', ['gateActivity' => $gateActivity, 'showingToday' => $showingToday])
            </div>
        </div>

        <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-900/5">
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                <h2 class="text-base font-semibold text-gray-900">Inside Now</h2>
                <span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand-800" data-stat="inside">{{ $stats['vehicles_inside'] }}</span>
            </div>
            <div data-vehicles-inside class="max-h-[32rem] overflow-auto">
                @include('dashboard._vehicles-inside', ['vehiclesInside' => $vehiclesInside])
            </div>
        </div>
    </div>

    <!-- Records management: secondary to the log above -->
    <div>
        <h2 class="mb-3 text-base font-semibold text-gray-900">Records</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @can('employees.view')
            <a href="{{ route('employees.index') }}" class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-900/5 hover:ring-brand-300">
                <p class="text-xs font-medium text-gray-500">Employees</p>
                <p class="mt-1 text-xl font-semibold text-gray-900">{{ number_format($stats['employees']) }}</p>
            </a>
            @endcan

            @can('vehicles.view')
            <a href="{{ route('vehicles.index') }}" class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-900/5 hover:ring-brand-300">
                <p class="text-xs font-medium text-gray-500">Vehicles</p>
                <p class="mt-1 text-xl font-semibold text-gray-900">{{ number_format($stats['vehicles']) }}</p>
            </a>
            @endcan

            @can('visitor_visits.view')
            <a href="{{ route('visitor-visits.index') }}" class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-900/5 hover:ring-brand-300">
                <p class="text-xs font-medium text-gray-500">Active Visits</p>
                <p class="mt-1 text-xl font-semibold text-gray-900">{{ number_format($stats['active_visits']) }}</p>
            </a>
            @endcan

            @can('rfid_tags.view')
            <a href="{{ route('rfid-tags.index') }}" class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-900/5 hover:ring-brand-300">
                <p class="text-xs font-medium text-gray-500">RFID Tags</p>
                <div class="mt-1 flex items-baseline gap-2">
                    <p class="text-xl font-semibold text-gray-900">{{ number_format($stats['rfid_tags']) }}</p>
                    <span class="text-xs text-gray-500">{{ $stats['rfid_tags_assigned'] }} assigned</span>
                </div>
            </a>
            @endcan
        </div>
    </div>

    <!-- Quick Actions -->
    <div>
        <h2 class="mb-3 text-base font-semibold text-gray-900">Quick Actions</h2>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            @can('visitor_visits.create')
            <a href="{{ route('visitor-visits.create') }}" class="flex items-center gap-3 rounded-lg border-2 border-dashed border-gray-300 p-4 hover:border-gray-400 hover:bg-gray-50">
                <div class="rounded-md bg-brand-50 p-2">
                    <svg class="h-5 w-5 text-brand-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                    </svg>
                </div>
                <span class="text-sm font-semibold text-gray-900">Check In Visitor</span>
            </a>
            @endcan

            @can('rfid_assignments.create')
            <a href="{{ route('rfid-assignments.create') }}" class="flex items-center gap-3 rounded-lg border-2 border-dashed border-gray-300 p-4 hover:border-gray-400 hover:bg-gray-50">
                <div class="rounded-md bg-orange-50 p-2">
                    <svg class="h-5 w-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                    </svg>
                </div>
                <span class="text-sm font-semibold text-gray-900">Assign RFID Tag</span>
            </a>
            @endcan

            @can('vehicles.create')
            <a href="{{ route('vehicles.create') }}" class="flex items-center gap-3 rounded-lg border-2 border-dashed border-gray-300 p-4 hover:border-gray-400 hover:bg-gray-50">
                <div class="rounded-md bg-green-50 p-2">
                    <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                </div>
                <span class="text-sm font-semibold text-gray-900">Register Vehicle</span>
            </a>
            @endcan
        </div>
    </div>
</div>

{{-- Live refresh behaviour lives in resources/js/dashboard.js (bundled via
     Vite and imported from app.js) instead of an inline <script> block,
     because the app's CSP (script-src 'self') blocks inline scripts. --}}
@endsection
