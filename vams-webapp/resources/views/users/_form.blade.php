@csrf

<div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
    <div>
        <label for="name" class="block text-sm font-semibold text-gray-900 mb-2">Full Name</label>
        <input id="name" name="name" type="text" required
            value="{{ old('name', $user->name) }}"
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('name') border-red-500 @enderror">
        @error('name')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="email" class="block text-sm font-semibold text-gray-900 mb-2">Email Address</label>
        <input id="email" name="email" type="email" required
            value="{{ old('email', $user->email) }}"
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('email') border-red-500 @enderror">
        @error('email')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="password" class="block text-sm font-semibold text-gray-900 mb-2">
            Password @if (! $user->exists) <span class="text-red-500">*</span> @else <span class="font-normal text-gray-500">(leave blank to keep current)</span> @endif
        </label>
        <input id="password" name="password" type="password"
            @if (! $user->exists) required @endif
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('password') border-red-500 @enderror">
        @error('password')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="password_confirmation" class="block text-sm font-semibold text-gray-900 mb-2">
            Confirm Password @if (! $user->exists) <span class="text-red-500">*</span> @endif
        </label>
        <input id="password_confirmation" name="password_confirmation" type="password"
            @if (! $user->exists) required @endif
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
    </div>

    <div>
        <label for="role_id" class="block text-sm font-semibold text-gray-900 mb-2">Role</label>
        <select id="role_id" name="role_id" required
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('role_id') border-red-500 @enderror">
            <option value="">Select a role</option>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" @selected(old('role_id', $user->role_id) == $role->id)>{{ $role->name }}</option>
            @endforeach
        </select>
        @error('role_id')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="status" class="block text-sm font-semibold text-gray-900 mb-2">Status</label>
        <select id="status" name="status" required
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('status') border-red-500 @enderror">
            <option value="active" @selected(old('status', $user->status ?? 'active') === 'active')>Active</option>
            <option value="suspended" @selected(old('status', $user->status) === 'suspended')>Suspended</option>
            <option value="locked" @selected(old('status', $user->status) === 'locked')>Locked</option>
        </select>
        @error('status')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="mt-8 flex items-center justify-end gap-4">
    <a href="{{ $user->exists ? route('users.show', $user) : route('users.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Cancel</a>
    <button type="submit" class="inline-flex justify-center rounded-lg bg-brand-700 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
        {{ $user->exists ? 'Update User' : 'Create User' }}
    </button>
</div>
