<?php

namespace Database\Factories;

use App\Enums\DockerMediaType;
use App\Models\DockerBlob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DockerBlob>
 */
class DockerBlobFactory extends Factory
{
    protected $model = DockerBlob::class;

    public function definition(): array
    {
        $content = fake()->sha256();

        return [
            'digest' => 'sha256:'.hash('sha256', $content),
            'size' => fake()->numberBetween(1024, 1024 * 1024 * 100),
            'media_type' => DockerMediaType::LayerTarGzip,
            'storage_path' => 'docker/blobs/'.substr(hash('sha256', $content), 0, 2).'/'.hash('sha256', $content),
            'reference_count' => 0,
        ];
    }

    public function withDigest(string $digest): static
    {
        $hash = str_contains($digest, ':') ? explode(':', $digest)[1] : $digest;

        return $this->state([
            'digest' => 'sha256:'.$hash,
            'storage_path' => 'docker/blobs/'.substr($hash, 0, 2).'/'.$hash,
        ]);
    }

    public function config(): static
    {
        return $this->state(['media_type' => DockerMediaType::ContainerConfig]);
    }

    public function layer(): static
    {
        return $this->state(['media_type' => DockerMediaType::LayerTarGzip]);
    }

    public function ociLayer(): static
    {
        return $this->state(['media_type' => DockerMediaType::OciLayerTarGzip]);
    }

    public function withReferenceCount(int $count): static
    {
        return $this->state(['reference_count' => $count]);
    }

    public function orphaned(): static
    {
        return $this->state(['reference_count' => 0]);
    }
}
