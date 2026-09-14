@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Edit RFID Assignment</h1>

    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-lg p-8">
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 mb-6">
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">RFID Tag</dt>
                <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $rfidAssignment->rfidTag?->epc ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase">Assigned To</dt>
                <dd class="mt-1 text-sm text-gray-900">
                    @if ($rfidAssignment->vehicle)
                        Vehicle {{ $rfidAssignment->vehicle->plate_number }}
                    @elseif ($rfidAssignment->visitorVisit)
                        Visit — {{ $rfidAssignment->visitorVisit->visitor?->fullName() ?? 'Visitor' }}
                    @else
                        —
                    @endif
                </dd>
            </div>
        </dl>

        <p class="text-sm text-gray-500 mb-6">The tag and its target cannot be changed here — release this assignment and create a new one instead. Only the assigned-at timestamp can be corrected.</p>

        <form method="POST" action="{{ route('rfid-assignments.update', $rfidAssignment) }}">
            @csrf
            @method('PUT')

            <div>
                <label for="assigned_at" class="block text-sm font-semibold text-gray-900 mb-2">Assigned At</label>
                <input id="assigned_at" name="assigned_at" type="datetime-local" required
                    value="{{ old('assigned_at', optional($rfidAssignment->assigned_at)->format('Y-m-d\TH:i')) }}"
                    class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500 @error('assigned_at') border-red-500 @enderror">
                @error('assigned_at')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="mt-8 flex items-center justify-end gap-4">
                <a href="{{ route('rfid-assignments.index') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">Cancel</a>
                <button type="submit" class="inline-flex justify-center rounded-lg bg-brand-700 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                    Update Assignment
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
