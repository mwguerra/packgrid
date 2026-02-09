<?php

namespace App\Services;

use App\Enums\RepositoryVisibility;
use App\Models\Credential;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class RepositoryNormalizer
{
    public function __construct(
        private readonly GitHubClient $client,
        private readonly RepositoryFormatDetector $formatDetector,
    ) {}

    public function normalize(string $input, ?Credential $credential = null): array
    {
        $fullName = $this->parseFullName($input);
        $url = 'https://github.com/'.$fullName;

        $name = Str::title(str_replace('-', ' ', Str::after($fullName, '/')));
        $visibility = null;

        try {
            $repo = $this->client->getRepository($fullName, $credential);
            $visibility = ($repo['private'] ?? false) ? RepositoryVisibility::PrivateRepo->value : RepositoryVisibility::PublicRepo->value;
            $name = $repo['name'] ?? $name;
        } catch (Throwable) {
            $visibility = null;
        }

        // Detect package format from manifest files
        $format = null;
        try {
            $format = $this->formatDetector->detect($fullName, $credential)?->value;
        } catch (Throwable) {
            $format = null;
        }

        return [
            'repo_full_name' => $fullName,
            'url' => $url,
            'name' => $name,
            'visibility' => $visibility,
            'format' => $format,
        ];
    }

    private function parseFullName(string $input): string
    {
        $input = trim($input);

        // Handle SSH format: git@github.com:owner/repo.git
        if (preg_match('/^git@github\.com:(.+)$/i', $input, $matches)) {
            $path = $matches[1];
            $path = preg_replace('/\.git$/', '', $path) ?? $path;

            return $this->validateFullName($path);
        }

        // Remove .git suffix
        $input = preg_replace('/\.git$/', '', $input) ?? $input;

        // Add scheme if missing for proper URL parsing
        if (preg_match('/^github\.com/i', $input)) {
            $input = 'https://'.$input;
        }

        if (str_contains($input, 'github.com')) {
            $parts = parse_url($input);
            // parse_url handles stripping query strings and fragments automatically
            $path = trim($parts['path'] ?? '', '/');

            return $this->validateFullName($path);
        }

        // Plain owner/repo format - strip any trailing fragments or query strings
        $input = preg_replace('/[#?].*$/', '', $input) ?? $input;

        return $this->validateFullName($input);
    }

    private function validateFullName(string $fullName): string
    {
        $fullName = trim($fullName, '/');

        if (! preg_match('/^[A-Za-z0-9_.-]+\\/[A-Za-z0-9_.-]+$/', $fullName)) {
            throw new RuntimeException('Repository must be a GitHub URL or owner/repo.');
        }

        return $fullName;
    }
}
