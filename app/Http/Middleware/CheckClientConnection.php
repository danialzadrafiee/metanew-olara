<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckClientConnection
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (connection_aborted()) {
            abort(499, 'Client Closed Request');
        }

        return $response;
    }
}