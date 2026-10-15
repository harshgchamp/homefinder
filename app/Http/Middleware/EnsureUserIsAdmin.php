<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $role = '1'): Response
    {
        // BEFORE the controller
        abort_unless($request->user()?->is_admin == true, 403);

        $response = $next($request);   // ← controller (and inner middleware) runs here

        // AFTER the controller (optional)
        return $response;
    }

     
}
