<?php

namespace Database\Factories;

use App\Enums\CredentialStatus;
use App\Models\Credential;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CredentialFactory extends Factory
{
    protected $model = Credential::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company(),
            'provider' => 'github',
            'token' => Str::random(40),
            'username' => $this->faker->userName(),
            'status' => CredentialStatus::Unknown,
            'last_checked_at' => null,
            'last_error' => null,
        ];
    }
}
