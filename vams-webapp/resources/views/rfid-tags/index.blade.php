@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-6xl">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">RFID Tags</h1>
        @can('rfid_tags.create')
            <a href="{{ route('rfid-tags.create') }}" class="inline-flex items-center rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                + Add RFID Tag
            </a>
        @endcan
    </div>

    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-lg p-4 mb-6">
        <form method="GET" action="{{ route('rfid-tags.index') }}" class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[200px]">
                <label for="search" class="block text-xs font-medium text-gray-500 mb-1">Search</label>
                <input id="search" name="search" type="text" value="{{ request('search') }}" placeholder="Tag code or EPC..."
                    class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>
            <div>
                <label for="credential_type" class="block text-xs font-medium text-gray-500 mb-1">Credential Type</label>
                <select id="credential_type" name="credential_type" class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                    <option value="">All</option>
                    <option value="sticker" @selected(request('credential_type') === 'sticker')>Sticker</option>
                    <option value="card" @selected(request('credential_type') === 'card')>Card</option>
                </select>
            </div>
            <div>
                <label for="status" class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                <select id="status" name="status" class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                    <option value="">All</option>
                    @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'lost' => 'Lost', 'disabled' => 'Disabled', 'expired' => 'Expired'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-900">Filter</button>
            <a href="{{ route('rfid-tags.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700">Reset</a>
        </form>
    </div>

    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Tag</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">EPC</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Issued</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Expires</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($rfidTags as $rfidTag)
                    <tr>
                        {{-- The tag code is what is written on the physical sticker, so it
                             leads the row; the EPC is the machine identifier and sits beside it. --}}
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="inline-flex items-center rounded-md bg-brand-50 px-2.5 py-1 text-sm font-bold text-brand-800 ring-1 ring-inset ring-brand-800/10">
                                {{ $rfidTag->tag_code }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500 font-mono">{{ $rfidTag->epc }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ ucfirst($rfidTag->credential_type) }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $rfidTag->isActive() ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                {{ ucfirst($rfidTag->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $rfidTag->issued_at?->format('M j, Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $rfidTag->expires_at?->format('M j, Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right text-sm space-x-3">
                            <a href="{{ route('rfid-tags.show', $rfidTag) }}" class="font-medium text-brand-700 hover:text-brand-900">View</a>
                            @can('rfid_tags.update')
                                <a href="{{ route('rfid-tags.edit', $rfidTag) }}" class="font-medium text-slate-600 hover:text-slate-800">Edit</a>
                            @endcan
                            @can('rfid_tags.delete')
                                <form method="POST" action="{{ route('rfid-tags.destroy', $rfidTag) }}" class="inline" data-confirm="Delete this RFID tag?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="font-medium text-red-600 hover:text-red-800">Delete</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-500">No RFID tags found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $rfidTags->links() }}
    </div>
</div>
@endsection
