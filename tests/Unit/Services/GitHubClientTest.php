<?php

use App\DTOs\FileContentDto;
use App\DTOs\RefDto;
use App\DTOs\RepositoryInfoDto;
use App\Models\Credential;
use App\Services\GitHubClient;
use Illuminate\Support\Facades\Http;

test('testConnection returns raw user array', function () {
    Http::fake(['https://api.github.com/user' => Http::response(['login' => 'octocat'], 200)]);

    $client = new GitHubClient();
    $result = $client->testConnection();

    expect($result['login'])->toBe('octocat');
});

test('getRepositoryInfo returns RepositoryInfoDto', function () {
    Http::fake([
        'https://api.github.com/repos/acme/pkg' => Http::response([
            'name' => 'pkg',
            'private' => true,
            'default_branch' => 'main',
        ], 200),
    ]);

    $client = new GitHubClient();
    $dto = $client->getRepositoryInfo('acme/pkg');

    expect($dto)->toBeInstanceOf(RepositoryInfoDto::class)
        ->and($dto->fullName)->toBe('acme/pkg')
        ->and($dto->name)->toBe('pkg')
        ->and($dto->isPrivate)->toBeTrue()
        ->and($dto->defaultBranch)->toBe('main');
});

test('listTags returns RefDto array', function () {
    Http::fake([
        'https://api.github.com/repos/acme/pkg/tags' => Http::response([
            ['name' => 'v1.0.0', 'commit' => ['sha' => 'abc123']],
            ['name' => 'v2.0.0', 'commit' => ['sha' => 'def456']],
        ], 200),
    ]);

    $client = new GitHubClient();
    $refs = $client->listTags('acme/pkg');

    expect($refs)->toHaveCount(2)
        ->and($refs[0])->toBeInstanceOf(RefDto::class)
        ->and($refs[0]->name)->toBe('v1.0.0')
        ->and($refs[0]->sha)->toBe('abc123')
        ->and($refs[0]->type)->toBe('tag');
});

test('listBranches returns RefDto array', function () {
    Http::fake([
        'https://api.github.com/repos/acme/pkg/branches*' => Http::response([
            ['name' => 'main', 'commit' => ['sha' => 'aaa111']],
        ], 200),
    ]);

    $client = new GitHubClient();
    $refs = $client->listBranches('acme/pkg');

    expect($refs[0])->toBeInstanceOf(RefDto::class)
        ->and($refs[0]->name)->toBe('main')
        ->and($refs[0]->type)->toBe('branch');
});

test('getFileContent returns decoded FileContentDto', function () {
    $encoded = base64_encode('{"name":"acme/pkg"}');
    Http::fake([
        'https://api.github.com/repos/acme/pkg/contents/composer.json*' => Http::response([
            'content' => $encoded."\n",
        ], 200),
    ]);

    $client = new GitHubClient();
    $dto = $client->getFileContent('acme/pkg', 'composer.json', 'v1.0.0');

    expect($dto)->toBeInstanceOf(FileContentDto::class)
        ->and($dto->path)->toBe('composer.json')
        ->and($dto->content)->toBe('{"name":"acme/pkg"}');
});

test('getHttpGitCredentials returns null when no credential', function () {
    $client = new GitHubClient();
    expect($client->getHttpGitCredentials())->toBeNull();
});

test('getHttpGitCredentials returns basic auth pair', function () {
    $credential = new Credential(['token' => 'ghp_secret']);
    $client = new GitHubClient($credential);
    expect($client->getHttpGitCredentials())->toBe(['x-access-token', 'ghp_secret']);
});
