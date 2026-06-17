<?php

use App\Services\GitHubClient;
use Illuminate\Support\Facades\Http;

test('listTags is cached within the ttl', function () {
    config(['packgrid.github_cache.ttl' => 60]);
    Http::fake([
        'https://api.github.com/repos/acme/tools/tags' => Http::response([['name' => 'v1.0.0', 'commit' => ['sha' => 's']]], 200),
    ]);

    $client = app(GitHubClient::class);
    $client->listTags('acme/tools');
    $client->listTags('acme/tools');

    Http::assertSentCount(1);
});

test('the cache expires after the ttl', function () {
    config(['packgrid.github_cache.ttl' => 60]);
    Http::fake([
        'https://api.github.com/repos/acme/tools/tags' => Http::response([['name' => 'v1.0.0', 'commit' => ['sha' => 's']]], 200),
    ]);

    $client = app(GitHubClient::class);
    $client->listTags('acme/tools');
    $this->travel(61)->seconds();
    $client->listTags('acme/tools');

    Http::assertSentCount(2);
});

test('error responses are not cached', function () {
    config(['packgrid.github_cache.ttl' => 60]);
    Http::fakeSequence('https://api.github.com/repos/acme/tools/tags')
        ->push(['message' => 'rate limited'], 429)
        ->push([['name' => 'v1.0.0', 'commit' => ['sha' => 's']]], 200);

    $client = app(GitHubClient::class);

    expect(fn () => $client->listTags('acme/tools'))
        ->toThrow(\Illuminate\Http\Client\RequestException::class);

    expect($client->listTags('acme/tools'))->toHaveCount(1);
    Http::assertSentCount(2);
});

test('ttl of zero disables caching', function () {
    config(['packgrid.github_cache.ttl' => 0]);
    Http::fake([
        'https://api.github.com/repos/acme/tools/tags' => Http::response([['name' => 'v1.0.0', 'commit' => ['sha' => 's']]], 200),
    ]);

    $client = app(GitHubClient::class);
    $client->listTags('acme/tools');
    $client->listTags('acme/tools');

    Http::assertSentCount(2);
});
