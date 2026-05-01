<?php

use App\DTOs\FileContentDto;
use App\DTOs\RefDto;
use App\DTOs\RepositoryInfoDto;

test('RepositoryInfoDto holds expected values', function () {
    $dto = new RepositoryInfoDto(
        fullName: 'acme/my-lib',
        name: 'my-lib',
        isPrivate: true,
        defaultBranch: 'main',
    );

    expect($dto->fullName)->toBe('acme/my-lib')
        ->and($dto->name)->toBe('my-lib')
        ->and($dto->isPrivate)->toBeTrue()
        ->and($dto->defaultBranch)->toBe('main');
});

test('RefDto holds expected values', function () {
    $dto = new RefDto(name: 'v1.2.0', sha: 'abc123', type: 'tag');

    expect($dto->name)->toBe('v1.2.0')
        ->and($dto->sha)->toBe('abc123')
        ->and($dto->type)->toBe('tag');
});

test('FileContentDto holds decoded content', function () {
    $dto = new FileContentDto(path: 'composer.json', content: '{"name":"acme/pkg"}');

    expect($dto->path)->toBe('composer.json')
        ->and($dto->content)->toBe('{"name":"acme/pkg"}');
});
