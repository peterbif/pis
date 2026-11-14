<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        if ($request->is('api/*')) $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        if ($request->isSecure()) $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        if (app()->isProduction()) {
            $wsScheme = config('reverb.client_scheme') === 'https' ? 'wss' : 'ws';
            $wsHost = config('reverb.client_host');
            $wsPort = (int) config('reverb.client_port');
            $defaultWsPort = $wsScheme === 'wss' ? 443 : 80;
            $wsOrigin = $wsHost ? $wsScheme.'://'.$wsHost.($wsPort && $wsPort !== $defaultWsPort ? ':'.$wsPort : '') : null;
            $connectSources = "'self'".($wsOrigin ? ' '.$wsOrigin : '');
            $response->headers->set('Content-Security-Policy', "default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self'; script-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; img-src 'self' data: blob:; connect-src {$connectSources}; upgrade-insecure-requests");
        }
        return $response;
    }
}
