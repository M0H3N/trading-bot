<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogViewerBasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isEnabled()) {
            return $next($request);
        }

        if ($this->credentialsMatch($request)) {
            return $next($request);
        }

        return response('Unauthorized.', Response::HTTP_UNAUTHORIZED, [
            'WWW-Authenticate' => 'Basic realm="Log Viewer"',
        ]);
    }

    public static function isEnabled(): bool
    {
        return (bool) config('log-viewer.basic_auth.enabled', true);
    }

    public static function credentialsAreConfigured(): bool
    {
        $username = config('log-viewer.basic_auth.username');
        $password = config('log-viewer.basic_auth.password');

        return is_string($username) && $username !== ''
            && is_string($password) && $password !== '';
    }

    public static function credentialsMatch(Request $request): bool
    {
        if (! self::credentialsAreConfigured()) {
            return false;
        }

        $username = (string) config('log-viewer.basic_auth.username');
        $password = (string) config('log-viewer.basic_auth.password');

        return hash_equals($username, (string) $request->getUser())
            && hash_equals($password, (string) $request->getPassword());
    }
}
