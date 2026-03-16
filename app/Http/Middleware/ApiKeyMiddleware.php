<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ApiKeyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->header('X-API-Key');

        // Check if API key is present
        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'API key is missing',
                'data' => []
            ], 401);
        }

        // Check if API key is valid
        if ($apiKey !== env('API_KEY')) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid API key',
                'data' => []
            ], 401);
        }

        return $next($request);
    }
}
