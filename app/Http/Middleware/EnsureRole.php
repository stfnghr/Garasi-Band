<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi route untuk role tertentu. User juga harus sudah disetujui admin.
 * Pemakaian: ->middleware('role:admin') atau ->middleware('role:user').
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== UserRole::from($role)) {
            abort(403, 'You do not have access to this page.');
        }

        if (! $user->isAdmin() && ! $user->isApproved()) {
            Auth::logout();

            return redirect()->route('login')->withErrors(['email' => 'Your account has not been approved by the admin yet.']);
        }

        return $next($request);
    }
}
