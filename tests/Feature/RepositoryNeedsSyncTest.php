<?php

use App\Models\Repository;

test('needsSync uses the configured freshness window', function () {
    config(['packgrid.autosync.fresh_seconds' => 120]);

    $repo = Repository::factory()->make(['last_sync_at' => now()->subSeconds(90)]);
    expect($repo->needsSync())->toBeFalse(); // 90s < 120s window

    $repo->last_sync_at = now()->subSeconds(150);
    expect($repo->needsSync())->toBeTrue(); // 150s > 120s window
});

test('needsSync defaults to a 60 second window', function () {
    $repo = Repository::factory()->make(['last_sync_at' => now()->subSeconds(90)]);
    expect($repo->needsSync())->toBeTrue();

    $repo->last_sync_at = null;
    expect($repo->needsSync())->toBeTrue();
});
