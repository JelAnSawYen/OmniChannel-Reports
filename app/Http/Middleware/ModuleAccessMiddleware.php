<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ModuleAccessMiddleware
{
    public function handle(Request $request, Closure $next, string $module)
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        if (! $request->user()->canAccessModule($module)) {
            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}
