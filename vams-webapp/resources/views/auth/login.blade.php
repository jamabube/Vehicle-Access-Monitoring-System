@extends('layouts.guest')

@section('content')
<div class="flex min-h-screen">
    <!-- Left Panel - Branding -->
    <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-brand-800 via-brand-900 to-brand-950 p-12 flex-col justify-between text-white relative overflow-hidden">
        <div class="absolute inset-0 opacity-10">
            <svg class="w-full h-full" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                        <path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="1"/>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#grid)" />
            </svg>
        </div>
        <div class="relative z-10">
            <div class="flex items-center space-x-3 mb-8">
                <img src="{{ asset('images/logo.svg') }}" alt="Logo" class="h-12 w-12">
                <div>
                    <h1 class="text-2xl font-bold">{{ env('APP_ORGANIZATION', 'Organization') }}</h1>
                    <p class="text-brand-200 text-sm">Vehicle Access Monitoring System</p>
                </div>
            </div>
            <div class="mt-16 space-y-6">
                <div class="flex items-start space-x-4">
                    <div class="bg-white/10 rounded-lg p-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-lg">Secure Access Control</h3>
                        <p class="text-brand-100 text-sm mt-1">RFID-based vehicle tracking and monitoring</p>
                    </div>
                </div>
                <div class="flex items-start space-x-4">
                    <div class="bg-white/10 rounded-lg p-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-lg">Real-time Monitoring</h3>
                        <p class="text-brand-100 text-sm mt-1">Track entries with audit logs</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="relative z-10 text-brand-200 text-sm">
            <p>&copy; {{ date('Y') }} {{ env('APP_ORGANIZATION', 'Organization') }}</p>
        </div>
    </div>

    <!-- Right Panel - Login Form -->
    <div class="w-full lg:w-1/2 flex items-center justify-center p-8 bg-gray-50">
        <div class="w-full max-w-md">
            <div class="lg:hidden flex flex-col items-center mb-8">
                <img src="{{ asset('images/logo.svg') }}" alt="{{ env('APP_ORGANIZATION', 'Organization') }}" class="h-16 w-16 mb-4">
                <h2 class="text-2xl font-bold text-gray-900">{{ env('APP_ORGANIZATION', 'Organization') }}</h2>
                <p class="text-sm text-gray-600 mt-1">Vehicle Access Monitoring System</p>
            </div>

            <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                <div class="mb-8">
                    <h3 class="text-2xl font-bold text-gray-900">Welcome back</h3>
                    <p class="text-gray-600 mt-2">Sign in to your account to continue</p>
                </div>

                @if (session('status'))
                    <div class="mb-6 rounded-lg bg-green-50 p-4 border border-green-200">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-green-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-sm text-green-800 font-medium">{{ session('status') }}</span>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="space-y-6">
                    @csrf

                    <div>
                        <label for="email" class="block text-sm font-semibold text-gray-900 mb-2">Email address</label>
                        <input id="email" name="email" type="email" autocomplete="email" required autofocus
                            value="{{ old('email') }}"
                            class="block w-full px-3 py-3 border border-gray-300 rounded-lg text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent @error('email') border-red-500 @enderror"
                            placeholder="you@example.com">
                        @error('email')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-semibold text-gray-900 mb-2">Password</label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required
                            class="block w-full px-3 py-3 border border-gray-300 rounded-lg text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-transparent @error('password') border-red-500 @enderror"
                            placeholder="Enter your password">
                        @error('password')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center">
                            <input id="remember" name="remember" type="checkbox"
                                class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 cursor-pointer">
                            <label for="remember" class="ml-2 block text-sm text-gray-700 cursor-pointer select-none">
                                Keep me signed in
                            </label>
                        </div>
                        <a href="{{ route('password.request') }}" class="text-sm font-medium text-brand-700 hover:text-brand-800">
                            Forgot password?
                        </a>
                    </div>

                    <button type="submit"
                        class="w-full flex justify-center items-center py-3 px-4 rounded-lg text-sm font-semibold text-white bg-brand-700 hover:bg-brand-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 transition-all">
                        Sign in to dashboard
                    </button>
                </form>

                <div class="mt-6 pt-6 border-t border-gray-200">
                    <p class="text-xs text-center text-gray-500">Secure access powered by RFID technology</p>
                </div>
            </div>

            <p class="mt-8 text-center text-xs text-gray-500">
                Having trouble signing in? Contact your system administrator.
            </p>
        </div>
    </div>
</div>
@endsection


