<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAlumni
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || $request->user()->role !== 'alumni') {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Alumni access only.',
            ], 403);
        }

        return $next($request);
    }
}
