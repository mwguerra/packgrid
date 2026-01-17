<?php

namespace Database\Factories;

use App\Enums\DockerMediaType;
use App\Models\DockerManifest;
use App\Models\DockerRepository;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DockerManifest>
 */
class DockerManifestFactory extends Factory
{
    protected $model = DockerManifest::class;

    public function definition(): array
    {
        $content = json_encode([
            'schemaVersion' => 2,
            'mediaType' => DockerMediaType::ManifestV2->value,
            'config' => [
                'mediaType' => DockerMediaType::ContainerConfig->value,
                'digest' => 'sha256:'.fake()->sha256(),
                'size' => 1024,
            ],
            'layers' => [
                [
                    'mediaType' => DockerMediaType::LayerTarGzip->value,
                    'digest' => 'sha256:'.fake()->sha256(),
                    'size' => 10240,
                ],
            ],
        ]);

        return [
            'docker_repository_id' => DockerRepository::factory(),
            'digest' => 'sha256:'.hash('sha256', $content),
            'media_type' => DockerMediaType::ManifestV2,
            'content' => $content,
            'size' => strlen($content),
            'layer_digests' => ['sha256:'.fake()->sha256()],
            'config_digest' => 'sha256:'.fake()->sha256(),
            'architecture' => 'amd64',
            'os' => 'linux',
        ];
    }

    public function forRepository(DockerRepository $repository): static
    {
        return $this->state(['docker_repository_id' => $repository->id]);
    }

    public function manifestV2(): static
    {
        return $this->state(['media_type' => DockerMediaType::ManifestV2]);
    }

    public function ociManifest(): static
    {
        return $this->state(['media_type' => DockerMediaType::OciManifest]);
    }

    public function multiArch(): static
    {
        return $this->state(['media_type' => DockerMediaType::ManifestList]);
    }

    public function withArchitecture(string $arch, string $os = 'linux'): static
    {
        return $this->state([
            'architecture' => $arch,
            'os' => $os,
        ]);
    }
}
