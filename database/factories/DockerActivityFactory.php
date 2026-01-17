<?php

namespace Database\Factories;

use App\Enums\DockerActivityType;
use App\Models\DockerActivity;
use App\Models\DockerRepository;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DockerActivity>
 */
class DockerActivityFactory extends Factory
{
    protected $model = DockerActivity::class;

    public function definition(): array
    {
        return [
            'docker_repository_id' => DockerRepository::factory(),
            'type' => DockerActivityType::Pull,
            'tag' => fake()->randomElement(['latest', 'v1.0.0', 'v2.0.0', null]),
            'digest' => 'sha256:'.fake()->sha256(),
            'size' => fake()->numberBetween(1024, 1024 * 1024 * 100),
            'client_ip' => fake()->ipv4(),
            'user_agent' => 'docker/'.fake()->semver(),
        ];
    }

    public function forRepository(DockerRepository $repository): static
    {
        return $this->state(['docker_repository_id' => $repository->id]);
    }

    public function push(): static
    {
        return $this->state(['type' => DockerActivityType::Push]);
    }

    public function pull(): static
    {
        return $this->state(['type' => DockerActivityType::Pull]);
    }

    public function delete(): static
    {
        return $this->state(['type' => DockerActivityType::Delete]);
    }

    public function mount(): static
    {
        return $this->state(['type' => DockerActivityType::Mount]);
    }

    public function withTag(string $tag): static
    {
        return $this->state(['tag' => $tag]);
    }
}
