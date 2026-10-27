<?php

namespace Laravel\Nova\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sentinel\Http\Middleware\SentinelMiddleware;
use Symfony\Component\HttpFoundation\Response;

class PreventsAccessingViaReverseProxiesFromLocalEnvironment extends SentinelMiddleware
{
    /** {@inheritdoc} */
    #[\Override]
    public function handle(Request $request, Closure $next, ?string $driver = null): Response
    {
        return parent::handle($request, $next, 'nova');
    }
}
