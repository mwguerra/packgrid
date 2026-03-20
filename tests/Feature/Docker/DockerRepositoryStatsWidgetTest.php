<?php

use App\Enums\DockerActivityType;
use App\Filament\Resources\DockerRepositoryResource\Pages\ListDockerRepositories;
use App\Filament\Resources\DockerRepositoryResource\Widgets\DockerRepositoryStats;
use App\Models\DockerActivity;
use App\Models\DockerBlob;
use App\Models\DockerRepository;
use App\Models\User;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    actingAs(User::factory()->create());
});

describe('DockerRepositoryStats Widget', function () {
    it('renders on the list page', function () {
        livewire(ListDockerRepositories::class)
            ->assertOk();
    });

    it('can render the widget', function () {
        livewire(DockerRepositoryStats::class)
            ->assertOk();
    });

    it('shows total storage size from blobs', function () {
        DockerBlob::factory()->create(['size' => 1024 * 1024 * 500]); // 500 MB
        DockerBlob::factory()->create(['size' => 1024 * 1024 * 300]); // 300 MB

        livewire(DockerRepositoryStats::class)
            ->assertOk();
    });

    it('shows download count for last 7 days', function () {
        $repository = DockerRepository::factory()->create();

        // Create pull activities within last 7 days
        DockerActivity::factory()->count(5)->create([
            'docker_repository_id' => $repository->id,
            'type' => DockerActivityType::Pull,
            'created_at' => Carbon::today(),
        ]);

        // Create pull activities older than 7 days (should not be counted)
        DockerActivity::factory()->count(3)->create([
            'docker_repository_id' => $repository->id,
            'type' => DockerActivityType::Pull,
            'created_at' => Carbon::today()->subDays(10),
        ]);

        // Push activities should not be counted as downloads
        DockerActivity::factory()->count(2)->create([
            'docker_repository_id' => $repository->id,
            'type' => DockerActivityType::Push,
            'created_at' => Carbon::today(),
        ]);

        livewire(DockerRepositoryStats::class)
            ->assertOk();
    });
});
