@extends('layouts.app')

@section('title', 'Create User')

@section('content')
<div class="mx-auto max-w-3xl">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Add User</h1>

    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-lg p-8">
        <form method="POST" action="{{ route('users.store') }}">
            @include('users._form')
        </form>
    </div>
</div>
@endsection

