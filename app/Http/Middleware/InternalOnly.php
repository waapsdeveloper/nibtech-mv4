<?php
// In app/Http/Middleware/InternalOnly.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Env;

class InternalOnly
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Define allowed IPs for internal access
        $allowedIps = [
            '127.0.0.1', // localhost
            '::1',       // localhost IPv6
            // '192.168.1.1', // Replace with your server's internal IP
            Env::get('SERVER_IP'), // Get the server IP from .env file
        ];

        $url = end(explode('/',Env::get('APP_URL')));
        // Check if the request originated from the allowed domain
        if ($request->getHost() !== Env::get('APP_URL')) {
            dd($request->getHost(), Env::get('APP_URL'), request(), $url);
            abort(401, 'Unauthorized accessu');
        }

        if (!in_array($request->ip(), $allowedIps)) {
            dd($request->ip());
            abort(401, 'Unauthorized access');

        }
        return $next($request);
    }
}
