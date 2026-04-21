<?php

use App\Models\Credential;
use App\Services\GitHubClient;
use App\Services\GitLabClient;
use App\Services\GitProviderClientFactory;

test('forCredential returns GitHubClient when credential is null', function () {
    $factory = new GitProviderClientFactory();
    $client = $factory->forCredential(null);

    expect($client)->toBeInstanceOf(GitHubClient::class);
});

test('forCredential returns GitHubClient for github provider', function () {
    $credential = new Credential(['provider' => 'github', 'token' => 'ghp_secret']);
    $factory = new GitProviderClientFactory();
    $client = $factory->forCredential($credential);

    expect($client)->toBeInstanceOf(GitHubClient::class);
});

test('forCredential returns GitLabClient for gitlab provider', function () {
    $credential = new Credential(['provider' => 'gitlab', 'token' => 'glpat-secret']);
    $factory = new GitProviderClientFactory();
    $client = $factory->forCredential($credential);

    expect($client)->toBeInstanceOf(GitLabClient::class);
});

test('forCredential defaults to GitHubClient when provider is null', function () {
    $credential = new Credential(['provider' => null, 'token' => 'ghp_secret']);
    $factory = new GitProviderClientFactory();
    $client = $factory->forCredential($credential);

    expect($client)->toBeInstanceOf(GitHubClient::class);
});
