@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Check In Visitor</h1>

    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-lg p-8">
        <form method="POST" action="{{ route('visitor-visits.store') }}">
            @csrf

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <div>
                    <label for="visitor_id" class="block text-sm font-semibold text-gray-900 mb-2">Visitor</label>
                    <select id="visitor_id" name="visitor_id" required
                        class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('visitor_id') border-red-500 @enderror">
                        <option value="">— Select a visitor —</option>
                        @foreach ($visitors as $visitor)
                            <option value="{{ $visitor->id }}" @selected((int) old('visitor_id') === $visitor->id)>{{ $visitor->fullName() }}</option>
                        @endforeach
                    </select>
                    @error('visitor_id')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500">Visitor not listed? <a href="{{ route('visitors.create') }}" class="text-brand-700 hover:text-brand-900">Register them first</a>.</p>
                </div>

                <div>
                    <label for="host_name" class="block text-sm font-semibold text-gray-900 mb-2">Host / Department</label>
                    <input id="host_name" name="host_name" type="text"
                        value="{{ old('host_name') }}"
                        class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('host_name') border-red-500 @enderror">
                    @error('host_name')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="purpose" class="block text-sm font-semibold text-gray-900 mb-2">Purpose</label>
                    <input id="purpose" name="purpose" type="text" placeholder="e.g. Delivery, Meeting"
                        value="{{ old('purpose') }}"
                        class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('purpose') border-red-500 @enderror">
                    @error('purpose')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="vehicle_id" class="block text-sm font-semibold text-gray-900 mb-2">Vehicle (optional)</label>
                    <select id="vehicle_id" name="vehicle_id"
                        class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('vehicle_id') border-red-500 @enderror">
                        <option value="">— None —</option>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}" @selected((int) old('vehicle_id') === $vehicle->id)>{{ $vehicle->plate_number }}</option>
                        @endforeach
                    </select>
                    @error('vehicle_id')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="rfid_tag_id" class="block text-sm font-semibold text-gray-900 mb-2">RFID Card (optional)</label>
                    <select id="rfid_tag_id" name="rfid_tag_id"
                        class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('rfid_tag_id') border-red-500 @enderror">
                        <option value="">— None —</option>
                        @foreach ($rfidCards as $card)
                            <option value="{{ $card->id }}" @selected((int) old('rfid_tag_id') === $card->id)>{{ $card->epc }}</option>
                        @endforeach
                    </select>
                    @error('rfid_tag_id')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500">Only unassigned, active cards are listed.</p>
                </div>

                <div>
                    <label for="valid_from" class="block text-sm font-semibold text-gray-900 mb-2">Valid From</label>
                    <input id="valid_from" name="valid_from" type="datetime-local" required
                        value="{{ old('valid_from', now()->format('Y-m-d\TH:i')) }}"
                        class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('valid_from') border-red-500 @enderror">
                    @error('valid_from')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="valid_until" class="block text-sm font-semibold text-gray-900 mb-2">Valid Until</label>
                    <input id="valid_until" name="valid_until" type="datetime-local" required
                        value="{{ old('valid_until', now()->addHours(8)->format('Y-m-d\TH:i')) }}"
                        class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('valid_until') border-red-500 @enderror">
                    @error('valid_until')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-8 flex items-center justify-end gap-4">
                <a href="{{ route('visitor-visits.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Cancel</a>
                <button type="submit" class="inline-flex justify-center rounded-lg bg-brand-700 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                    Check In Visitor
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
