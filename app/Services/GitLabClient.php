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

class GitLabClient implements GitProviderClientInterface
{
    private string $apiBase;

    public function __construct(private readonly ?Credential $credential = null)
    {
        $baseUrl = rtrim($credential?->base_url ?? 'https://gitlab.com', '/');
        $this->apiBase = $baseUrl.'/api/v4';
    }

    public function testConnection(): array
    {
        return $this->request()->get($this->apiBase.'/user')->throw()->json();
    }

    public function getRepositoryInfo(string $fullName): RepositoryInfoDto
    {
        $data = $this->request()
            ->get($this->apiBase.'/projects/'.$this->encode($fullName))
            ->throw()
            ->json();

        return new RepositoryInfoDto(
            fullName: $fullName,
            name: $data['name'],
            isPrivate: ($data['visibility'] ?? 'private') !== 'public',
            defaultBranch: $data['default_branch'] ?? 'main',
        );
    }

    public function listTags(string $fullName): array
    {
        $data = $this->request()
            ->get($this->apiBase.'/projects/'.$this->encode($fullName).'/repository/tags')
            ->throw()
            ->json();

        return collect($data)
            ->filter(fn ($t) => isset($t['name'], $t['commit']['id']))
            ->map(fn ($t) => new RefDto(name: $t['name'], sha: $t['commit']['id'], type: 'tag'))
            ->values()
            ->all();
    }

    public function listBranches(string $fullName): array
    {
        $data = $this->request()
            ->get($this->apiBase.'/projects/'.$this->encode($fullName).'/repository/branches')
            ->throw()
            ->json();

        return collect($data)
            ->filter(fn ($b) => isset($b['name'], $b['commit']['id']))
            ->map(fn ($b) => new RefDto(name: $b['name'], sha: $b['commit']['id'], type: 'branch'))
            ->values()
            ->all();
    }

    public function getFileContent(string $fullName, string $path, string $ref): FileContentDto
    {
        $data = $this->request()
            ->get(
                $this->apiBase.'/projects/'.$this->encode($fullName).'/repository/files/'.urlencode($path),
                ['ref' => $ref]
            )
            ->throw()
            ->json();

        if (! isset($data['content'])) {
            throw new RuntimeException("{$path} not found in {$fullName} at {$ref}");
        }

        return new FileContentDto(
            path: $path,
            content: base64_decode($data['content']),
        );
    }

    public function downloadZip(string $fullName, string $ref): Response
    {
        return $this->request()
            ->withOptions(['stream' => true])
            ->get(
                $this->apiBase.'/projects/'.$this->encode($fullName).'/repository/archive.zip',
                ['sha' => $ref]
            )
            ->throw();
    }

    public function downloadTar(string $fullName, string $ref): Response
    {
        return $this->request()
            ->withOptions(['stream' => true])
            ->get(
                $this->apiBase.'/projects/'.$this->encode($fullName).'/repository/archive.tar.gz',
                ['sha' => $ref]
            )
            ->throw();
    }

    public function getHttpGitCredentials(): ?array
    {
        if (! $this->credential?->token) {
            return null;
        }

        return ['oauth2', $this->credential->token];
    }

    private function encode(string $fullName): string
    {
        return urlencode($fullName);
    }

    private function request(): PendingRequest
    {
        $request = Http::withHeaders(['User-Agent' => 'Packgrid']);

        if ($this->credential?->token) {
            $request = $request->withHeaders(['PRIVATE-TOKEN' => $this->credential->token]);
        }

        return $request;
    }
}
