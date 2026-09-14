@csrf

@can('rfid_tags.create')
    {{-- Scan helper: fills the EPC field from whatever the reader just saw, so
         staff never have to read a tag code off a terminal and retype it. --}}
    <div class="mb-6 rounded-lg border border-brand-200 bg-brand-50 p-4"
        data-scan-panel data-scan-url="{{ route('rfid-tags.recent-scans') }}">

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-brand-900">Scan a tag</p>
                <p class="text-sm text-brand-800">Hold the sticker or card in front of the reader, then pick it below.</p>
            </div>
            <button type="button" data-scan-toggle
                class="inline-flex items-center rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800 focus:outline-none focus:ring-2 focus:ring-brand-500">
                Start scanning
            </button>
        </div>

        <div data-scan-body hidden class="mt-4">
            <p data-scan-status class="text-sm text-brand-800">Waiting for a tag…</p>
            <ul data-scan-results class="mt-3 space-y-2"></ul>
            <p class="mt-3 text-xs text-brand-700">
                Nothing appearing? The RFID listener must be running — it is what sends reads to this page.
            </p>
        </div>
    </div>
@endcan

<div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
    <div>
        <label for="epc" class="block text-sm font-semibold text-gray-900 mb-2">EPC (tag code)</label>
        <input id="epc" name="epc" type="text" required
            value="{{ old('epc', $rfidTag->epc) }}"
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('epc') border-red-500 @enderror">
        @error('epc')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="credential_type" class="block text-sm font-semibold text-gray-900 mb-2">Credential Type</label>
        <select id="credential_type" name="credential_type" required
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('credential_type') border-red-500 @enderror">
            @foreach (['sticker' => 'Sticker (permanent, employee vehicle)', 'card' => 'Card (temporary, visitor)'] as $value => $label)
                <option value="{{ $value }}" @selected(old('credential_type', $rfidTag->credential_type) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('credential_type')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="status" class="block text-sm font-semibold text-gray-900 mb-2">Status</label>
        <select id="status" name="status" required
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('status') border-red-500 @enderror">
            @foreach (['active' => 'Active', 'inactive' => 'Inactive', 'lost' => 'Lost', 'disabled' => 'Disabled', 'expired' => 'Expired'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $rfidTag->status) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="issued_at" class="block text-sm font-semibold text-gray-900 mb-2">Issued At</label>
        <input id="issued_at" name="issued_at" type="datetime-local"
            value="{{ old('issued_at', optional($rfidTag->issued_at)->format('Y-m-d\TH:i')) }}"
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('issued_at') border-red-500 @enderror">
        @error('issued_at')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="expires_at" class="block text-sm font-semibold text-gray-900 mb-2">Expires At</label>
        <input id="expires_at" name="expires_at" type="datetime-local"
            value="{{ old('expires_at', optional($rfidTag->expires_at)->format('Y-m-d\TH:i')) }}"
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('expires_at') border-red-500 @enderror">
        @error('expires_at')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="sm:col-span-2">
        <label for="notes" class="block text-sm font-semibold text-gray-900 mb-2">Notes</label>
        <textarea id="notes" name="notes" rows="3"
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('notes') border-red-500 @enderror">{{ old('notes', $rfidTag->notes) }}</textarea>
        @error('notes')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="mt-8 flex items-center justify-end gap-4">
    <a href="{{ route('rfid-tags.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Cancel</a>
    <button type="submit" class="inline-flex justify-center rounded-lg bg-brand-700 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
        {{ $rfidTag->exists ? 'Update RFID Tag' : 'Create RFID Tag' }}
    </button>
</div>

{{-- The scan panel's behaviour lives in resources/js/rfid-tag-scan.js
     (bundled via Vite and imported from app.js) instead of an inline
     <script>, because the app's CSP (script-src 'self') blocks inline
     scripts. --}}
