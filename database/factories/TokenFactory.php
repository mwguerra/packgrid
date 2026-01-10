<?php

namespace Database\Factories;

use App\Models\Token;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Token>
 */
class TokenFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'token' => Token::generateToken(),
            'allowed_ips' => null,
            'allowed_domains' => null,
            'enabled' => true,
            'last_used_at' => null,
            'expires_at' => null,
        ];
    }

    public function disabled(): static
    {
        return $this->state(['enabled' => false]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }

    public function withIpRestriction(array $ips): static
    {
        return $this->state(['allowed_ips' => $ips]);
    }

    public function withDomainRestriction(array $domains): static
    {
        return $this->state(['allowed_domains' => $domains]);
    }
}
