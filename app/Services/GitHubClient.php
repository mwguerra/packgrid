<?php

namespace App\Services;

use App\Models\Credential;
use Closure;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GitHubClient
{
    private const API_BASE = 'https://api.github.com';

    public function testCredential(Credential $credential): array
    {
        return $this->request($credential)
            ->get(self::API_BASE.'/user')
            ->throw()
            ->json();
    }

    public function getRepository(string $fullName, ?Credential $credential = null): array
    {
        return $this->remember('gh:repo:'.$this->credKey($credential).':'.$fullName, fn (): array => $this->request($credential)
            ->get(self::API_BASE.'/repos/'.$fullName)
            ->throw()
            ->json());
    }

    public function listTags(string $fullName, ?Credential $credential = null): array
    {
        return $this->remember('gh:tags:'.$this->credKey($credential).':'.$fullName, fn (): array => $this->request($credential)
            ->get(self::API_BASE.'/repos/'.$fullName.'/tags')
            ->throw()
            ->json());
    }

    public function listBranches(string $fullName, ?Credential $credential = null): array
    {
        return $this->remember('gh:branches:'.$this->credKey($credential).':'.$fullName, fn (): array => $this->request($credential)
            ->get(self::API_BASE.'/repos/'.$fullName.'/branches', ['per_page' => 100])
            ->throw()
            ->json());
    }

    public function getBranch(string $fullName, string $branch, ?Credential $credential = null): array
    {
        return $this->remember('gh:branch:'.$this->credKey($credential).':'.$fullName.':'.$branch, fn (): array => $this->request($credential)
            ->get(self::API_BASE.'/repos/'.$fullName.'/branches/'.$branch)
            ->throw()
            ->json());
    }

    public function getComposerJson(string $fullName, string $ref, ?Credential $credential = null): array
    {
        $payload = $this->getFileContent($fullName, 'composer.json', $ref, $credential);

        if (! isset($payload['content'])) {
            throw new RuntimeException('composer.json not found for '.$fullName.' at '.$ref);
        }

        $contents = base64_decode($payload['content']);
        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('composer.json could not be decoded for '.$fullName.' at '.$ref);
        }

        return $decoded;
    }

    public function getFileContent(string $fullName, string $path, string $ref, ?Credential $credential = null): array
    {
        $key = 'gh:contents:'.$this->credKey($credential).':'.$fullName.':'.$ref.':'.sha1($path);

        return $this->remember($key, fn (): array => $this->request($credential)
            ->get(self::API_BASE.'/repos/'.$fullName.'/contents/'.$path, ['ref' => $ref])
            ->throw()
            ->json());
    }

    public function downloadZipball(string $fullName, string $ref, ?Credential $credential = null): \Illuminate\Http\Client\Response
    {
        return $this->request($credential)
            ->withOptions(['stream' => true])
            ->get(self::API_BASE.'/repos/'.$fullName.'/zipball/'.$ref)
            ->throw();
    }

    public function downloadTarball(string $fullName, string $ref, ?Credential $credential = null): \Illuminate\Http\Client\Response
    {
        return $this->request($credential)
            ->withOptions(['stream' => true])
            ->get(self::API_BASE.'/repos/'.$fullName.'/tarball/'.$ref)
            ->throw();
    }

    private function remember(string $key, Closure $fetch): array
    {
        $ttl = (int) config('packgrid.github_cache.ttl', 60);

        return $ttl > 0 ? Cache::remember($key, $ttl, $fetch) : $fetch();
    }

    private function credKey(?Credential $credential): string
    {
        return (string) ($credential?->getKey() ?? 'anon');
    }

    private function request(?Credential $credential): PendingRequest
    {
        $request = Http::withHeaders([
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'Packgrid',
        ]);

        if ($credential?->token) {
            $scheme = str_starts_with($credential->token, 'github_pat_') ? 'Bearer' : 'token';
            $request = $request->withHeaders([
                'Authorization' => $scheme.' '.$credential->token,
            ]);
        }

        return $request;
    }
}
