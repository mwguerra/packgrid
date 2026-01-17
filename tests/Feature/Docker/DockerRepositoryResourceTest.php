<?php

use App\Enums\RepositoryVisibility;
use App\Filament\Resources\DockerRepositoryResource\Pages\EditDockerRepository;
use App\Filament\Resources\DockerRepositoryResource\Pages\ListDockerRepositories;
use App\Filament\Resources\DockerRepositoryResource\Pages\ViewDockerRepository;
use App\Models\DockerManifest;
use App\Models\DockerRepository;
use App\Models\DockerTag;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    $user = User::factory()->create();
    actingAs($user);
    Storage::fake('local');
});

// =============================================================================
// LIST DOCKER REPOSITORIES PAGE TESTS
// =============================================================================

describe('ListDockerRepositories Page', function () {
    it('can load the list page', function () {
        livewire(ListDockerRepositories::class)
            ->assertOk();
    });

    it('displays docker repositories in the table', function () {
        $repositories = DockerRepository::factory()->count(3)->create();

        livewire(ListDockerRepositories::class)
            ->assertCanSeeTableRecords($repositories);
    });

    it('can search repositories by name', function () {
        $searchTarget = DockerRepository::factory()->create(['name' => 'myorg/myapp']);
        $otherRepo = DockerRepository::factory()->create(['name' => 'other/repo']);

        livewire(ListDockerRepositories::class)
            ->searchTable('myorg/myapp')
            ->assertCanSeeTableRecords([$searchTarget])
            ->assertCanNotSeeTableRecords([$otherRepo]);
    });

    it('can filter by visibility', function () {
        $publicRepo = DockerRepository::factory()->public()->create();
        $privateRepo = DockerRepository::factory()->private()->create();

        livewire(ListDockerRepositories::class)
            ->filterTable('visibility', RepositoryVisibility::PrivateRepo->value)
            ->assertCanSeeTableRecords([$privateRepo])
            ->assertCanNotSeeTableRecords([$publicRepo]);
    });

    it('displays tag count badge', function () {
        $repository = DockerRepository::factory()->create(['name' => 'myorg/myapp']);
        $manifest = DockerManifest::factory()->forRepository($repository)->create();
        DockerTag::factory()->count(3)->forRepository($repository)->forManifest($manifest)->create();

        $repository->updateStatistics();

        livewire(ListDockerRepositories::class)
            ->assertSee('3');
    });
});

// =============================================================================
// EDIT DOCKER REPOSITORY PAGE TESTS
// =============================================================================

describe('EditDockerRepository Page', function () {
    it('can load the edit page', function () {
        $repository = DockerRepository::factory()->create();

        livewire(EditDockerRepository::class, ['record' => $repository->id])
            ->assertOk();
    });

    it('loads repository data correctly', function () {
        $repository = DockerRepository::factory()->create([
            'name' => 'myorg/myapp',
            'visibility' => RepositoryVisibility::PublicRepo,
            'description' => 'Test description',
            'enabled' => true,
        ]);

        livewire(EditDockerRepository::class, ['record' => $repository->id])
            ->assertFormSet([
                'name' => 'myorg/myapp',
                'visibility' => RepositoryVisibility::PublicRepo->value,
                'description' => 'Test description',
                'enabled' => true,
            ]);
    });

    it('can update repository visibility', function () {
        $repository = DockerRepository::factory()->public()->create();

        livewire(EditDockerRepository::class, ['record' => $repository->id])
            ->fillForm([
                'visibility' => RepositoryVisibility::PrivateRepo->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $repository->refresh();
        expect($repository->visibility)->toBe(RepositoryVisibility::PrivateRepo);
    });

    it('can update repository description', function () {
        $repository = DockerRepository::factory()->create([
            'description' => 'Old description',
        ]);

        livewire(EditDockerRepository::class, ['record' => $repository->id])
            ->fillForm([
                'description' => 'New description',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $repository->refresh();
        expect($repository->description)->toBe('New description');
    });

    it('can toggle repository enabled status', function () {
        $repository = DockerRepository::factory()->create(['enabled' => true]);

        livewire(EditDockerRepository::class, ['record' => $repository->id])
            ->fillForm([
                'enabled' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $repository->refresh();
        expect($repository->enabled)->toBeFalse();
    });

    it('validates unique name on edit', function () {
        $existing = DockerRepository::factory()->create(['name' => 'existing/repo']);
        $repository = DockerRepository::factory()->create(['name' => 'other/repo']);

        livewire(EditDockerRepository::class, ['record' => $repository->id])
            ->fillForm([
                'name' => 'existing/repo',
            ])
            ->call('save')
            ->assertHasFormErrors(['name']);
    });

    it('allows keeping same name on edit', function () {
        $repository = DockerRepository::factory()->create(['name' => 'myorg/myapp']);

        livewire(EditDockerRepository::class, ['record' => $repository->id])
            ->fillForm([
                'name' => 'myorg/myapp',
                'description' => 'Updated description',
            ])
            ->call('save')
            ->assertHasNoFormErrors();
    });
});

// =============================================================================
// VIEW DOCKER REPOSITORY PAGE TESTS
// =============================================================================

describe('ViewDockerRepository Page', function () {
    it('can load the view page', function () {
        $repository = DockerRepository::factory()->create();

        livewire(ViewDockerRepository::class, ['record' => $repository->id])
            ->assertOk();
    });

    it('displays repository information', function () {
        $repository = DockerRepository::factory()->create([
            'name' => 'myorg/myapp',
            'visibility' => RepositoryVisibility::PublicRepo,
            'description' => 'My test repository',
        ]);

        livewire(ViewDockerRepository::class, ['record' => $repository->id])
            ->assertSee('myorg/myapp')
            ->assertSee('My test repository');
    });

    it('displays statistics', function () {
        $repository = DockerRepository::factory()->create([
            'tag_count' => 5,
            'manifest_count' => 3,
            'pull_count' => 100,
            'push_count' => 50,
            'total_size' => 1024 * 1024 * 100, // 100MB
        ]);

        livewire(ViewDockerRepository::class, ['record' => $repository->id])
            ->assertSee('5') // tag count
            ->assertSee('3') // manifest count
            ->assertSee('100') // pull count
            ->assertSee('50'); // push count
    });
});

// =============================================================================
// DELETE DOCKER REPOSITORY TESTS
// =============================================================================

describe('DeleteDockerRepository', function () {
    it('can delete a docker repository from list', function () {
        $repository = DockerRepository::factory()->create();

        livewire(ListDockerRepositories::class)
            ->callTableAction('delete', $repository)
            ->assertHasNoActionErrors();

        expect(DockerRepository::find($repository->id))->toBeNull();
    });
});

// =============================================================================
// TOGGLE COLUMN TESTS
// =============================================================================

describe('DockerRepository Toggle Columns', function () {
    it('renders enabled toggle column', function () {
        DockerRepository::factory()->create(['enabled' => true]);

        livewire(ListDockerRepositories::class)
            ->assertTableColumnExists('enabled');
    });
});
