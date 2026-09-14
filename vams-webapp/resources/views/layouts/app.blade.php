<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-50">
    <div class="min-h-screen lg:flex">
        <!-- Mobile top bar -->
        <div class="flex items-center justify-between bg-brand-950 px-4 py-3 lg:hidden">
            <div class="flex items-center gap-2">
                <img src="{{ asset('images/logo.svg') }}" alt="{{ config('app.name') }}" class="h-8 w-auto">
                <span class="text-sm font-semibold text-white">{{ config('app.name') }}</span>
            </div>
            <button type="button" id="vams-sidebar-toggle" class="text-white/80 hover:text-white">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>

        <!-- Sidebar -->
        <aside id="vams-sidebar" class="hidden lg:flex lg:flex-shrink-0 lg:w-64 bg-brand-950 flex-col lg:sticky lg:top-0 lg:h-screen">
            <div class="flex items-center gap-3 px-6 py-6">
                <img src="{{ asset('images/logo.svg') }}" alt="{{ config('app.name') }}" class="h-9 w-auto">
                <div class="leading-tight">
                    <p class="text-sm font-semibold text-white">{{ env('APP_ORGANIZATION', config('app.name')) }}</p>
                    <p class="text-xs text-brand-200/70">Access Monitoring</p>
                </div>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 pb-6">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('dashboard') ? 'bg-brand-800 text-white' : 'text-brand-100/80 hover:bg-brand-900 hover:text-white' }}">
                    <svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    Dashboard
                </a>

                @can('employees.view')
                    <a href="{{ route('employees.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('employees.*') ? 'bg-brand-800 text-white' : 'text-brand-100/80 hover:bg-brand-900 hover:text-white' }}">
                        <svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        Employees
                    </a>
                @endcan

                @can('vehicles.view')
                    <a href="{{ route('vehicles.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('vehicles.*') ? 'bg-brand-800 text-white' : 'text-brand-100/80 hover:bg-brand-900 hover:text-white' }}">
                        <svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        Vehicles
                    </a>
                @endcan

                @can('visitors.view')
                    <a href="{{ route('visitors.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('visitors.*') ? 'bg-brand-800 text-white' : 'text-brand-100/80 hover:bg-brand-900 hover:text-white' }}">
                        <svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        Visitors
                    </a>
                @endcan

                @can('visitor_visits.view')
                    <a href="{{ route('visitor-visits.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('visitor-visits.*') ? 'bg-brand-800 text-white' : 'text-brand-100/80 hover:bg-brand-900 hover:text-white' }}">
                        <svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Visitor Visits
                    </a>
                @endcan

                @can('rfid_tags.view')
                    <a href="{{ route('rfid-tags.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('rfid-tags.*') ? 'bg-brand-800 text-white' : 'text-brand-100/80 hover:bg-brand-900 hover:text-white' }}">
                        <svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                        </svg>
                        RFID Tags
                    </a>
                @endcan

                @can('rfid_assignments.view')
                    <a href="{{ route('rfid-assignments.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('rfid-assignments.*') ? 'bg-brand-800 text-white' : 'text-brand-100/80 hover:bg-brand-900 hover:text-white' }}">
                        <svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        RFID Assignments
                    </a>
                @endcan

                @can('rfid_readers.view')
                    <a href="{{ route('rfid-readers.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('rfid-readers.*') ? 'bg-brand-800 text-white' : 'text-brand-100/80 hover:bg-brand-900 hover:text-white' }}">
                        <svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/>
                        </svg>
                        RFID Readers
                    </a>
                @endcan

                @can('users.view')
                    <a href="{{ route('users.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('users.*') ? 'bg-brand-800 text-white' : 'text-brand-100/80 hover:bg-brand-900 hover:text-white' }}">
                        <svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2m18 0v-2a4 4 0 00-3-3.87m-4-12a4 4 0 010 7.75M9 11a4 4 0 100-8 4 4 0 000 8z" />
                        </svg>
                        Users
                    </a>
                @endcan

                @canany(['access_logs.view', 'audit_logs.view', 'system_logs.view'])
                    <p class="px-3 pb-1 pt-4 text-xs font-semibold uppercase tracking-wide text-brand-100/40">Reports</p>
                @endcanany

                @can('access_logs.view')
                    <a href="{{ route('access-logs.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('access-logs.*') ? 'bg-brand-800 text-white' : 'text-brand-100/80 hover:bg-brand-900 hover:text-white' }}">
                        <svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Access Logs
                    </a>
                @endcan

                @can('audit_logs.view')
                    <a href="{{ route('audit-logs.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('audit-logs.*') ? 'bg-brand-800 text-white' : 'text-brand-100/80 hover:bg-brand-900 hover:text-white' }}">
                        <svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                        </svg>
                        Audit Logs
                    </a>
                @endcan

                @can('system_logs.view')
                    <a href="{{ route('system-logs.index') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('system-logs.*') ? 'bg-brand-800 text-white' : 'text-brand-100/80 hover:bg-brand-900 hover:text-white' }}">
                        <svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        System Logs
                    </a>
                @endcan
            </nav>

            <div class="border-t border-brand-900 px-4 py-4">
                <div class="flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-white">{{ Auth::user()->name }}</p>
                        <p class="truncate text-xs text-brand-200/60">{{ Auth::user()->email }}</p>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-md p-2 text-brand-200/70 hover:bg-brand-900 hover:text-white" title="Logout">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- Page Content -->
        <div class="flex-1 min-w-0">
            <main class="py-8">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    @if (session('status'))
                        <div class="mb-6 rounded-lg bg-brand-50 border border-brand-200 p-4">
                            <p class="text-sm font-medium text-brand-800">{{ session('status') }}</p>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="mb-6 rounded-lg bg-red-50 border border-red-200 p-4">
                            <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                        </div>
                    @endif

                    @yield('content')
                </div>
            </main>
        </div>
    </div>
</body>
</html>

