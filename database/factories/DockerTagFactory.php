<?php

namespace Database\Factories;

use App\Models\DockerManifest;
use App\Models\DockerRepository;
use App\Models\DockerTag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DockerTag>
 */
class DockerTagFactory extends Factory
{
    protected $model = DockerTag::class;

    public function definition(): array
    {
        return [
            'docker_repository_id' => DockerRepository::factory(),
            'docker_manifest_id' => DockerManifest::factory(),
            'name' => 'v'.fake()->unique()->numerify('#.#.#'),
        ];
    }

    public function forRepository(DockerRepository $repository): static
    {
        return $this->state(['docker_repository_id' => $repository->id]);
    }

    public function forManifest(DockerManifest $manifest): static
    {
        return $this->state(['docker_manifest_id' => $manifest->id]);
    }

    public function named(string $name): static
    {
        return $this->state(['name' => $name]);
    }

    public function latest(): static
    {
        return $this->state(['name' => 'latest']);
    }
}
