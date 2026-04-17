<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CorsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $allowedOrigins = [
            'https://red-mud-0883f360f.7.azurestaticapps.net',
            'http://localhost:5173',
            'http://127.0.0.1:5173',
        ];

        $origin = $request->header('Origin');

        // Handle preflight requests
        if ($request->isMethod('OPTIONS')) {
            $response = response('', 200);
            
            if (in_array($origin, $allowedOrigins)) {
                $response->header('Access-Control-Allow-Origin', $origin);
                $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH');
                $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
                $response->header('Access-Control-Allow-Credentials', 'true');
                $response->header('Access-Control-Max-Age', '86400');
            }
            
            return $response;
        }

        // Handle actual requests
        $response = $next($request);

        if (in_array($origin, $allowedOrigins)) {
            $response->header('Access-Control-Allow-Origin', $origin);
            $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH');
            $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            $response->header('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }
}
