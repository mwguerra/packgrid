<?php

namespace App\DTOs;

readonly class FileContentDto
{
    public function __construct(
        public string $path,
        public string $content, // decoded string, never base64
    ) {}
}
