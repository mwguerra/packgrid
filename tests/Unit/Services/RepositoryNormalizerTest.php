<?php

use App\Models\Credential;
use App\Services\GitProviderClientFactory;
use App\Services\RepositoryFormatDetector;
use App\Services\RepositoryNormalizer;
use Illuminate\Support\Facades\Http;

function makeNormalizer(): RepositoryNormalizer
{
    return new RepositoryNormalizer(
        new GitProviderClientFactory(),
        new RepositoryFormatDetector(new GitProviderClientFactory()),
    );
}

// --- GitHub ---

test('normalizer parses plain owner/repo for GitHub', function () {
    Http::fake([
        'https://api.github.com/repos/acme/pkg' => Http::response([
            'name' => 'pkg', 'private' => false, 'default_branch' => 'main',
        ]),
        'https://api.github.com/repos/acme/pkg/contents/composer.json*' => Http::response(['content' => base64_encode('{}')]),
    ]);

    $result = makeNormalizer()->normalize('acme/pkg');

    expect($result['repo_full_name'])->toBe('acme/pkg')
        ->and($result['url'])->toBe('https://github.com/acme/pkg');
});

test('normalizer parses full GitHub HTTPS URL', function () {
    Http::fake([
        'https://api.github.com/repos/acme/pkg' => Http::response(['name' => 'pkg', 'private' => false, 'default_branch' => 'main']),
        'https://api.github.com/repos/acme/pkg/contents/composer.json*' => Http::response(['content' => base64_encode('{}')]),
    ]);

    $result = makeNormalizer()->normalize('https://github.com/acme/pkg');

    expect($result['repo_full_name'])->toBe('acme/pkg');
});

test('normalizer parses GitHub SSH URL', function () {
    Http::fake([
        'https://api.github.com/repos/acme/pkg' => Http::response(['name' => 'pkg', 'private' => false, 'default_branch' => 'main']),
        'https://api.github.com/repos/acme/pkg/contents/composer.json*' => Http::response(['content' => base64_encode('{}')]),
    ]);

    $result = makeNormalizer()->normalize('git@github.com:acme/pkg.git');

    expect($result['repo_full_name'])->toBe('acme/pkg');
});

// --- GitLab ---

test('normalizer parses GitLab HTTPS URL using gitlab credential', function () {
    Http::fake([
        'https://gitlab.com/api/v4/projects/acme%2Fpkg' => Http::response([
            'name' => 'pkg', 'visibility' => 'private', 'default_branch' => 'main',
        ]),
        'https://gitlab.com/api/v4/projects/acme%2Fpkg/repository/files/composer.json*' => Http::response([
            'content' => base64_encode('{}'), 'encoding' => 'base64',
        ]),
    ]);

    $credential = new Credential(['provider' => 'gitlab', 'token' => 'glpat-secret']);
    $result = makeNormalizer()->normalize('https://gitlab.com/acme/pkg', $credential);

    expect($result['repo_full_name'])->toBe('acme/pkg')
        ->and($result['url'])->toBe('https://gitlab.com/acme/pkg');
});

test('normalizer parses GitLab SSH URL', function () {
    Http::fake([
        'https://gitlab.com/api/v4/projects/acme%2Fpkg' => Http::response([
            'name' => 'pkg', 'visibility' => 'private', 'default_branch' => 'main',
        ]),
        'https://gitlab.com/api/v4/projects/acme%2Fpkg/repository/files/composer.json*' => Http::response([
            'content' => base64_encode('{}'), 'encoding' => 'base64',
        ]),
    ]);

    $credential = new Credential(['provider' => 'gitlab', 'token' => 'glpat-secret']);
    $result = makeNormalizer()->normalize('git@gitlab.com:acme/pkg.git', $credential);

    expect($result['repo_full_name'])->toBe('acme/pkg');
});

test('normalizer builds self-hosted GitLab URL from credential base_url', function () {
    Http::fake([
        'https://git.company.com/api/v4/projects/acme%2Fpkg' => Http::response([
            'name' => 'pkg', 'visibility' => 'private', 'default_branch' => 'main',
        ]),
        'https://git.company.com/api/v4/projects/acme%2Fpkg/repository/files/composer.json*' => Http::response([
            'content' => base64_encode('{}'), 'encoding' => 'base64',
        ]),
    ]);

    $credential = new Credential([
        'provider' => 'gitlab',
        'base_url' => 'https://git.company.com',
        'token' => 'glpat-secret',
    ]);
    $result = makeNormalizer()->normalize('acme/pkg', $credential);

    expect($result['url'])->toBe('https://git.company.com/acme/pkg');
});

test('normalizer rejects invalid input', function () {
    makeNormalizer()->normalize('not-valid');
})->throws(RuntimeException::class);

// --- Self-hosted GitLab (no credential) ---

test('normalizer auto-detects self-hosted GitLab from HTTPS URL', function () {
    Http::fake([
        'https://git.example.com/api/v4/projects/acme%2Fpkg' => Http::response([
            'name' => 'pkg', 'visibility' => 'public', 'default_branch' => 'main',
        ]),
        'https://git.example.com/api/v4/projects/acme%2Fpkg/repository/files/composer.json*' => Http::response([
            'content' => base64_encode('{}'), 'encoding' => 'base64',
        ]),
    ]);

    $result = makeNormalizer()->normalize('https://git.example.com/acme/pkg');

    expect($result['repo_full_name'])->toBe('acme/pkg')
        ->and($result['url'])->toBe('https://git.example.com/acme/pkg');
});

test('normalizer auto-detects self-hosted GitLab with subgroups from HTTPS URL', function () {
    Http::fake([
        'https://git.example.com/api/v4/projects/org%2Fteam%2Fpkg' => Http::response([
            'name' => 'pkg', 'visibility' => 'public', 'default_branch' => 'main',
        ]),
        'https://git.example.com/api/v4/projects/org%2Fteam%2Fpkg/repository/files/composer.json*' => Http::response([
            'content' => base64_encode('{}'), 'encoding' => 'base64',
        ]),
    ]);

    $result = makeNormalizer()->normalize('https://git.example.com/org/team/pkg');

    expect($result['repo_full_name'])->toBe('org/team/pkg')
        ->and($result['url'])->toBe('https://git.example.com/org/team/pkg');
});
