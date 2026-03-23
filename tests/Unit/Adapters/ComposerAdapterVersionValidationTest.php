<?php

use App\Adapters\ComposerAdapter;
use App\Services\GitHubClient;

beforeEach(function () {
    $this->adapter = new ComposerAdapter(Mockery::mock(GitHubClient::class));
});

describe('isValidVersion', function () {
    it('accepts standard semver tags', function (string $version) {
        expect($this->adapter->isValidVersion($version))->toBeTrue();
    })->with([
        '1.0.0',
        '0.1.0',
        '2.3.4',
        'v1.0.0',
        'v2.3.4',
    ]);

    it('accepts dev branch versions', function (string $version) {
        expect($this->adapter->isValidVersion($version))->toBeTrue();
    })->with([
        'dev-main',
        'dev-master',
        'dev-develop',
        'dev-feature/new-thing',
    ]);

    it('accepts pre-release and build metadata versions', function (string $version) {
        expect($this->adapter->isValidVersion($version))->toBeTrue();
    })->with([
        '1.0.0-alpha',
        '1.0.0-beta.1',
        '1.0.0-RC1',
    ]);

    it('rejects non-semver strings', function (string $version) {
        expect($this->adapter->isValidVersion($version))->toBeFalse();
    })->with([
        'runner-latest',
        'latest',
        'stable',
        'nightly-2024-01-01',
        'my-custom-tag',
        'release-candidate',
        '',
    ]);
});

describe('normalizeVersion + isValidVersion together', function () {
    it('validates normalized tag versions', function (string $ref, bool $expected) {
        $version = $this->adapter->normalizeVersion($ref, 'tag');

        expect($this->adapter->isValidVersion($version))->toBe($expected);
    })->with([
        ['v1.0.0', true],
        ['1.0.0', true],
        ['runner-latest', false],
        ['latest', false],
    ]);

    it('validates normalized branch versions', function (string $ref, bool $expected) {
        $version = $this->adapter->normalizeVersion($ref, 'branch');

        expect($this->adapter->isValidVersion($version))->toBe($expected);
    })->with([
        ['main', true],
        ['feature/test', true],
        ['runner-latest', true], // dev-runner-latest is valid for Composer
    ]);
});
