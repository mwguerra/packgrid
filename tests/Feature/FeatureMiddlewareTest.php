<?php

use App\Models\Setting;
use App\Support\PackgridSettings;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Clear settings cache before each test
    PackgridSettings::clearCache();
});

test('composer routes return 503 when composer is disabled', function () {
    Setting::query()->delete();
    Setting::create([
        'composer_enabled' => false,
        'npm_enabled' => true,
        'docker_enabled' => true,
    ]);
    PackgridSettings::clearCache();

    $this->get('/packages.json')
        ->assertStatus(503)
        ->assertJson([
            'error' => 'This feature is disabled. Contact administrator.',
            'feature' => 'composer',
        ]);
});

test('npm routes return 503 when npm is disabled', function () {
    Setting::query()->delete();
    Setting::create([
        'composer_enabled' => true,
        'npm_enabled' => false,
        'docker_enabled' => true,
    ]);
    PackgridSettings::clearCache();

    $this->get('/npm/@test/package')
        ->assertStatus(503)
        ->assertJson([
            'error' => 'This feature is disabled. Contact administrator.',
            'feature' => 'npm',
        ]);
});

test('docker routes return 503 when docker is disabled', function () {
    Setting::query()->delete();
    Setting::create([
        'composer_enabled' => true,
        'npm_enabled' => true,
        'docker_enabled' => false,
    ]);
    PackgridSettings::clearCache();

    // Docker catalog endpoint requires auth, so we'll get 503 before auth is checked
    $this->get('/v2/_catalog')
        ->assertStatus(503)
        ->assertJson([
            'error' => 'This feature is disabled. Contact administrator.',
            'feature' => 'docker',
        ]);
});

test('composer routes work when composer is enabled', function () {
    Storage::fake('local');

    Setting::query()->delete();
    Setting::create([
        'composer_enabled' => true,
        'npm_enabled' => true,
        'docker_enabled' => true,
    ]);
    PackgridSettings::clearCache();

    // Write a mock packages.json
    $store = app(\App\Services\PackageMetadataStore::class);
    $store->writePackagesIndex(['packages' => []]);

    $this->get('/packages.json')
        ->assertOk();
});

test('npm routes work when npm is enabled', function () {
    Setting::query()->delete();
    Setting::create([
        'composer_enabled' => true,
        'npm_enabled' => true,
        'docker_enabled' => true,
    ]);
    PackgridSettings::clearCache();

    // Should get 404 (package not found) instead of 503
    $this->get('/npm/@test/nonexistent')
        ->assertStatus(404);
});

test('docker version check works regardless of docker setting', function () {
    Setting::query()->delete();
    Setting::create([
        'composer_enabled' => true,
        'npm_enabled' => true,
        'docker_enabled' => false,
    ]);
    PackgridSettings::clearCache();

    // Docker version check endpoint (/v2/) should still work without feature middleware
    // It returns 200 when no tokens exist (public access)
    $this->get('/v2/')
        ->assertOk();
});
