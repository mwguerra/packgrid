<?php

use App\Filament\Pages\Auth\Register;
use App\Models\User;
use Illuminate\Support\Facades\DB;

use function Pest\Livewire\livewire;

beforeEach(function () {
    DB::table('users')->delete();
});

test('redirects to registration page when no users exist and visiting login', function () {
    expect(DB::table('users')->count())->toBe(0);

    $this->get('/admin/login')
        ->assertRedirect('/admin/register');
});

test('root redirects to registration when no users exist', function () {
    expect(DB::table('users')->count())->toBe(0);

    $this->get('/')
        ->assertRedirect('/admin/register');
});

test('registration page is accessible when no users exist', function () {
    expect(DB::table('users')->count())->toBe(0);

    $this->get('/admin/register')
        ->assertOk();
});

test('registration page redirects to login when users exist', function () {
    User::factory()->create();
    expect(DB::table('users')->count())->toBe(1);

    $this->get('/admin/register')
        ->assertRedirect('/admin/login');
});

test('login page is accessible when users exist', function () {
    User::factory()->create();
    expect(DB::table('users')->count())->toBe(1);

    $this->get('/admin/login')
        ->assertOk();
});

test('root redirects to login when users exist', function () {
    User::factory()->create();
    expect(DB::table('users')->count())->toBe(1);

    $this->get('/')
        ->assertRedirect('/admin/login');
});

test('can register first user when no users exist', function () {
    expect(DB::table('users')->count())->toBe(0);

    livewire(Register::class)
        ->fillForm([
            'name' => 'First User',
            'email' => 'first@example.com',
            'password' => 'Password123!',
            'passwordConfirmation' => 'Password123!',
        ])
        ->call('register')
        ->assertHasNoFormErrors();

    expect(DB::table('users')->count())->toBe(1);
    expect(User::first()->email)->toBe('first@example.com');
});
