<?php

use App\DTOs\FileContentDto;
use App\DTOs\RefDto;
use App\DTOs\RepositoryInfoDto;
use App\Models\Credential;
use App\Services\GitLabClient;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('testConnection calls GitLab user endpoint', function () {
    Http::fake(['https://gitlab.com/api/v4/user' => Http::response(['username' => 'jsmith'], 200)]);

    $client = new GitLabClient();
    $result = $client->testConnection();

    expect($result['username'])->toBe('jsmith');
});

test('getRepositoryInfo returns RepositoryInfoDto from GitLab response', function () {
    Http::fake([
        'https://gitlab.com/api/v4/projects/acme%2Fpkg' => Http::response([
            'name' => 'pkg',
            'visibility' => 'private',
            'default_branch' => 'main',
        ], 200),
    ]);

    $client = new GitLabClient();
    $dto = $client->getRepositoryInfo('acme/pkg');

    expect($dto)->toBeInstanceOf(RepositoryInfoDto::class)
        ->and($dto->fullName)->toBe('acme/pkg')
        ->and($dto->name)->toBe('pkg')
        ->and($dto->isPrivate)->toBeTrue()
        ->and($dto->defaultBranch)->toBe('main');
});

test('public GitLab repo is not private', function () {
    Http::fake([
        'https://gitlab.com/api/v4/projects/acme%2Fpkg' => Http::response([
            'name' => 'pkg',
            'visibility' => 'public',
            'default_branch' => 'main',
        ], 200),
    ]);

    $client = new GitLabClient();
    $dto = $client->getRepositoryInfo('acme/pkg');

    expect($dto->isPrivate)->toBeFalse();
});

test('listTags maps GitLab commit id to RefDto sha', function () {
    Http::fake([
        'https://gitlab.com/api/v4/projects/acme%2Fpkg/repository/tags' => Http::response([
            ['name' => 'v1.0.0', 'commit' => ['id' => 'abc123']],
            ['name' => 'v2.0.0', 'commit' => ['id' => 'def456']],
        ], 200),
    ]);

    $client = new GitLabClient();
    $refs = $client->listTags('acme/pkg');

    expect($refs)->toHaveCount(2)
        ->and($refs[0])->toBeInstanceOf(RefDto::class)
        ->and($refs[0]->name)->toBe('v1.0.0')
        ->and($refs[0]->sha)->toBe('abc123')
        ->and($refs[0]->type)->toBe('tag');
});

test('listBranches maps GitLab commit id to RefDto sha', function () {
    Http::fake([
        'https://gitlab.com/api/v4/projects/acme%2Fpkg/repository/branches' => Http::response([
            ['name' => 'main', 'commit' => ['id' => 'aaa111']],
        ], 200),
    ]);

    $client = new GitLabClient();
    $refs = $client->listBranches('acme/pkg');

    expect($refs[0])->toBeInstanceOf(RefDto::class)
        ->and($refs[0]->name)->toBe('main')
        ->and($refs[0]->type)->toBe('branch');
});

test('getFileContent decodes base64 content from GitLab', function () {
    $encoded = base64_encode('{"name":"acme/pkg"}');
    Http::fake([
        'https://gitlab.com/api/v4/projects/acme%2Fpkg/repository/files/composer.json*' => Http::response([
            'content' => $encoded,
            'encoding' => 'base64',
        ], 200),
    ]);

    $client = new GitLabClient();
    $dto = $client->getFileContent('acme/pkg', 'composer.json', 'v1.0.0');

    expect($dto)->toBeInstanceOf(FileContentDto::class)
        ->and($dto->content)->toBe('{"name":"acme/pkg"}');
});

test('self-hosted GitLab uses base_url from credential', function () {
    Http::fake([
        'https://git.company.com/api/v4/projects/acme%2Fpkg/repository/tags' => Http::response([
            ['name' => 'v1.0.0', 'commit' => ['id' => 'abc']],
        ], 200),
    ]);

    $credential = new Credential(['base_url' => 'https://git.company.com', 'token' => 'secret']);
    $client = new GitLabClient($credential);
    $refs = $client->listTags('acme/pkg');

    expect($refs[0]->name)->toBe('v1.0.0');
});

test('getHttpGitCredentials returns oauth2 and token', function () {
    $credential = new Credential(['token' => 'glpat-secret']);
    $client = new GitLabClient($credential);

    expect($client->getHttpGitCredentials())->toBe(['oauth2', 'glpat-secret']);
});

test('getHttpGitCredentials returns null when no credential', function () {
    $client = new GitLabClient();
    expect($client->getHttpGitCredentials())->toBeNull();
});

test('PRIVATE-TOKEN header is sent for authenticated requests', function () {
    Http::fake([
        'https://gitlab.com/api/v4/projects/acme%2Fpkg/repository/tags' => Http::response([
            ['name' => 'v1.0.0', 'commit' => ['id' => 'abc']],
        ], 200),
    ]);

    $credential = new Credential(['token' => 'glpat-secret']);
    $client = new GitLabClient($credential);
    $client->listTags('acme/pkg');

    Http::assertSent(fn ($request) => $request->hasHeader('PRIVATE-TOKEN', 'glpat-secret'));
});
