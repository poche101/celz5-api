<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is logged in AND is an admin
        // Assuming you have an 'is_admin' column (boolean) in your users table
        if (auth()->check() && auth()->user()->is_admin) {
            return $next($request);
        }

        return response()->json(['message' => 'Access Denied: Admins Only'], 403);
    }
}
