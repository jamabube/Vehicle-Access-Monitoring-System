@extends('layouts.guest')

@section('content')
<div class="flex min-h-screen items-center justify-center bg-gray-50 px-4">
    <div class="w-full max-w-md rounded-lg bg-white p-8 text-center shadow">
        <h1 class="text-3xl font-bold text-gray-900">403</h1>
        <p class="mt-2 text-gray-600">
            {{ $exception->getMessage() ?: 'You do not have permission to access this resource.' }}
        </p>
        <a href="{{ route('dashboard') }}" class="mt-6 inline-block text-sm font-medium text-slate-600 hover:text-slate-500">
            &larr; Back to dashboard
        </a>
    </div>
</div>
@endsection
