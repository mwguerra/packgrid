<?php

use App\Models\Repository;

test('autosync defaults to false and casts to boolean', function () {
    $repo = Repository::factory()->create();

    expect($repo->autosync)->toBeFalse();

    $repo->update(['autosync' => 1]);
    $repo->refresh();

    expect($repo->autosync)->toBeTrue();
});
