<?php

namespace App\Services;

use App\Contracts\GitProviderClientInterface;
use App\DTOs\FileContentDto;
use App\DTOs\RefDto;
use App\DTOs\RepositoryInfoDto;
use App\Models\Credential;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GitHubClient implements GitProviderClientInterface
{
    private const API_BASE = 'https://api.github.com';

    public function __construct(private readonly ?Credential $credential = null) {}

    public function testConnection(): array
    {
        return $this->request()->get(self::API_BASE.'/user')->throw()->json();
    }

    public function getRepositoryInfo(string $fullName): RepositoryInfoDto
    {
        $data = $this->request()->get(self::API_BASE.'/repos/'.$fullName)->throw()->json();

        return new RepositoryInfoDto(
            fullName: $fullName,
            name: $data['name'] ?? basename($fullName),
            isPrivate: $data['private'] ?? false,
            defaultBranch: $data['default_branch'] ?? 'main',
        );
    }

    public function listTags(string $fullName): array
    {
        $data = $this->request()->get(self::API_BASE.'/repos/'.$fullName.'/tags')->throw()->json();

        return collect($data)
            ->filter(fn ($t) => isset($t['name'], $t['commit']['sha']))
            ->map(fn ($t) => new RefDto(name: $t['name'], sha: $t['commit']['sha'], type: 'tag'))
            ->values()
            ->all();
    }

    public function listBranches(string $fullName): array
    {
        $data = $this->request()
            ->get(self::API_BASE.'/repos/'.$fullName.'/branches', ['per_page' => 100])
            ->throw()
            ->json();

        return collect($data)
            ->filter(fn ($b) => isset($b['name'], $b['commit']['sha']))
            ->map(fn ($b) => new RefDto(name: $b['name'], sha: $b['commit']['sha'], type: 'branch'))
            ->values()
            ->all();
    }

    public function getFileContent(string $fullName, string $path, string $ref): FileContentDto
    {
        $data = $this->request()
            ->get(self::API_BASE.'/repos/'.$fullName.'/contents/'.$path, ['ref' => $ref])
            ->throw()
            ->json();

        if (! isset($data['content'])) {
            throw new RuntimeException("{$path} not found in {$fullName} at {$ref}");
        }

        return new FileContentDto(
            path: $path,
            content: base64_decode(str_replace("\n", '', $data['content'])),
        );
    }

    public function downloadZip(string $fullName, string $ref): Response
    {
        return $this->request()
            ->withOptions(['stream' => true])
            ->get(self::API_BASE.'/repos/'.$fullName.'/zipball/'.$ref)
            ->throw();
    }

    public function downloadTar(string $fullName, string $ref): Response
    {
        return $this->request()
            ->withOptions(['stream' => true])
            ->get(self::API_BASE.'/repos/'.$fullName.'/tarball/'.$ref)
            ->throw();
    }

    public function getHttpGitCredentials(): ?array
    {
        if (! $this->credential?->token) {
            return null;
        }

        return ['x-access-token', $this->credential->token];
    }

    private function request(): PendingRequest
    {
        $request = Http::withHeaders([
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'Packgrid',
        ]);

        if ($this->credential?->token) {
            $scheme = str_starts_with($this->credential->token, 'github_pat_') ? 'Bearer' : 'token';
            $request = $request->withHeaders(['Authorization' => $scheme.' '.$this->credential->token]);
        }

        return $request;
    }
}
