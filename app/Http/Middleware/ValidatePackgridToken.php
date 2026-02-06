<?php

namespace App\Http\Middleware;

use App\Models\Token;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidatePackgridToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // If no tokens exist, allow public access (backwards compatible)
        if (Token::query()->doesntExist()) {
            return $next($request);
        }

        // Extract token from:
        // 1. HTTP Basic Auth (Composer sends token as password with any username)
        // 2. Bearer token (npm sends token as Authorization: Bearer <token>)
        $tokenValue = $this->extractToken($request);

        if (! $tokenValue) {
            return $this->unauthorized('Authentication required.');
        }

        $token = Token::query()->where('token', $tokenValue)->first();

        if (! $token) {
            return $this->unauthorized('Invalid token.');
        }

        if (! $token->isValid()) {
            return $this->unauthorized('Token is disabled or expired.');
        }

        if (! $token->isAllowedFromIp($request->ip())) {
            return $this->unauthorized('Access denied from this IP address.');
        }

        $refererDomain = $this->extractDomain($request->header('Referer'));
        $originDomain = $this->extractDomain($request->header('Origin'));
        $domain = $refererDomain ?: $originDomain;

        if (! $token->isAllowedFromDomain($domain)) {
            return $this->unauthorized('Access denied from this domain.');
        }

        $token->recordUsage();

        $request->attributes->set('packgrid_token', $token);

        return $next($request);
    }

    /**
     * Extract token from request using various authentication methods.
     */
    private function extractToken(Request $request): ?string
    {
        // 1. Try HTTP Basic Auth (Composer sends token as password)
        $password = $request->getPassword();
        if ($password) {
            return $password;
        }

        // 2. Try Bearer token (npm sends token as Authorization: Bearer <token>)
        $authHeader = $request->header('Authorization', '');
        if (str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        return null;
    }

    private function unauthorized(string $message): Response
    {
        return response()->json(['error' => $message], 401, [
            'WWW-Authenticate' => 'Basic realm="Packgrid"',
        ]);
    }

    private function extractDomain(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $parsed = parse_url($url);

        return $parsed['host'] ?? null;
    }
}
