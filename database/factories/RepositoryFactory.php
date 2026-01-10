<?php

namespace Database\Factories;

use App\Enums\PackageFormat;
use App\Enums\RepositoryVisibility;
use App\Models\Credential;
use App\Models\Repository;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RepositoryFactory extends Factory
{
    protected $model = Repository::class;

    public function definition(): array
    {
        $owner = $this->faker->userName();
        $repo = $this->faker->slug(2);
        $fullName = $owner.'/'.$repo;

        return [
            'name' => Str::title(str_replace('-', ' ', $repo)),
            'repo_full_name' => $fullName,
            'url' => 'https://github.com/'.$fullName,
            'visibility' => RepositoryVisibility::PublicRepo,
            'format' => PackageFormat::Composer,
            'credential_id' => null,
            'enabled' => true,
            'package_count' => 0,
            'last_sync_at' => null,
            'last_error' => null,
            'ref_filter' => null,
        ];
    }

    public function privateRepo(): static
    {
        return $this->state(function () {
            return [
                'visibility' => RepositoryVisibility::PrivateRepo,
                'credential_id' => Credential::factory(),
            ];
        });
    }

    public function npm(): static
    {
        return $this->state(function () {
            return [
                'format' => PackageFormat::Npm,
            ];
        });
    }
}
