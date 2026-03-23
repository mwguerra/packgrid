<?php

use App\Adapters\NpmAdapter;
use App\Services\GitHubClient;

beforeEach(function () {
    $this->adapter = new NpmAdapter(Mockery::mock(GitHubClient::class));
});

describe('isValidVersion', function () {
    it('accepts standard semver versions', function (string $version) {
        expect($this->adapter->isValidVersion($version))->toBeTrue();
    })->with([
        '1.0.0',
        '0.1.0',
        '2.3.4',
    ]);

    it('accepts pre-release versions', function (string $version) {
        expect($this->adapter->isValidVersion($version))->toBeTrue();
    })->with([
        '0.0.0-main',
        '0.0.0-develop',
        '1.0.0-alpha',
        '1.0.0-beta.1',
        '1.0.0-rc.1',
    ]);

    it('accepts build metadata versions', function (string $version) {
        expect($this->adapter->isValidVersion($version))->toBeTrue();
    })->with([
        '1.0.0+build.123',
    ]);

    it('rejects non-semver strings', function (string $version) {
        expect($this->adapter->isValidVersion($version))->toBeFalse();
    })->with([
        'runner-latest',
        'latest',
        'stable',
        'nightly-2024-01-01',
        'my-custom-tag',
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
        ['develop', true],
        ['runner-latest', true], // 0.0.0-runner-latest is valid NPM prerelease
    ]);
});
