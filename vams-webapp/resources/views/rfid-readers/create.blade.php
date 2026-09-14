@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Add RFID Reader</h1>

    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-lg p-8">
        <form method="POST" action="{{ route('rfid-readers.store') }}">
            @include('rfid-readers._form')
        </form>
    </div>
</div>
@endsection
