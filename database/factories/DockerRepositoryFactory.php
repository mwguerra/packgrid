<?php

namespace Database\Factories;

use App\Enums\RepositoryVisibility;
use App\Models\DockerRepository;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DockerRepository>
 */
class DockerRepositoryFactory extends Factory
{
    protected $model = DockerRepository::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->slug(2),
            'visibility' => RepositoryVisibility::PrivateRepo,
            'description' => fake()->sentence(),
            'enabled' => true,
            'total_size' => 0,
            'pull_count' => 0,
            'push_count' => 0,
            'tag_count' => 0,
            'manifest_count' => 0,
            'last_push_at' => null,
            'last_pull_at' => null,
        ];
    }

    public function public(): static
    {
        return $this->state(['visibility' => RepositoryVisibility::PublicRepo]);
    }

    public function private(): static
    {
        return $this->state(['visibility' => RepositoryVisibility::PrivateRepo]);
    }

    public function disabled(): static
    {
        return $this->state(['enabled' => false]);
    }

    public function withStats(int $pulls = 10, int $pushes = 5): static
    {
        return $this->state([
            'pull_count' => $pulls,
            'push_count' => $pushes,
            'last_push_at' => now()->subHours($pushes),
            'last_pull_at' => now()->subHours(1),
        ]);
    }
}
