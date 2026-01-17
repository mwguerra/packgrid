<?php

use App\Enums\PackageFormat;
use App\Models\Setting;
use App\Support\PackgridSettings;

beforeEach(function () {
    // Clear settings cache before each test
    PackgridSettings::clearCache();
    Setting::query()->delete();
});

test('packgrid settings returns true for enabled features', function () {
    Setting::create([
        'composer_enabled' => true,
        'npm_enabled' => false,
        'docker_enabled' => true,
    ]);

    expect(PackgridSettings::composerEnabled())->toBeTrue()
        ->and(PackgridSettings::npmEnabled())->toBeFalse()
        ->and(PackgridSettings::dockerEnabled())->toBeTrue();
});

test('isFeatureEnabled returns correct values', function () {
    Setting::create([
        'composer_enabled' => true,
        'npm_enabled' => false,
        'docker_enabled' => true,
    ]);

    expect(PackgridSettings::isFeatureEnabled('composer'))->toBeTrue()
        ->and(PackgridSettings::isFeatureEnabled('npm'))->toBeFalse()
        ->and(PackgridSettings::isFeatureEnabled('docker'))->toBeTrue()
        ->and(PackgridSettings::isFeatureEnabled('unknown'))->toBeFalse();
});

test('getEnabledFormats returns only enabled formats', function () {
    Setting::create([
        'composer_enabled' => true,
        'npm_enabled' => false,
        'docker_enabled' => true,
    ]);

    $formats = PackgridSettings::getEnabledFormats();

    expect($formats)->toHaveKey(PackageFormat::Composer->value)
        ->and($formats)->not->toHaveKey(PackageFormat::Npm->value);
});

test('getEnabledPackageTypes returns only enabled types', function () {
    Setting::create([
        'composer_enabled' => true,
        'npm_enabled' => false,
        'docker_enabled' => true,
    ]);

    $types = PackgridSettings::getEnabledPackageTypes();

    expect($types)->toContain('composer')
        ->and($types)->toContain('docker')
        ->and($types)->not->toContain('npm');
});

test('hasMultipleFormats returns true when both composer and npm enabled', function () {
    Setting::create([
        'composer_enabled' => true,
        'npm_enabled' => true,
        'docker_enabled' => true,
    ]);

    expect(PackgridSettings::hasMultipleFormats())->toBeTrue();
});

test('hasMultipleFormats returns false when only one format enabled', function () {
    Setting::create([
        'composer_enabled' => true,
        'npm_enabled' => false,
        'docker_enabled' => true,
    ]);

    expect(PackgridSettings::hasMultipleFormats())->toBeFalse();
});

test('repositoriesEnabled returns true when composer or npm enabled', function () {
    Setting::create([
        'composer_enabled' => true,
        'npm_enabled' => false,
        'docker_enabled' => false,
    ]);

    expect(PackgridSettings::repositoriesEnabled())->toBeTrue();
});

test('repositoriesEnabled returns false when both composer and npm disabled', function () {
    Setting::create([
        'composer_enabled' => false,
        'npm_enabled' => false,
        'docker_enabled' => true,
    ]);

    expect(PackgridSettings::repositoriesEnabled())->toBeFalse();
});

test('getDefaultFormat returns first enabled format', function () {
    Setting::create([
        'composer_enabled' => false,
        'npm_enabled' => true,
        'docker_enabled' => true,
    ]);

    expect(PackgridSettings::getDefaultFormat())->toBe(PackageFormat::Npm->value);
});

test('getDefaultPackageType returns first enabled type', function () {
    Setting::create([
        'composer_enabled' => false,
        'npm_enabled' => true,
        'docker_enabled' => true,
    ]);

    expect(PackgridSettings::getDefaultPackageType())->toBe('npm');
});

test('clearCache invalidates cached settings', function () {
    Setting::create([
        'composer_enabled' => true,
        'npm_enabled' => true,
        'docker_enabled' => true,
    ]);

    // First call caches the value
    expect(PackgridSettings::composerEnabled())->toBeTrue();

    // Update directly in DB
    Setting::query()->update(['composer_enabled' => false]);

    // Should still return cached value
    expect(PackgridSettings::composerEnabled())->toBeTrue();

    // Clear cache
    PackgridSettings::clearCache();

    // Should now return updated value
    expect(PackgridSettings::composerEnabled())->toBeFalse();
});

test('settings creates default record if none exists', function () {
    expect(Setting::count())->toBe(0);

    // Calling any method should create a default record
    PackgridSettings::composerEnabled();

    expect(Setting::count())->toBe(1);
    $settings = Setting::first();
    expect($settings->composer_enabled)->toBeTrue()
        ->and($settings->npm_enabled)->toBeTrue()
        ->and($settings->docker_enabled)->toBeTrue();
});
