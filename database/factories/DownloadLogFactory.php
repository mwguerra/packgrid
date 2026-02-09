<?php

namespace Database\Factories;

use App\Enums\PackageFormat;
use App\Models\DownloadLog;
use App\Models\Repository;
use App\Models\Token;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DownloadLog>
 */
class DownloadLogFactory extends Factory
{
    protected $model = DownloadLog::class;

    public function definition(): array
    {
        return [
            'repository_id' => Repository::factory(),
            'token_id' => null,
            'package_version' => 'v'.fake()->semver(),
            'format' => PackageFormat::Composer,
            'client_ip' => fake()->ipv4(),
            'user_agent' => 'Composer/'.fake()->semver(),
        ];
    }

    public function npm(): static
    {
        return $this->state([
            'format' => PackageFormat::Npm,
            'user_agent' => 'npm/'.fake()->semver(),
        ]);
    }

    public function composer(): static
    {
        return $this->state([
            'format' => PackageFormat::Composer,
            'user_agent' => 'Composer/'.fake()->semver(),
        ]);
    }

    public function withToken(?Token $token = null): static
    {
        return $this->state([
            'token_id' => $token ?? Token::factory(),
        ]);
    }

    public function forRepository(Repository $repository): static
    {
        return $this->state(['repository_id' => $repository->id]);
    }
}
