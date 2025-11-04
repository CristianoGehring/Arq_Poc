<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ApiVersionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $version = $request->segment(2);

        if (! Str::startsWith($version, 'v') || ! is_numeric(substr($version, 1))) {
            throw new NotFoundHttpException('Invalid API version.');
        }

        $request->attributes->set('api_version', $version);

        return $next($request);
    }
}
