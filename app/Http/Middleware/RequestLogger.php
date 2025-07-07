<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RequestLogger
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
        // Log sebelum request diproses
        Log::info('API Request', [
            'ip'     => $request->ip(),
            'method' => $request->method(),
            'url'    => $request->fullUrl(),
            'input'  => $request->except(['password', 'password_confirmation']),
            'user_id' => optional($request->user())->id,
        ]);

        $response = $next($request);

        // Optional: log response status
        Log::info('API Response', [
            'status' => $response->status(),
        ]);

        return $response;
    }
}
