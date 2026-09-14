@csrf

<div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
    <div>
        <label for="first_name" class="block text-sm font-semibold text-gray-900 mb-2">First Name</label>
        <input id="first_name" name="first_name" type="text" required
            value="{{ old('first_name', $visitor->first_name) }}"
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('first_name') border-red-500 @enderror">
        @error('first_name')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="last_name" class="block text-sm font-semibold text-gray-900 mb-2">Last Name</label>
        <input id="last_name" name="last_name" type="text" required
            value="{{ old('last_name', $visitor->last_name) }}"
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('last_name') border-red-500 @enderror">
        @error('last_name')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="contact_number" class="block text-sm font-semibold text-gray-900 mb-2">Contact Number</label>
        <input id="contact_number" name="contact_number" type="text"
            value="{{ old('contact_number', $visitor->contact_number) }}"
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('contact_number') border-red-500 @enderror">
        @error('contact_number')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="valid_id_type" class="block text-sm font-semibold text-gray-900 mb-2">Valid ID Type</label>
        <input id="valid_id_type" name="valid_id_type" type="text" placeholder="e.g. Driver License, Passport"
            value="{{ old('valid_id_type', $visitor->valid_id_type) }}"
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('valid_id_type') border-red-500 @enderror">
        @error('valid_id_type')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="valid_id_number" class="block text-sm font-semibold text-gray-900 mb-2">Valid ID Number</label>
        <input id="valid_id_number" name="valid_id_number" type="text"
            value="{{ old('valid_id_number', $visitor->valid_id_number) }}"
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('valid_id_number') border-red-500 @enderror">
        @error('valid_id_number')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="address" class="block text-sm font-semibold text-gray-900 mb-2">Address</label>
        <input id="address" name="address" type="text"
            value="{{ old('address', $visitor->address) }}"
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('address') border-red-500 @enderror">
        @error('address')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="mt-8 flex items-center justify-end gap-4">
    <a href="{{ route('visitors.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Cancel</a>
    <button type="submit" class="inline-flex justify-center rounded-lg bg-brand-700 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
        {{ $visitor->exists ? 'Update Visitor' : 'Create Visitor' }}
    </button>
</div>
