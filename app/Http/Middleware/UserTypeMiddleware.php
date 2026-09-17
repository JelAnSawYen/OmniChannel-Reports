<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UserTypeMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$userTypes): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        if (! $user->userType) {
            abort(403, 'Your account does not have a user type assigned.');
        }

        if (! in_array($user->userType->name, $userTypes)) {
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
