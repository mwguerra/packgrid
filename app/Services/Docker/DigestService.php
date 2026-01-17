<?php

namespace App\Services\Docker;

use RuntimeException;

class DigestService
{
    private const ALGORITHM = 'sha256';

    public function calculate(string $content): string
    {
        $hash = hash(self::ALGORITHM, $content);

        return self::ALGORITHM.':'.$hash;
    }

    public function calculateFromStream($stream): string
    {
        $context = hash_init(self::ALGORITHM);

        while (! feof($stream)) {
            $chunk = fread($stream, 8192);
            if ($chunk !== false) {
                hash_update($context, $chunk);
            }
        }

        $hash = hash_final($context);

        return self::ALGORITHM.':'.$hash;
    }

    public function calculateFromFile(string $path): string
    {
        if (! file_exists($path)) {
            throw new RuntimeException("File does not exist: {$path}");
        }

        $hash = @hash_file(self::ALGORITHM, $path);

        if ($hash === false) {
            throw new RuntimeException("Failed to calculate digest for file: {$path}");
        }

        return self::ALGORITHM.':'.$hash;
    }

    public function validate(string $digest): bool
    {
        if (! str_contains($digest, ':')) {
            return false;
        }

        [$algorithm, $hash] = explode(':', $digest, 2);

        if ($algorithm !== self::ALGORITHM) {
            return false;
        }

        // SHA256 hash should be 64 hex characters
        return preg_match('/^[a-f0-9]{64}$/', $hash) === 1;
    }

    public function verify(string $content, string $expectedDigest): bool
    {
        $calculatedDigest = $this->calculate($content);

        return hash_equals($calculatedDigest, $expectedDigest);
    }

    public function verifyFile(string $path, string $expectedDigest): bool
    {
        $calculatedDigest = $this->calculateFromFile($path);

        return hash_equals($calculatedDigest, $expectedDigest);
    }

    public function getAlgorithm(): string
    {
        return self::ALGORITHM;
    }

    public function extractHash(string $digest): string
    {
        if (! str_contains($digest, ':')) {
            return $digest;
        }

        [, $hash] = explode(':', $digest, 2);

        return $hash;
    }

    public function normalizeDigest(string $digest): string
    {
        // If digest doesn't have algorithm prefix, add it
        if (! str_contains($digest, ':')) {
            return self::ALGORITHM.':'.$digest;
        }

        return $digest;
    }
}
