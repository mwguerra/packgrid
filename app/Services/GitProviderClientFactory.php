<?php

namespace App\Services;

use App\Contracts\GitProviderClientInterface;
use App\Enums\GitProvider;
use App\Models\Credential;

class GitProviderClientFactory
{
    public function forCredential(?Credential $credential): GitProviderClientInterface
    {
        if (! $credential) {
            return new GitHubClient();
        }

        $provider = GitProvider::tryFrom($credential->provider ?? '') ?? GitProvider::GitHub;

        return match ($provider) {
            GitProvider::GitLab => new GitLabClient($credential),
            GitProvider::GitHub => new GitHubClient($credential),
        };
    }
}
