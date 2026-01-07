<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class DatabaseConnectionCleanup
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Disconnect to release connection back to pool
        DB::disconnect();

        return $response;
    }

    public function terminate(Request $request, $response)
    {
        // Ensure cleanup on termination
        DB::disconnect();
    }
}
