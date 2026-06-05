<?php

use App\Filament\Resources\Tokens\Pages\CreateToken;
use App\Filament\Resources\Tokens\Pages\ListTokens;
use App\Filament\Resources\Tokens\Pages\ViewToken;
use App\Models\Token;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAs(User::factory()->create());
});

it('creates a token through the panel and stores only its hash', function () {
    livewire(CreateToken::class)
        ->fillForm([
            'name' => 'CI Pipeline',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $token = Token::query()->where('name', 'CI Pipeline')->firstOrFail();

    // The generated value was hashed; the table holds no usable secret.
    expect($token->token_hash)->not->toBeNull()
        ->and(strlen($token->token_hash))->toBe(64);
});

it('does not expose the raw token value on the view page', function () {
    // A known plaintext whose value must never render in the infolist.
    $token = Token::factory()->create(['token' => 'definitely-secret-raw-value-9999']);

    livewire(ViewToken::class, ['record' => $token->getKey()])
        ->assertOk()
        ->assertDontSee('definitely-secret-raw-value-9999');
});

it('rotates a token via the table action, changing its hash', function () {
    $token = Token::factory()->create(['token' => 'rotate-me-via-the-table-123456']);
    $originalHash = $token->token_hash;

    livewire(ListTokens::class)
        ->callTableAction('rotate', $token)
        ->assertHasNoActionErrors();

    expect($token->fresh()->token_hash)->not->toBe($originalHash);
    // The old plaintext no longer resolves to any token.
    expect(Token::findByPlainText('rotate-me-via-the-table-123456'))->toBeNull();
});
