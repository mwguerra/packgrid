<?php

use App\Enums\PackageFormat;
use App\Enums\RepositoryVisibility;
use App\Filament\Resources\RepositoryResource\Pages\CreateRepository;
use App\Filament\Resources\RepositoryResource\Pages\EditRepository;
use App\Filament\Resources\RepositoryResource\Pages\ListRepositories;
use App\Models\Credential;
use App\Models\Repository;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    $user = User::factory()->create();
    actingAs($user);
    Storage::fake('local');
});

// =============================================================================
// CREATE REPOSITORY PAGE TESTS
// =============================================================================

describe('CreateRepository Page', function () {
    it('can load the create page', function () {
        livewire(CreateRepository::class)
            ->assertOk();
    });

    it('has url and credential_id fields on create', function () {
        livewire(CreateRepository::class)
            ->assertFormFieldExists('url')
            ->assertFormFieldExists('credential_id')
            ->assertFormFieldExists('ref_filter')
            ->assertFormFieldExists('enabled');
    });

    it('hides format and visibility fields on create', function () {
        livewire(CreateRepository::class)
            ->assertFormFieldHidden('format')
            ->assertFormFieldHidden('visibility');
    });

    it('can create a valid public Composer repository', function () {
        fakeGitHubForComposerRepo('acme/tools', public: true);

        livewire(CreateRepository::class)
            ->fillForm([
                'url' => 'acme/tools',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        expect(Repository::where('repo_full_name', 'acme/tools')->exists())->toBeTrue();

        $repo = Repository::where('repo_full_name', 'acme/tools')->first();
        expect($repo->format)->toBe(PackageFormat::Composer)
            ->and($repo->visibility)->toBe(RepositoryVisibility::PublicRepo)
            ->and($repo->url)->toBe('https://github.com/acme/tools');
    });

    it('can create a valid public NPM repository', function () {
        fakeGitHubForNpmRepo('@acme/tools', 'acme/npm-tools', public: true);

        livewire(CreateRepository::class)
            ->fillForm([
                'url' => 'acme/npm-tools',
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        expect(Repository::where('repo_full_name', 'acme/npm-tools')->exists())->toBeTrue();

        $repo = Repository::where('repo_full_name', 'acme/npm-tools')->first();
        expect($repo->format)->toBe(PackageFormat::Npm)
            ->and($repo->visibility)->toBe(RepositoryVisibility::PublicRepo);
    });

    it('validates url is required', function () {
        livewire(CreateRepository::class)
            ->fillForm([
                'url' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['url' => 'required']);
    });

    it('validates invalid url format', function () {
        livewire(CreateRepository::class)
            ->fillForm([
                'url' => 'invalid-format',
            ])
            ->call('create')
            ->assertHasFormErrors(['url']);
    });

    it('validates duplicate repository by URL', function () {
        Repository::factory()->create([
            'repo_full_name' => 'acme/tools',
            'url' => 'https://github.com/acme/tools',
        ]);

        fakeGitHubForComposerRepo('acme/tools', public: true);

        livewire(CreateRepository::class)
            ->fillForm([
                'url' => 'acme/tools',
            ])
            ->call('create')
            ->assertHasFormErrors(['url']);
    });

    it('validates repository without manifest files', function () {
        Http::fake([
            'https://api.github.com/repos/acme/empty' => Http::response([
                'private' => false,
                'name' => 'empty',
                'default_branch' => 'main',
            ], 200),
            'https://api.github.com/repos/acme/empty/contents/composer.json?ref=main' => Http::response([], 404),
            'https://api.github.com/repos/acme/empty/contents/package.json?ref=main' => Http::response([], 404),
        ]);

        livewire(CreateRepository::class)
            ->fillForm([
                'url' => 'acme/empty',
            ])
            ->call('create')
            ->assertHasFormErrors(['url']);
    });

    it('validates private repository requires credential', function () {
        Http::fake([
            'https://api.github.com/repos/acme/private' => Http::response([
                'private' => true,
                'name' => 'private',
                'default_branch' => 'main',
            ], 200),
            'https://api.github.com/repos/acme/private/contents/composer.json?ref=main' => Http::response([
                'content' => base64_encode(json_encode(['name' => 'acme/private'])),
            ], 200),
        ]);

        livewire(CreateRepository::class)
            ->fillForm([
                'url' => 'acme/private',
                'credential_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['credential_id']);
    });

    it('can create private repository with credential', function () {
        $credential = Credential::factory()->create();

        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/repos/acme/private-repo') && ! str_contains($url, '/contents/') && ! str_contains($url, '/tags') && ! str_contains($url, '/branches')) {
                return Http::response([
                    'private' => true,
                    'name' => 'private-repo',
                    'default_branch' => 'main',
                ], 200);
            }

            if (str_contains($url, '/contents/composer.json')) {
                return Http::response([
                    'content' => base64_encode(json_encode(['name' => 'acme/private-repo'])),
                ], 200);
            }

            if (str_contains($url, '/tags')) {
                return Http::response([
                    ['name' => 'v1.0.0', 'commit' => ['sha' => 'abc123']],
                ], 200);
            }

            if (str_contains($url, '/branches')) {
                return Http::response([
                    ['name' => 'main', 'commit' => ['sha' => 'def456']],
                ], 200);
            }

            return Http::response([], 404);
        });

        livewire(CreateRepository::class)
            ->fillForm([
                'url' => 'acme/private-repo',
                'credential_id' => $credential->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $repo = Repository::where('repo_full_name', 'acme/private-repo')->first();
        expect($repo)->not->toBeNull()
            ->and($repo->visibility)->toBe(RepositoryVisibility::PrivateRepo)
            ->and($repo->credential_id)->toBe($credential->id);
    });

    it('validates repository with no tags or branches', function () {
        Http::fake([
            'https://api.github.com/repos/acme/empty-refs' => Http::response([
                'private' => false,
                'name' => 'empty-refs',
                'default_branch' => 'main',
            ], 200),
            'https://api.github.com/repos/acme/empty-refs/contents/composer.json?ref=main' => Http::response([
                'content' => base64_encode(json_encode(['name' => 'acme/empty-refs'])),
            ], 200),
            'https://api.github.com/repos/acme/empty-refs/tags*' => Http::response([], 200),
            'https://api.github.com/repos/acme/empty-refs/branches*' => Http::response([], 200),
        ]);

        livewire(CreateRepository::class)
            ->fillForm([
                'url' => 'acme/empty-refs',
            ])
            ->call('create')
            ->assertHasFormErrors(['url']);
    });

    it('validates ref_filter that matches nothing', function () {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/repos/acme/filtered') && ! str_contains($url, '/contents/') && ! str_contains($url, '/tags') && ! str_contains($url, '/branches')) {
                return Http::response([
                    'private' => false,
                    'name' => 'filtered',
                    'default_branch' => 'main',
                ], 200);
            }

            if (str_contains($url, '/contents/composer.json')) {
                return Http::response([
                    'content' => base64_encode(json_encode(['name' => 'acme/filtered'])),
                ], 200);
            }

            if (str_contains($url, '/tags')) {
                return Http::response([
                    ['name' => 'v1.0.0', 'commit' => ['sha' => 'abc123']],
                ], 200);
            }

            if (str_contains($url, '/branches')) {
                return Http::response([
                    ['name' => 'main', 'commit' => ['sha' => 'def456']],
                ], 200);
            }

            return Http::response([], 404);
        });

        livewire(CreateRepository::class)
            ->fillForm([
                'url' => 'acme/filtered',
                'ref_filter' => 'nonexistent-tag',
            ])
            ->call('create')
            ->assertHasFormErrors(['ref_filter']);
    });
});

// =============================================================================
// EDIT REPOSITORY PAGE TESTS
// =============================================================================

describe('EditRepository Page', function () {
    it('can load the edit page', function () {
        $repository = Repository::factory()->create();

        livewire(EditRepository::class, ['record' => $repository->id])
            ->assertOk();
    });

    it('shows format and visibility fields on edit', function () {
        $repository = Repository::factory()->create();

        livewire(EditRepository::class, ['record' => $repository->id])
            ->assertFormFieldVisible('format')
            ->assertFormFieldVisible('visibility');
    });

    it('loads repository data correctly', function () {
        $repository = Repository::factory()->create([
            'url' => 'https://github.com/test/repo',
            'format' => PackageFormat::Composer,
            'visibility' => RepositoryVisibility::PublicRepo,
            'enabled' => true,
        ]);

        livewire(EditRepository::class, ['record' => $repository->id])
            ->assertSchemaStateSet([
                'url' => 'https://github.com/test/repo',
                'format' => PackageFormat::Composer->value,
                'visibility' => RepositoryVisibility::PublicRepo->value,
                'enabled' => true,
            ]);
    });

    it('can update repository format', function () {
        $repository = Repository::factory()->create([
            'format' => PackageFormat::Composer,
        ]);

        livewire(EditRepository::class, ['record' => $repository->id])
            ->fillForm([
                'format' => PackageFormat::Npm->value,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $repository->refresh();
        expect($repository->format)->toBe(PackageFormat::Npm);
    });

    it('can update repository visibility', function () {
        $credential = Credential::factory()->create();
        $repository = Repository::factory()->create([
            'visibility' => RepositoryVisibility::PublicRepo,
            'credential_id' => null,
        ]);

        livewire(EditRepository::class, ['record' => $repository->id])
            ->fillForm([
                'visibility' => RepositoryVisibility::PrivateRepo->value,
                'credential_id' => $credential->id,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $repository->refresh();
        expect($repository->visibility)->toBe(RepositoryVisibility::PrivateRepo)
            ->and($repository->credential_id)->toBe($credential->id);
    });

    it('validates private repository requires credential on edit', function () {
        $repository = Repository::factory()->create([
            'visibility' => RepositoryVisibility::PublicRepo,
            'credential_id' => null,
        ]);

        livewire(EditRepository::class, ['record' => $repository->id])
            ->fillForm([
                'visibility' => RepositoryVisibility::PrivateRepo->value,
                'credential_id' => null,
            ])
            ->call('save')
            ->assertHasFormErrors(['credential_id']);
    });

    it('can toggle repository enabled status', function () {
        $repository = Repository::factory()->create([
            'enabled' => true,
        ]);

        livewire(EditRepository::class, ['record' => $repository->id])
            ->fillForm([
                'enabled' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $repository->refresh();
        expect($repository->enabled)->toBeFalse();
    });
});

// =============================================================================
// LIST REPOSITORIES PAGE TESTS
// =============================================================================

describe('ListRepositories Page', function () {
    it('can load the list page', function () {
        livewire(ListRepositories::class)
            ->assertOk();
    });

    it('displays repositories in the table', function () {
        $repositories = Repository::factory()->count(3)->create();

        livewire(ListRepositories::class)
            ->assertCanSeeTableRecords($repositories);
    });

    it('can search repositories by name', function () {
        $searchTarget = Repository::factory()->create(['name' => 'laravel-permission']);
        $otherRepo = Repository::factory()->create(['name' => 'vue-components']);

        livewire(ListRepositories::class)
            ->searchTable('laravel-permission')
            ->assertCanSeeTableRecords([$searchTarget])
            ->assertCanNotSeeTableRecords([$otherRepo]);
    });

    it('can filter by format', function () {
        $composerRepo = Repository::factory()->create(['format' => PackageFormat::Composer]);
        $npmRepo = Repository::factory()->create(['format' => PackageFormat::Npm]);

        livewire(ListRepositories::class)
            ->filterTable('format', PackageFormat::Composer->value)
            ->assertCanSeeTableRecords([$composerRepo])
            ->assertCanNotSeeTableRecords([$npmRepo]);
    });

    it('can filter by visibility', function () {
        $publicRepo = Repository::factory()->create(['visibility' => RepositoryVisibility::PublicRepo]);
        $privateRepo = Repository::factory()->create(['visibility' => RepositoryVisibility::PrivateRepo]);

        livewire(ListRepositories::class)
            ->filterTable('visibility', RepositoryVisibility::PrivateRepo->value)
            ->assertCanSeeTableRecords([$privateRepo])
            ->assertCanNotSeeTableRecords([$publicRepo]);
    });

    it('renders download count column', function () {
        $repository = Repository::factory()->create(['download_count' => 42]);

        livewire(ListRepositories::class)
            ->assertCanSeeTableRecords([$repository])
            ->assertTableColumnStateSet('download_count', 42, $repository);
    });

    it('can filter by credential', function () {
        $credential = Credential::factory()->create();
        $repoWithCredential = Repository::factory()->create(['credential_id' => $credential->id]);
        $repoWithoutCredential = Repository::factory()->create(['credential_id' => null]);

        livewire(ListRepositories::class)
            ->filterTable('credential', $credential->id)
            ->assertCanSeeTableRecords([$repoWithCredential])
            ->assertCanNotSeeTableRecords([$repoWithoutCredential]);
    });
});

// =============================================================================
// HELPER FUNCTIONS
// =============================================================================

function fakeGitHubForComposerRepo(string $fullName, bool $public = true): void
{
    [$owner, $repo] = explode('/', $fullName);

    Http::fake(function ($request) use ($fullName, $repo, $public) {
        $url = $request->url();

        if (str_contains($url, "/repos/{$fullName}") && ! str_contains($url, '/contents/') && ! str_contains($url, '/tags') && ! str_contains($url, '/branches')) {
            return Http::response([
                'private' => ! $public,
                'name' => $repo,
                'default_branch' => 'main',
            ], 200);
        }

        if (str_contains($url, '/contents/composer.json')) {
            return Http::response([
                'content' => base64_encode(json_encode(['name' => $fullName])),
            ], 200);
        }

        if (str_contains($url, '/tags')) {
            return Http::response([
                ['name' => 'v1.0.0', 'commit' => ['sha' => 'abc123']],
            ], 200);
        }

        if (str_contains($url, '/branches')) {
            return Http::response([
                ['name' => 'main', 'commit' => ['sha' => 'def456']],
            ], 200);
        }

        return Http::response([], 404);
    });
}

function fakeGitHubForNpmRepo(string $packageName, string $fullName, bool $public = true): void
{
    [$owner, $repo] = explode('/', $fullName);

    Http::fake(function ($request) use ($fullName, $packageName, $repo, $public) {
        $url = $request->url();

        if (str_contains($url, "/repos/{$fullName}") && ! str_contains($url, '/contents/') && ! str_contains($url, '/tags') && ! str_contains($url, '/branches')) {
            return Http::response([
                'private' => ! $public,
                'name' => $repo,
                'default_branch' => 'main',
            ], 200);
        }

        if (str_contains($url, '/contents/composer.json')) {
            return Http::response([], 404);
        }

        if (str_contains($url, '/contents/package.json')) {
            return Http::response([
                'content' => base64_encode(json_encode(['name' => $packageName])),
            ], 200);
        }

        if (str_contains($url, '/tags')) {
            return Http::response([
                ['name' => 'v1.0.0', 'commit' => ['sha' => 'abc123']],
            ], 200);
        }

        if (str_contains($url, '/branches')) {
            return Http::response([
                ['name' => 'main', 'commit' => ['sha' => 'def456']],
            ], 200);
        }

        return Http::response([], 404);
    });
}
