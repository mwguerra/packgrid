<?php

namespace Database\Factories;

use App\Enums\SyncStatus;
use App\Models\Repository;
use App\Models\SyncLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class SyncLogFactory extends Factory
{
    protected $model = SyncLog::class;

    public function definition(): array
    {
        $startedAt = $this->faker->dateTimeBetween('-2 days', 'now');

        return [
            'repository_id' => Repository::factory(),
            'status' => SyncStatus::Success,
            'started_at' => $startedAt,
            'finished_at' => $this->faker->dateTimeBetween($startedAt, 'now'),
            'error' => null,
        ];
    }

    public function failed(): static
    {
        return $this->state(function () {
            return [
                'status' => SyncStatus::Fail,
                'error' => 'Repository sync failed.',
            ];
        });
    }
}
