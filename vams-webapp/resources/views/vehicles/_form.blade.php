@csrf

<div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
    <div>
        <label for="plate_number" class="block text-sm font-semibold text-gray-900 mb-2">Plate Number</label>
        <input id="plate_number" name="plate_number" type="text" required
            value="{{ old('plate_number', $vehicle->plate_number) }}"
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('plate_number') border-red-500 @enderror">
        @error('plate_number')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="status" class="block text-sm font-semibold text-gray-900 mb-2">Status</label>
        <select id="status" name="status" required
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('status') border-red-500 @enderror">
            @foreach (['active' => 'Active', 'inactive' => 'Inactive'] as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $vehicle->status) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="vehicle_type" class="block text-sm font-semibold text-gray-900 mb-2">Vehicle Type</label>
        @php $currentType = old('vehicle_type', $vehicle->vehicle_type); @endphp
        <select id="vehicle_type" name="vehicle_type"
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('vehicle_type') border-red-500 @enderror">
            <option value="">— Select a type —</option>
            @foreach (\App\Models\Vehicle::TYPES as $type)
                <option value="{{ $type }}" @selected($currentType === $type)>{{ $type }}</option>
            @endforeach
            {{-- Keep a value saved before this list existed, so editing an older
                 record does not silently blank its type. --}}
            @if ($currentType && ! in_array($currentType, \App\Models\Vehicle::TYPES, true))
                <option value="{{ $currentType }}" selected>{{ $currentType }} (existing)</option>
            @endif
        </select>
        @error('vehicle_type')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="color" class="block text-sm font-semibold text-gray-900 mb-2">Color</label>
        {{-- A picklist that still accepts typing: no fixed list of colours can
             be complete, but the common ones should be one click away. --}}
        <input id="color" name="color" type="text" list="color-options" placeholder="Pick or type..."
            value="{{ old('color', $vehicle->color) }}"
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('color') border-red-500 @enderror">
        <datalist id="color-options">
            @foreach (\App\Models\Vehicle::COMMON_COLORS as $option)
                <option value="{{ $option }}"></option>
            @endforeach
        </datalist>
        @error('color')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="make" class="block text-sm font-semibold text-gray-900 mb-2">Make</label>
        <input id="make" name="make" type="text" list="make-options" placeholder="Pick or type..."
            value="{{ old('make', $vehicle->make) }}"
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('make') border-red-500 @enderror">
        <datalist id="make-options">
            @foreach (\App\Models\Vehicle::COMMON_MAKES as $option)
                <option value="{{ $option }}"></option>
            @endforeach
        </datalist>
        @error('make')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="model" class="block text-sm font-semibold text-gray-900 mb-2">Model</label>
        <input id="model" name="model" type="text" list="model-options" placeholder="Pick or type..."
            value="{{ old('model', $vehicle->model) }}"
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('model') border-red-500 @enderror">
        {{-- Filled by the script below: it narrows to the chosen make, and
             falls back to every known model when no make is set yet. --}}
        <datalist id="model-options"></datalist>
        @error('model')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="owner_type" class="block text-sm font-semibold text-gray-900 mb-2">Owner Type</label>
        <select id="owner_type" name="owner_type" required
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('owner_type') border-red-500 @enderror">
            @foreach (['employee' => 'Employee', 'visitor' => 'Visitor'] as $value => $label)
                <option value="{{ $value }}" @selected(old('owner_type', $vehicle->owner_type) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('owner_type')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="employee_id" class="block text-sm font-semibold text-gray-900 mb-2">Employee (owner)</label>
        <select id="employee_id" name="employee_id"
            class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('employee_id') border-red-500 @enderror">
            <option value="">— None —</option>
            @foreach ($employees as $option)
                <option value="{{ $option->id }}" @selected((int) old('employee_id', $vehicle->employee_id) === $option->id)>{{ $option->fullName() }} ({{ $option->employee_code }})</option>
            @endforeach
        </select>
        @error('employee_id')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="mt-8 flex items-center justify-end gap-4">
    <a href="{{ route('vehicles.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Cancel</a>
    <button type="submit" class="inline-flex justify-center rounded-lg bg-brand-700 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
        {{ $vehicle->exists ? 'Update Vehicle' : 'Create Vehicle' }}
    </button>
</div>

{{-- Data for resources/js/vehicle-model-suggestions.js (bundled via Vite and
     imported from app.js). A JSON script tag -- not a JS one -- so the app's
     CSP (script-src 'self') does not block it: the browser never executes
     application/json as script. --}}
<script type="application/json" id="vehicle-models-data">@json(\App\Models\Vehicle::COMMON_MODELS)</script>
