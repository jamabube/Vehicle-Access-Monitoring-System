@extends('layouts.app')

@section('title', 'Users')

@section('content')
<div class="mx-auto max-w-6xl">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Users</h1>
        @can('users.create')
            <a href="{{ route('users.create') }}" class="inline-flex items-center rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                + Add User
            </a>
        @endcan
    </div>

    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-lg p-4 mb-6">
        <form method="GET" action="{{ route('users.index') }}" class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[200px]">
                <label for="search" class="block text-xs font-medium text-gray-500 mb-1">Search</label>
                <input id="search" name="search" type="text" value="{{ request('search') }}" placeholder="Name or email..."
                    class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>
            <div>
                <label for="status" class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                <select id="status" name="status" class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                    <option value="">All</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="suspended" @selected(request('status') === 'suspended')>Suspended</option>
                    <option value="locked" @selected(request('status') === 'locked')>Locked</option>
                </select>
            </div>
            <div>
                <label for="role" class="block text-xs font-medium text-gray-500 mb-1">Role</label>
                <select id="role" name="role" class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                    <option value="">All</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" @selected((string) request('role') === (string) $role->id)>{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-900">Filter</button>
            <a href="{{ route('users.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700">Reset</a>
        </form>
    </div>

    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Email</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Role</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($users as $user)
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900 font-medium">{{ $user->name }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $user->email }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium bg-brand-100 text-brand-900">
                                {{ $user->role?->name ?? 'No Role' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium
                                @if ($user->status === 'active') bg-green-100 text-green-800
                                @elseif ($user->status === 'suspended') bg-amber-100 text-amber-800
                                @else bg-red-100 text-red-800 @endif">
                                {{ ucfirst($user->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-sm space-x-3">
                            <a href="{{ route('users.show', $user) }}" class="font-medium text-brand-700 hover:text-brand-900">View</a>
                            @can('users.update')
                                <a href="{{ route('users.edit', $user) }}" class="font-medium text-slate-600 hover:text-slate-800">Edit</a>
                            @endcan
                            @can('users.delete')
                                @if ($user->id !== auth()->id())
                                    <form method="POST" action="{{ route('users.destroy', $user) }}" class="inline" data-confirm="Delete this user?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="font-medium text-red-600 hover:text-red-800">Delete</button>
                                    </form>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">No users found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $users->links() }}
    </div>
</div>
@endsection
