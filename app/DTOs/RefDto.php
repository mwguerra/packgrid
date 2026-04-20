<?php

namespace App\DTOs;

readonly class RefDto
{
    public function __construct(
        public string $name,
        public string $sha,
        public string $type, // 'tag' | 'branch'
    ) {}
}
