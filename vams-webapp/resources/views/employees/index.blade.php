@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-6xl">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Employees</h1>
        @can('employees.create')
            <a href="{{ route('employees.create') }}" class="inline-flex items-center rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">
                + Add Employee
            </a>
        @endcan
    </div>

    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-lg p-4 mb-6">
        <form method="GET" action="{{ route('employees.index') }}" class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[200px]">
                <label for="search" class="block text-xs font-medium text-gray-500 mb-1">Search</label>
                <input id="search" name="search" type="text" value="{{ request('search') }}" placeholder="Code, name, department..."
                    class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>
            <div>
                <label for="status" class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                <select id="status" name="status" class="block w-full px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                    <option value="">All</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                </select>
            </div>
            <button type="submit" class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-900">Filter</button>
            <a href="{{ route('employees.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700">Reset</a>
        </form>
    </div>

    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Code</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Name</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Department</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($employees as $employee)
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">{{ $employee->employee_code }}</td>
                        <td class="px-4 py-3 text-sm text-gray-900">{{ $employee->fullName() }}</td>
                        <td class="px-4 py-3 text-sm text-gray-500">{{ $employee->department ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $employee->isActive() ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                {{ ucfirst($employee->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right text-sm space-x-3">
                            <a href="{{ route('employees.show', $employee) }}" class="font-medium text-brand-700 hover:text-brand-900">View</a>
                            @can('employees.update')
                                <a href="{{ route('employees.edit', $employee) }}" class="font-medium text-slate-600 hover:text-slate-800">Edit</a>
                            @endcan
                            @can('employees.delete')
                                <form method="POST" action="{{ route('employees.destroy', $employee) }}" class="inline" data-confirm="Delete this employee?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="font-medium text-red-600 hover:text-red-800">Delete</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">No employees found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">
        {{ $employees->links() }}
    </div>
</div>
@endsection
