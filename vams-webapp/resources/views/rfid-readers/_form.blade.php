@csrf

<div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
    @if ($rfidReader->exists)
        <div>
            <label class="block text-sm font-semibold text-gray-900 mb-2">Device Code</label>
            <input type="text" disabled value="{{ $rfidReader->device_code }}"
                class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 bg-gray-50 cursor-not-allowed font-mono">
            <p class="mt-1 text-xs text-gray-500">Auto-generated, cannot be changed.</p>
        </div>
    @endif

    <div>
        <label for="device_name" class="block text-sm font-semibold text-gray-900 mb-2">Device Name</label>
        <input id="device_name" name="device_name" type="text" required placeholder="Main Gate Reader"
            value="{{ old('device_name', $rfidReader->device_name) }}"
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('device_name') border-red-500 @enderror">
        @error('device_name')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="model" class="block text-sm font-semibold text-gray-900 mb-2">Model</label>
        <input id="model" name="model" type="text"
            value="{{ old('model', $rfidReader->model ?: 'S4A UHF-202415') }}"
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('model') border-red-500 @enderror">
        @error('model')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="location" class="block text-sm font-semibold text-gray-900 mb-2">Location</label>
        <input id="location" name="location" type="text" placeholder="Main Gate"
            value="{{ old('location', $rfidReader->location) }}"
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('location') border-red-500 @enderror">
        @error('location')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="ip_address" class="block text-sm font-semibold text-gray-900 mb-2">IP Address</label>
        <input id="ip_address" name="ip_address" type="text" placeholder="192.168.1.10"
            value="{{ old('ip_address', $rfidReader->ip_address) }}"
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('ip_address') border-red-500 @enderror">
        @error('ip_address')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="status" class="block text-sm font-semibold text-gray-900 mb-2">Status</label>
        <select id="status" name="status" required
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('status') border-red-500 @enderror">
            @foreach (['online' => 'Online', 'offline' => 'Offline', 'disabled' => 'Disabled'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $rfidReader->status) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>

@unless ($rfidReader->exists)
    <p class="mt-4 text-sm text-gray-500">The device API key and secret will be generated automatically after creation and shown once — save them securely for the RFID Listener/Device Service configuration.</p>
@endunless

<div class="mt-8 flex items-center justify-end gap-4">
    <a href="{{ route('rfid-readers.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Cancel</a>
    <button type="submit" class="inline-flex justify-center rounded-lg bg-brand-700 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
        {{ $rfidReader->exists ? 'Update Reader' : 'Create Reader' }}
    </button>
</div>
