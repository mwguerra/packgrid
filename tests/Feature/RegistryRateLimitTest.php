<?php

use App\Models\Token;
use App\Services\PackageMetadataStore;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    app(PackageMetadataStore::class)->writePackagesIndex(['packages' => []]);
});

it('returns 429 once the per-minute limit is exceeded', function () {
    config()->set('packgrid.rate_limit.per_minute', 2);
    Token::factory()->create(['token' => 'rate-limited-token-1234567890']);

    $hit = fn () => $this->withBasicAuth('composer', 'rate-limited-token-1234567890')->get('/packages.json');

    $hit()->assertOk();
    $hit()->assertOk();
    $hit()->assertStatus(429);
});

it('does not throttle when the limit is disabled', function () {
    config()->set('packgrid.rate_limit.per_minute', 0);
    Token::factory()->create(['token' => 'unlimited-token-12345678901234']);

    foreach (range(1, 5) as $i) {
        $this->withBasicAuth('composer', 'unlimited-token-12345678901234')
            ->get('/packages.json')
            ->assertOk();
    }
});

it('throttles anonymous public-fallback access by IP', function () {
    // With no tokens configured the registry allows public access, but the
    // throttle still protects the box from scraping/DoS from a single source.
    config()->set('packgrid.rate_limit.per_minute', 2);

    $this->get('/packages.json')->assertOk();
    $this->get('/packages.json')->assertOk();
    $this->get('/packages.json')->assertStatus(429);
});
