<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMfaCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->requiresMfa()) {
            return $next($request);
        }

        if ($request->session()->get('mfa_passed') === $user->id) {
            return $next($request);
        }

        if (! $user->mfa_confirmed_at) {
            return redirect()->route('mfa.setup');
        }

        return redirect()->route('mfa.challenge');
    }
}
