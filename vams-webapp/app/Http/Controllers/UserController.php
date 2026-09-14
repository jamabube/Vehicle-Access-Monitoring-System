<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * FINDING #6: User management UI.
 */
class UserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:users.view', only: ['index', 'show']),
            new Middleware('can:users.create', only: ['create', 'store']),
            new Middleware('can:users.update', only: ['edit', 'update']),
            new Middleware('can:users.delete', only: ['destroy']),
        ];
    }

    public function index(): View
    {
        $users = User::query()
            ->with('role')
            ->when(request('search'), fn ($q, $search) => $q->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when(request('status'), fn ($q, $status) => $q->where('status', $status))
            ->when(request('role'), fn ($q, $roleId) => $q->where('role_id', $roleId))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $roles = Role::orderBy('name')->get();

        return view('users.index', compact('users', 'roles'));
    }

    public function create(): View
    {
        $roles = Role::orderBy('name')->get();

        return view('users.create', ['user' => new User, 'roles' => $roles]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);

        AuditLog::record('user.created', $user, $request, $request->safe()->except('password', 'password_confirmation'));

        return redirect()->route('users.show', $user)->with('status', 'User created successfully.');
    }

    public function show(User $user): View
    {
        $user->load('role', 'auditLogs');

        return view('users.show', compact('user'));
    }

    public function edit(User $user): View
    {
        $roles = Role::orderBy('name')->get();

        return view('users.edit', compact('user', 'roles'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        // Only hash password if provided
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        AuditLog::record('user.updated', $user, $request, $request->safe()->except('password', 'password_confirmation', 'current_password'));

        return redirect()->route('users.show', $user)->with('status', 'User updated successfully.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        // Prevent self-deletion
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        AuditLog::record('user.deleted', $user, $request, [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->name,
        ]);

        $user->delete();

        return redirect()->route('users.index')->with('status', 'User deleted successfully.');
    }
}
