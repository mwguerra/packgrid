<?php

namespace App\Http\Middleware;

use App\Models\Token;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DockerRegistryAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        // If no tokens exist, allow public access (backwards compatible)
        if (Token::query()->doesntExist()) {
            return $next($request);
        }

        // Extract token from HTTP Basic Auth
        // Docker sends: username=token, password=anything
        // OR: username=anything, password=token
        $tokenValue = $this->extractToken($request);

        if (! $tokenValue) {
            return $this->unauthorized();
        }

        $token = Token::query()->where('token', $tokenValue)->first();

        if (! $token) {
            return $this->unauthorized('UNAUTHORIZED');
        }

        if (! $token->isValid()) {
            return $this->unauthorized('UNAUTHORIZED');
        }

        if (! $token->isAllowedFromIp($request->ip())) {
            return $this->unauthorized('DENIED');
        }

        $token->recordUsage();

        $request->attributes->set('packgrid_token', $token);

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        // Docker clients send credentials via HTTP Basic Auth
        // We accept the token as either username or password for flexibility
        [$username, $password] = $this->parseBasicAuth($request);

        // Try username first (docker login -u <token>)
        if ($username && $this->isValidTokenFormat($username)) {
            // Verify it's a real token
            if (Token::query()->where('token', $username)->exists()) {
                return $username;
            }
        }

        // Try password (docker login -u token -p <actual_token>)
        if ($password && $this->isValidTokenFormat($password)) {
            return $password;
        }

        return null;
    }

    /**
     * Parse Basic Auth credentials from the request.
     *
     * Tries PHP's built-in auth variables first, then falls back to manually
     * parsing the Authorization header. This fallback is needed because many
     * nginx + PHP-FPM configurations don't set PHP_AUTH_USER/PHP_AUTH_PW.
     *
     * @return array{0: ?string, 1: ?string} [username, password]
     */
    private function parseBasicAuth(Request $request): array
    {
        $username = $request->getUser();
        $password = $request->getPassword();

        if ($username !== null || $password !== null) {
            return [$username, $password];
        }

        // Fallback: manually parse the Authorization header
        $header = $request->header('Authorization')
            ?? $request->server->get('HTTP_AUTHORIZATION')
            ?? $request->server->get('REDIRECT_HTTP_AUTHORIZATION');

        if ($header && stripos($header, 'Basic ') === 0) {
            $decoded = base64_decode(substr($header, 6), true);
            if ($decoded !== false && str_contains($decoded, ':')) {
                return explode(':', $decoded, 2);
            }
        }

        return [null, null];
    }

    private function isValidTokenFormat(string $value): bool
    {
        // Tokens should be at least 20 characters
        return strlen($value) >= 20;
    }

    private function unauthorized(string $code = 'UNAUTHORIZED'): Response
    {
        $realm = config('app.url', 'packgrid');

        return response()->json([
            'errors' => [
                [
                    'code' => $code,
                    'message' => 'authentication required',
                    'detail' => [],
                ],
            ],
        ], 401, [
            'WWW-Authenticate' => sprintf('Basic realm="%s"', $realm),
            'Docker-Distribution-Api-Version' => 'registry/2.0',
        ]);
    }
}
