<?php

namespace App\Services;

use App\Enums\GitProvider;
use App\Enums\RepositoryVisibility;
use App\Models\Credential;
use RuntimeException;
use Throwable;

class RepositoryNormalizer
{
    public function __construct(
        private readonly GitProviderClientFactory $clientFactory,
        private readonly RepositoryFormatDetector $formatDetector,
    ) {}

    public function normalize(string $input, ?Credential $credential = null): array
    {
        $provider = $this->detectProvider($input, $credential);
        $fullName = $this->parseFullName($input, $provider, $credential);
        $url = $this->canonicalUrl($fullName, $provider, $credential, $input);

        $name = ucwords(str_replace('-', ' ', basename($fullName)));
        $visibility = null;
        $format = null;

        try {
            $client = $this->clientFactory->forCredential($credential);
            $info = $client->getRepositoryInfo($fullName);
            $visibility = $info->isPrivate
                ? RepositoryVisibility::PrivateRepo->value
                : RepositoryVisibility::PublicRepo->value;
            $name = $info->name;
        } catch (Throwable) {
            // Best-effort — leave as null; validation handles missing visibility later
        }

        try {
            $format = $this->formatDetector->detect($fullName, $credential)?->value;
        } catch (Throwable) {
            // Best-effort
        }

        return [
            'repo_full_name' => $fullName,
            'url' => $url,
            'name' => $name,
            'visibility' => $visibility,
            'format' => $format,
        ];
    }

    /**
     * Determine the Git provider from the credential (explicit) or from the
     * URL host (auto-detected). Any non-github.com HTTPS host is treated as
     * GitLab (gitlab.com or self-hosted). Plain owner/repo defaults to GitHub.
     */
    private function detectProvider(string $input, ?Credential $credential): GitProvider
    {
        if ($credential?->provider) {
            return GitProvider::tryFrom($credential->provider) ?? GitProvider::GitHub;
        }

        // Extract host from HTTPS or SSH URL
        $host = parse_url($input, PHP_URL_HOST);
        if (! $host && preg_match('/^git@([^:]+):/i', $input, $m)) {
            $host = $m[1];
        }

        if ($host) {
            return $host === 'github.com' ? GitProvider::GitHub : GitProvider::GitLab;
        }

        // Plain owner/repo — default to GitHub
        return GitProvider::GitHub;
    }

    private function parseFullName(string $input, GitProvider $provider, ?Credential $credential): string
    {
        $input = trim($input);

        // SSH: git@HOST:owner/repo.git
        if (preg_match('/^git@([^:]+):(.+)$/i', $input, $matches)) {
            $path = preg_replace('/\.git$/', '', $matches[2]) ?? $matches[2];

            return $this->validate($path, $provider);
        }

        // Remove .git suffix
        $input = preg_replace('/\.git$/', '', $input) ?? $input;

        // Detect known hosts and strip them
        foreach ($this->knownHosts($provider, $credential) as $host) {
            if (str_contains($input, $host)) {
                if (! str_starts_with($input, 'http')) {
                    $input = 'https://'.$input;
                }
                $path = trim(parse_url($input, PHP_URL_PATH) ?? '', '/');

                return $this->validate($path, $provider);
            }
        }

        // Fallback: any remaining HTTPS URL whose host was not in knownHosts
        // (e.g. a self-hosted GitLab instance not listed as a credential base_url)
        if (str_starts_with($input, 'http')) {
            $path = trim(parse_url($input, PHP_URL_PATH) ?? '', '/');

            return $this->validate($path, $provider);
        }

        // Plain owner/repo — strip any trailing fragments or query strings
        $input = preg_replace('/[#?].*$/', '', $input) ?? $input;

        return $this->validate($input, $provider);
    }

    private function canonicalUrl(string $fullName, GitProvider $provider, ?Credential $credential, string $rawInput = ''): string
    {
        return match ($provider) {
            GitProvider::GitLab => $this->gitLabBaseUrl($credential, $rawInput).'/'.$fullName,
            GitProvider::GitHub => 'https://github.com/'.$fullName,
        };
    }

    /**
     * Resolve the GitLab base URL in priority order:
     * 1. Credential's explicit base_url
     * 2. Origin extracted from the raw input URL
     * 3. Default to gitlab.com
     */
    private function gitLabBaseUrl(?Credential $credential, string $rawInput): string
    {
        if ($credential?->base_url) {
            return rtrim($credential->base_url, '/');
        }

        $scheme = parse_url($rawInput, PHP_URL_SCHEME);
        $host = parse_url($rawInput, PHP_URL_HOST);

        if ($scheme && $host) {
            return $scheme.'://'.$host;
        }

        return 'https://gitlab.com';
    }

    /**
     * @return string[]
     */
    private function knownHosts(GitProvider $provider, ?Credential $credential): array
    {
        return match ($provider) {
            GitProvider::GitLab => array_values(array_filter([
                'gitlab.com',
                $credential?->base_url ? parse_url($credential->base_url, PHP_URL_HOST) : null,
            ])),
            GitProvider::GitHub => ['github.com'],
        };
    }

    private function validate(string $fullName, GitProvider $provider): string
    {
        $fullName = trim($fullName, '/');

        // GitLab supports subgroups (one or more slashes); GitHub has exactly one
        $pattern = $provider === GitProvider::GitLab
            ? '/^[A-Za-z0-9_.-]+(\/[A-Za-z0-9_.-]+)+$/'
            : '/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/';

        if (! preg_match($pattern, $fullName)) {
            throw new RuntimeException(
                $provider === GitProvider::GitLab
                    ? 'Repository must be a GitLab URL or group/project path.'
                    : 'Repository must be a GitHub URL or owner/repo.'
            );
        }

        return $fullName;
    }
}
