<?php

namespace App\Http\Middleware;

use App\Models\Marketplace_model;
use Closure;
use Illuminate\Http\Request;

class ValidateSyncApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->header('X-API-Key');
        $apiSecret = $request->header('X-API-Secret');

        if (!$apiKey || !$apiSecret) {
            return response()->json([
                'success' => false,
                'message' => 'API Key and Secret are required',
            ], 401);
        }

        // Find marketplace by API key
        $marketplace = Marketplace_model::where('api_key', $apiKey)
            ->where('name', 'Syntora MV4')
            ->first();

        if (!$marketplace) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid API Key',
            ], 401);
        }

        // Verify the secret matches
        if ($marketplace->api_secret !== $apiSecret) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid API Secret',
            ], 401);
        }

        // Attach marketplace to request for use in controller
        $request->merge(['marketplace' => $marketplace]);

        return $next($request);
    }
}
