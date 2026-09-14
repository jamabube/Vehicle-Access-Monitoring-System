<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    /**
     * Handle an incoming request.
     *
     * Guards against a session staying valid after an administrator
     * suspends the account or the account becomes locked while the
     * user still has an active browser session.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (! $user->isActive() || $user->isLocked())) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => $user->isLocked()
                    ? __('Your account has been locked due to too many failed login attempts. Please try again later.')
                    : __('Your account has been suspended. Please contact an administrator.'),
            ]);
        }

        return $next($request);
    }
}
