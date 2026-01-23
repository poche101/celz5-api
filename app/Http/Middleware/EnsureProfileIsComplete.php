<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileIsComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Check if the user is authenticated and has filled the required fields
        if ($user && (empty($user->church) || empty($user->group) || empty($user->cell))) {
            return response()->json([
                'status' => 'profile_incomplete',
                'message' => 'Please update your profile details (Church, Group, Cell) before making a payment.',
                'user' => $user
            ], 403);
        }

        return $next($request);
    }
}
