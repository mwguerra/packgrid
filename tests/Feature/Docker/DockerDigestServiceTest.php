<?php

use App\Services\Docker\DigestService;

beforeEach(function () {
    $this->digestService = app(DigestService::class);
});

// =============================================================================
// DIGEST CALCULATION TESTS
// =============================================================================

describe('DigestService Calculation', function () {
    it('calculates correct sha256 digest for string content', function () {
        $content = 'Hello, World!';
        $expectedHash = hash('sha256', $content);

        $digest = $this->digestService->calculate($content);

        expect($digest)->toBe("sha256:{$expectedHash}");
    });

    it('calculates correct digest from stream', function () {
        $content = 'Stream content for testing';
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $digest = $this->digestService->calculateFromStream($stream);

        fclose($stream);

        expect($digest)->toBe('sha256:'.hash('sha256', $content));
    });

    it('calculates correct digest from file', function () {
        $content = 'File content for testing';
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, $content);

        $digest = $this->digestService->calculateFromFile($tempFile);

        unlink($tempFile);

        expect($digest)->toBe('sha256:'.hash('sha256', $content));
    });

    it('throws exception for non-existent file', function () {
        expect(fn () => $this->digestService->calculateFromFile('/non/existent/file'))
            ->toThrow(RuntimeException::class, 'File does not exist');
    });
});

// =============================================================================
// DIGEST VALIDATION TESTS
// =============================================================================

describe('DigestService Validation', function () {
    it('validates correct sha256 digest format', function () {
        $validDigest = 'sha256:'.str_repeat('a', 64);

        expect($this->digestService->validate($validDigest))->toBeTrue();
    });

    it('rejects digest without colon separator', function () {
        $invalidDigest = 'sha256'.str_repeat('a', 64);

        expect($this->digestService->validate($invalidDigest))->toBeFalse();
    });

    it('rejects digest with wrong algorithm', function () {
        $invalidDigest = 'md5:'.str_repeat('a', 32);

        expect($this->digestService->validate($invalidDigest))->toBeFalse();
    });

    it('rejects digest with invalid hash length', function () {
        $invalidDigest = 'sha256:'.str_repeat('a', 32);

        expect($this->digestService->validate($invalidDigest))->toBeFalse();
    });

    it('rejects digest with invalid characters', function () {
        $invalidDigest = 'sha256:'.str_repeat('g', 64);

        expect($this->digestService->validate($invalidDigest))->toBeFalse();
    });
});

// =============================================================================
// DIGEST VERIFICATION TESTS
// =============================================================================

describe('DigestService Verification', function () {
    it('verifies content matches digest', function () {
        $content = 'Verify this content';
        $digest = 'sha256:'.hash('sha256', $content);

        expect($this->digestService->verify($content, $digest))->toBeTrue();
    });

    it('fails verification for mismatched content', function () {
        $content = 'Original content';
        $digest = 'sha256:'.hash('sha256', 'Different content');

        expect($this->digestService->verify($content, $digest))->toBeFalse();
    });

    it('verifies file matches digest', function () {
        $content = 'File verification content';
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, $content);
        $digest = 'sha256:'.hash('sha256', $content);

        $result = $this->digestService->verifyFile($tempFile, $digest);

        unlink($tempFile);

        expect($result)->toBeTrue();
    });

    it('fails file verification for mismatched content', function () {
        $content = 'File content';
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, $content);
        $wrongDigest = 'sha256:'.hash('sha256', 'Different content');

        $result = $this->digestService->verifyFile($tempFile, $wrongDigest);

        unlink($tempFile);

        expect($result)->toBeFalse();
    });
});

// =============================================================================
// UTILITY METHODS TESTS
// =============================================================================

describe('DigestService Utilities', function () {
    it('returns correct algorithm name', function () {
        expect($this->digestService->getAlgorithm())->toBe('sha256');
    });

    it('extracts hash from digest', function () {
        $hash = str_repeat('a', 64);
        $digest = "sha256:{$hash}";

        expect($this->digestService->extractHash($digest))->toBe($hash);
    });

    it('returns hash unchanged if no algorithm prefix', function () {
        $hash = str_repeat('a', 64);

        expect($this->digestService->extractHash($hash))->toBe($hash);
    });

    it('normalizes hash to full digest format', function () {
        $hash = str_repeat('a', 64);

        expect($this->digestService->normalizeDigest($hash))->toBe("sha256:{$hash}");
    });

    it('keeps already normalized digest unchanged', function () {
        $digest = 'sha256:'.str_repeat('a', 64);

        expect($this->digestService->normalizeDigest($digest))->toBe($digest);
    });
});
