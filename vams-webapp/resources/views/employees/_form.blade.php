@csrf

<div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
    @if ($employee->exists)
        <div>
            <label class="block text-sm font-semibold text-gray-900 mb-2">Employee Code</label>
            <input type="text" disabled value="{{ $employee->employee_code }}"
                class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 bg-gray-50 cursor-not-allowed">
            <p class="mt-1 text-xs text-gray-500">Auto-generated, cannot be changed.</p>
        </div>
    @endif

    <div>
        <label for="status" class="block text-sm font-semibold text-gray-900 mb-2">Status</label>
        <select id="status" name="status" required
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('status') border-red-500 @enderror">
            @foreach (['active' => 'Active', 'inactive' => 'Inactive'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $employee->status) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="first_name" class="block text-sm font-semibold text-gray-900 mb-2">First Name</label>
        <input id="first_name" name="first_name" type="text" required
            value="{{ old('first_name', $employee->first_name) }}"
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('first_name') border-red-500 @enderror">
        @error('first_name')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="last_name" class="block text-sm font-semibold text-gray-900 mb-2">Last Name</label>
        <input id="last_name" name="last_name" type="text" required
            value="{{ old('last_name', $employee->last_name) }}"
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('last_name') border-red-500 @enderror">
        @error('last_name')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="department" class="block text-sm font-semibold text-gray-900 mb-2">Department</label>
        <input id="department" name="department" type="text"
            value="{{ old('department', $employee->department) }}"
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('department') border-red-500 @enderror">
        @error('department')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="position" class="block text-sm font-semibold text-gray-900 mb-2">Position</label>
        <input id="position" name="position" type="text"
            value="{{ old('position', $employee->position) }}"
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('position') border-red-500 @enderror">
        @error('position')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="contact_number" class="block text-sm font-semibold text-gray-900 mb-2">Contact Number</label>
        <input id="contact_number" name="contact_number" type="text"
            value="{{ old('contact_number', $employee->contact_number) }}"
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('contact_number') border-red-500 @enderror">
        @error('contact_number')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="email" class="block text-sm font-semibold text-gray-900 mb-2">Email</label>
        <input id="email" name="email" type="email"
            value="{{ old('email', $employee->email) }}"
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('email') border-red-500 @enderror">
        @error('email')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="mt-8 flex items-center justify-end gap-4">
    <a href="{{ route('employees.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Cancel</a>
    <button type="submit" class="inline-flex justify-center rounded-lg bg-brand-700 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
        {{ $employee->exists ? 'Update Employee' : 'Create Employee' }}
    </button>
</div>
