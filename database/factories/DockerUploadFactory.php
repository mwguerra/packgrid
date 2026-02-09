<?php

namespace Database\Factories;

use App\Enums\DockerUploadStatus;
use App\Models\DockerRepository;
use App\Models\DockerUpload;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DockerUpload>
 */
class DockerUploadFactory extends Factory
{
    protected $model = DockerUpload::class;

    public function definition(): array
    {
        return [
            'docker_repository_id' => DockerRepository::factory(),
            'status' => DockerUploadStatus::Pending,
            'temp_path' => storage_path('app/docker-uploads/'.Str::uuid()->toString()),
            'uploaded_bytes' => 0,
            'expected_size' => null,
            'expected_digest' => null,
            'expires_at' => now()->addHours(24),
        ];
    }

    public function forRepository(DockerRepository $repository): static
    {
        return $this->state(['docker_repository_id' => $repository->id]);
    }

    public function pending(): static
    {
        return $this->state(['status' => DockerUploadStatus::Pending]);
    }

    public function uploading(): static
    {
        return $this->state([
            'status' => DockerUploadStatus::Uploading,
            'uploaded_bytes' => fake()->numberBetween(1024, 1024 * 1024),
        ]);
    }

    public function complete(): static
    {
        return $this->state(['status' => DockerUploadStatus::Complete]);
    }

    public function failed(): static
    {
        return $this->state(['status' => DockerUploadStatus::Failed]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subHours(1)]);
    }

    public function withProgress(int $uploaded, int $expected): static
    {
        return $this->state([
            'status' => DockerUploadStatus::Uploading,
            'uploaded_bytes' => $uploaded,
            'expected_size' => $expected,
        ]);
    }
}
