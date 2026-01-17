<?php

use App\Filament\Pages\Settings;
use App\Models\Setting;
use App\Models\User;
use App\Support\PackgridSettings;
use Livewire\Livewire;

beforeEach(function () {
    // Clear settings cache before each test
    PackgridSettings::clearCache();
});

test('settings page loads with current values', function () {
    $user = User::factory()->create();

    // Update existing settings or create new
    Setting::query()->delete();
    Setting::create([
        'composer_enabled' => true,
        'npm_enabled' => false,
        'docker_enabled' => true,
    ]);
    PackgridSettings::clearCache();

    Livewire::actingAs($user)
        ->test(Settings::class)
        ->assertOk()
        ->assertFormSet([
            'composer_enabled' => true,
            'npm_enabled' => false,
            'docker_enabled' => true,
        ]);
});

test('settings can be saved successfully', function () {
    $user = User::factory()->create();
    Setting::query()->delete();
    Setting::create([
        'composer_enabled' => true,
        'npm_enabled' => true,
        'docker_enabled' => true,
    ]);
    PackgridSettings::clearCache();

    Livewire::actingAs($user)
        ->test(Settings::class)
        ->fillForm([
            'composer_enabled' => true,
            'npm_enabled' => false,
            'docker_enabled' => true,
        ])
        ->call('save')
        ->assertHasNoErrors();

    $settings = Setting::first();
    expect($settings->npm_enabled)->toBeFalse()
        ->and($settings->composer_enabled)->toBeTrue()
        ->and($settings->docker_enabled)->toBeTrue();
});

test('cannot disable all features', function () {
    $user = User::factory()->create();
    Setting::query()->delete();
    Setting::create([
        'composer_enabled' => true,
        'npm_enabled' => true,
        'docker_enabled' => true,
    ]);
    PackgridSettings::clearCache();

    Livewire::actingAs($user)
        ->test(Settings::class)
        ->fillForm([
            'composer_enabled' => false,
            'npm_enabled' => false,
            'docker_enabled' => false,
        ])
        ->call('save');

    // Settings should remain unchanged because validation failed
    $settings = Setting::first();
    expect($settings->composer_enabled)->toBeTrue();
});

test('settings page is accessible from URL', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/settings')
        ->assertOk();
});

test('settings page requires authentication', function () {
    User::factory()->create();

    $this->get('/admin/settings')
        ->assertRedirect('/admin/login');
});
