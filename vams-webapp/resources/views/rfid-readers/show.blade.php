@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ $rfidReader->device_name }}</h1>
        @can('rfid_readers.update')
            <a href="{{ route('rfid-readers.edit', $rfidReader) }}" class="text-sm font-medium text-slate-600 hover:text-slate-800">Edit</a>
        @endcan
    </div>

    @if (session('plain_api_secret'))
        <div class="mb-6 rounded-md bg-amber-50 border border-amber-300 p-4">
            <p class="text-sm font-semibold text-amber-900 mb-2">Save these device credentials now — the secret will not be shown again.</p>
            <p class="text-sm text-amber-800">API Key: <span class="font-mono">{{ $rfidReader->api_key }}</span></p>
            <p class="text-sm text-amber-800">API Secret: <span class="font-mono">{{ session('plain_api_secret') }}</span></p>
        </div>
    @endif

    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-lg p-8">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Device Code</dt>
                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $rfidReader->device_code }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Model</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $rfidReader->model ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Location</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $rfidReader->location ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">IP Address</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $rfidReader->ip_address ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Status</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ ucfirst($rfidReader->status) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Last Heartbeat</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ $rfidReader->last_heartbeat_at?->format('M j, Y g:i A') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">API Key</dt>
                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $rfidReader->api_key }}</dd>
            </div>
        </dl>
    </div>

    @can('rfid_readers.update')
        <div class="mt-6 bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-lg p-8">
            <h2 class="text-lg font-semibold text-gray-900 mb-2">Credentials</h2>
            <p class="text-sm text-gray-500 mb-4">Regenerating replaces the API secret immediately. The device service must be reconfigured with the new credentials afterward.</p>
            <form method="POST" action="{{ route('rfid-readers.regenerate-credentials', $rfidReader) }}" data-confirm="Regenerate credentials? The current API secret will stop working immediately.">
                @csrf
                <button type="submit" class="inline-flex justify-center rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                    Regenerate Credentials
                </button>
            </form>
        </div>
    @endcan

    <div class="mt-6">
        <a href="{{ route('rfid-readers.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">&larr; Back to RFID readers</a>
    </div>
</div>
@endsection
