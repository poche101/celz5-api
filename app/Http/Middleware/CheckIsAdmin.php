<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckIsAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Check if user is logged in
        // 2. Check if the user's role is 'admin'
        if ($request->user() && $request->user()->role === 'admin') {
            return $next($request);
        }

        // If not admin, return a JSON error
        return response()->json([
            'message' => 'Access Denied. Admins only.'
        ], 403);
    }
}
