@extends('layouts.app')

@section('title', $user->name)

@section('content')
<div class="mx-auto max-w-3xl">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ $user->name }}</h1>
        @can('users.update')
            <a href="{{ route('users.edit', $user) }}" class="text-sm font-medium text-slate-600 hover:text-slate-800">Edit</a>
        @endcan
    </div>

    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-lg p-8 mb-6">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Email</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $user->email }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Role</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium bg-brand-100 text-brand-900">
                        {{ $user->role?->name ?? 'No Role' }}
                    </span>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Status</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium
                        @if ($user->status === 'active') bg-green-100 text-green-800
                        @elseif ($user->status === 'suspended') bg-amber-100 text-amber-800
                        @else bg-red-100 text-red-800 @endif">
                        {{ ucfirst($user->status) }}
                    </span>
                </dd>
            </div>
        </dl>
    </div>

    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-lg p-8">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Activity</h2>
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Last Login</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $user->last_login_at?->format('M j, Y g:i A') ?? 'Never' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Last IP</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $user->last_login_ip ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Failed Attempts</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $user->failed_login_attempts }}</dd>
            </div>
            @if ($user->locked_until)
                <div>
                    <dt class="text-xs font-medium text-gray-500 uppercase">Locked Until</dt>
                    <dd class="mt-1 text-sm text-red-600">{{ $user->locked_until->format('M j, Y g:i A') }}</dd>
                </div>
            @endif
        </dl>
    </div>

    <div class="mt-6">
        <a href="{{ route('users.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">&larr; Back to users</a>
    </div>
</div>
@endsection

