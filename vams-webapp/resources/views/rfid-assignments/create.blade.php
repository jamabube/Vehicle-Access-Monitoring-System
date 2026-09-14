@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Add RFID Assignment</h1>

    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-lg p-8">
        <form method="POST" action="{{ route('rfid-assignments.store') }}">
            @csrf

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                    <label for="rfid_tag_id" class="block text-sm font-semibold text-gray-900 mb-2">RFID Tag</label>
                    <select id="rfid_tag_id" name="rfid_tag_id" required
                        class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('rfid_tag_id') border-red-500 @enderror">
                        <option value="">— Select a tag —</option>
                        @foreach ($rfidTags as $tag)
                            <option value="{{ $tag->id }}" @selected((int) old('rfid_tag_id') === $tag->id)>{{ $tag->tag_code }} — {{ ucfirst($tag->credential_type) }} ({{ $tag->epc }})</option>
                        @endforeach
                    </select>
                    @error('rfid_tag_id')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    @if ($rfidTags->isEmpty())
                        <p class="mt-2 text-sm text-amber-700">
                            Every active tag is already assigned. Release an existing assignment, or
                            <a href="{{ route('rfid-tags.create') }}" class="font-semibold underline">register a new tag</a> first.
                        </p>
                    @else
                        <p class="mt-2 text-xs text-gray-500">Only unassigned tags are listed, so a tag cannot be assigned twice.</p>
                    @endif
                </div>

                <div>
                    <label for="assigned_at" class="block text-sm font-semibold text-gray-900 mb-2">Assigned At</label>
                    <input id="assigned_at" name="assigned_at" type="datetime-local" required
                        value="{{ old('assigned_at', now()->format('Y-m-d\TH:i')) }}"
                        class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('assigned_at') border-red-500 @enderror">
                    @error('assigned_at')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="vehicle_id" class="block text-sm font-semibold text-gray-900 mb-2">Vehicle (sticker)</label>
                    <select id="vehicle_id" name="vehicle_id"
                        class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('vehicle_id') border-red-500 @enderror">
                        <option value="">— None —</option>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}" @selected((int) old('vehicle_id') === $vehicle->id)>{{ $vehicle->plate_number }}@if ($vehicle->employee) — {{ $vehicle->employee->fullName() }}@endif</option>
                        @endforeach
                    </select>
                    @error('vehicle_id')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    @if ($vehicles->isEmpty())
                        <p class="mt-2 text-sm text-amber-700">
                            Every active vehicle already has a sticker. Release one first, or
                            <a href="{{ route('vehicles.create') }}" class="font-semibold underline">register a new vehicle</a>.
                        </p>
                    @else
                        <p class="mt-2 text-xs text-gray-500">Vehicles that already carry a sticker are not listed.</p>
                    @endif
                </div>

                <div>
                    <label for="visitor_visit_id" class="block text-sm font-semibold text-gray-900 mb-2">Visitor Visit (card)</label>
                    <select id="visitor_visit_id" name="visitor_visit_id"
                        class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('visitor_visit_id') border-red-500 @enderror">
                        <option value="">— None —</option>
                        @foreach ($visitorVisits as $visit)
                            <option value="{{ $visit->id }}" @selected((int) old('visitor_visit_id') === $visit->id)>#{{ $visit->id }} — {{ $visit->visitor?->fullName() ?? 'Visitor' }}</option>
                        @endforeach
                    </select>
                    @error('visitor_visit_id')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-2 text-xs text-gray-500">Only active visits that do not already hold a card are listed.</p>
                </div>
            </div>

            <p class="mt-4 text-sm text-gray-500">Select exactly one target: a Vehicle for a permanent sticker, or a Visitor Visit for a temporary card.</p>

            <div class="mt-8 flex items-center justify-end gap-4">
                <a href="{{ route('rfid-assignments.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Cancel</a>
                <button type="submit" class="inline-flex justify-center rounded-lg bg-brand-700 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                    Create Assignment
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
