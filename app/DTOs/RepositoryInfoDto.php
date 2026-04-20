<?php

namespace App\DTOs;

readonly class RepositoryInfoDto
{
    public function __construct(
        public string $fullName,
        public string $name,
        public bool $isPrivate,
        public string $defaultBranch,
    ) {}
}
