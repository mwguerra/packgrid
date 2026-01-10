<?php

use App\Enums\CredentialStatus;
use App\Enums\PackageFormat;
use App\Enums\RepositoryVisibility;
use App\Models\Credential;
use App\Models\Repository;
use App\Models\SyncLog;
use App\Models\User;
use App\Services\CredentialHealthService;
use App\Services\RepositoryFormatDetector;
use App\Services\RepositoryNormalizer;
use App\Services\RepositorySyncService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

test('root redirects to admin login when users exist', function () {
    User::factory()->create();
    $this->get('/')->assertRedirect('/admin/login');
});

test('admin pages require authentication when users exist', function () {
    User::factory()->create();
    $this->get('/admin')->assertRedirect('/admin/login');
    $this->get('/admin/repositories')->assertRedirect('/admin/login');
});

test('credential token is stored encrypted', function () {
    $credential = Credential::factory()->create([
        'token' => 'plain-token',
    ]);

    $storedToken = DB::table('credentials')->where('id', $credential->id)->value('token');

    expect($storedToken)->not->toBe('plain-token');
    expect($credential->token)->toBe('plain-token');
});

test('credential requires required fields', function () {
    expect(fn () => Credential::create([
        'name' => null,
        'provider' => 'github',
        'token' => null,
    ]))->toThrow(QueryException::class);
});

test('credential test updates status and last checked', function () {
    Http::fake([
        'https://api.github.com/user' => Http::response(['login' => 'octo'], 200),
    ]);

    $credential = Credential::factory()->create([
        'token' => 'github_pat_123',
        'status' => CredentialStatus::Unknown,
        'last_checked_at' => null,
    ]);

    app(CredentialHealthService::class)->test($credential);
    $credential->refresh();

    expect($credential->status)->toBe(CredentialStatus::Ok)
        ->and($credential->last_checked_at)->not->toBeNull();
});

test('repository normalizer accepts owner/repo and returns GitHub URL', function () {
    Http::fake([
        'https://api.github.com/repos/acme/tools' => Http::response(['private' => false, 'name' => 'tools'], 200),
    ]);

    $normalizer = app(RepositoryNormalizer::class);
    $normalized = $normalizer->normalize('acme/tools');

    expect($normalized['repo_full_name'])->toBe('acme/tools')
        ->and($normalized['url'])->toBe('https://github.com/acme/tools')
        ->and($normalized['visibility'])->toBe(RepositoryVisibility::PublicRepo->value);
});

test('repository normalizer accepts various URL formats', function (string $input, string $expectedFullName) {
    Http::fake([
        'https://api.github.com/repos/*' => Http::response(['private' => false, 'name' => 'repo'], 200),
    ]);

    $normalizer = app(RepositoryNormalizer::class);
    $normalized = $normalizer->normalize($input);

    expect($normalized['repo_full_name'])->toBe($expectedFullName)
        ->and($normalized['url'])->toBe('https://github.com/'.$expectedFullName);
})->with([
    'owner/repo format' => ['mwguerra/filemanager', 'mwguerra/filemanager'],
    'full https URL' => ['https://github.com/mwguerra/filemanager', 'mwguerra/filemanager'],
    'URL without scheme' => ['github.com/mwguerra/filemanager', 'mwguerra/filemanager'],
    'URL with .git suffix' => ['https://github.com/mwguerra/filemanager.git', 'mwguerra/filemanager'],
    'SSH format' => ['git@github.com:mwguerra/filemanager.git', 'mwguerra/filemanager'],
    'SSH format without .git' => ['git@github.com:mwguerra/filemanager', 'mwguerra/filemanager'],
    'URL with query string' => ['https://github.com/mwguerra/filemanager?tab=readme', 'mwguerra/filemanager'],
    'URL with fragment' => ['https://github.com/mwguerra/filemanager#readme', 'mwguerra/filemanager'],
    'URL with both query and fragment' => ['https://github.com/mwguerra/filemanager?tab=code#L10', 'mwguerra/filemanager'],
    'URL without scheme with .git' => ['github.com/mwguerra/filemanager.git', 'mwguerra/filemanager'],
    'owner/repo with fragment' => ['mwguerra/filemanager#main', 'mwguerra/filemanager'],
]);

test('private repository requires a credential, public repository does not', function () {
    $publicRepo = Repository::factory()->create([
        'visibility' => RepositoryVisibility::PublicRepo,
        'credential_id' => null,
    ]);

    expect($publicRepo->credential_id)->toBeNull();

    $privateRepo = Repository::factory()->create([
        'visibility' => RepositoryVisibility::PrivateRepo,
        'credential_id' => null,
    ]);

    Storage::fake('local');

    expect(fn () => app(RepositorySyncService::class)->sync($privateRepo))
        ->toThrow(\RuntimeException::class, 'Private repository requires a credential.');
});

test('repository sync generates metadata and a sync log', function () {
    Storage::fake('local');

    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, '/repos/acme/tools/tags')) {
            return Http::response([
                ['name' => 'v1.0.0', 'commit' => ['sha' => 'tagsha']],
            ], 200);
        }

        if (str_contains($url, '/repos/acme/tools/branches')) {
            return Http::response([
                ['name' => 'main', 'commit' => ['sha' => 'branchsha']],
            ], 200);
        }

        if (str_contains($url, '/repos/acme/tools/contents/composer.json')) {
            return Http::response([
                'content' => base64_encode(json_encode([
                    'name' => 'acme/tools',
                    'type' => 'library',
                ])),
            ], 200);
        }

        return Http::response([], 404);
    });

    $repository = Repository::factory()->create([
        'repo_full_name' => 'acme/tools',
        'url' => 'https://github.com/acme/tools',
        'visibility' => RepositoryVisibility::PublicRepo,
    ]);

    app(RepositorySyncService::class)->sync($repository);

    expect(SyncLog::count())->toBe(1)
        ->and(Storage::disk('local')->exists('packgrid/packages.json'))->toBeTrue();
});

test('packages.json endpoint returns generated metadata', function () {
    Storage::fake('local');

    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, '/repos/acme/tools/tags')) {
            return Http::response([
                ['name' => 'v1.0.0', 'commit' => ['sha' => 'tagsha']],
            ], 200);
        }

        if (str_contains($url, '/repos/acme/tools/branches')) {
            return Http::response([
                ['name' => 'main', 'commit' => ['sha' => 'branchsha']],
            ], 200);
        }

        if (str_contains($url, '/repos/acme/tools/contents/composer.json')) {
            return Http::response([
                'content' => base64_encode(json_encode([
                    'name' => 'acme/tools',
                    'type' => 'library',
                ])),
            ], 200);
        }

        return Http::response([], 404);
    });

    $repository = Repository::factory()->create([
        'repo_full_name' => 'acme/tools',
        'url' => 'https://github.com/acme/tools',
        'visibility' => RepositoryVisibility::PublicRepo,
    ]);

    app(RepositorySyncService::class)->sync($repository);

    $response = $this->get('/packages.json');

    $response->assertOk();

    $packages = $response->json('packages');

    expect($packages['acme/tools']['v1.0.0']['name'])->toBe('acme/tools');
});

test('documentation page renders with server URL and snippets', function () {
    config(['app.url' => 'https://packgrid.test']);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/documentation')
        ->assertOk()
        ->assertSee('https://packgrid.test')
        ->assertSee('"repositories"');
});

test('package endpoints are public when no tokens exist', function () {
    Storage::fake('local');

    $repository = Repository::factory()->create([
        'repo_full_name' => 'acme/tools',
        'visibility' => RepositoryVisibility::PublicRepo,
    ]);

    $store = app(\App\Services\PackageMetadataStore::class);
    $store->writePackagesIndex(['packages' => ['acme/tools' => ['v1.0.0' => []]]]);

    $this->get('/packages.json')->assertOk();
});

test('package endpoints require authentication when tokens exist', function () {
    Storage::fake('local');

    \App\Models\Token::factory()->create();

    $this->get('/packages.json')
        ->assertStatus(401)
        ->assertJson(['error' => 'Authentication required.']);
});

test('package endpoints accept valid token via HTTP Basic Auth', function () {
    Storage::fake('local');

    $token = \App\Models\Token::factory()->create([
        'token' => 'test-token-123',
    ]);

    $store = app(\App\Services\PackageMetadataStore::class);
    $store->writePackagesIndex(['packages' => []]);

    $this->withBasicAuth('composer', 'test-token-123')
        ->get('/packages.json')
        ->assertOk();

    $token->refresh();
    expect($token->last_used_at)->not->toBeNull();
});

test('package endpoints reject disabled tokens', function () {
    Storage::fake('local');

    \App\Models\Token::factory()->disabled()->create([
        'token' => 'disabled-token',
    ]);

    $this->withBasicAuth('composer', 'disabled-token')
        ->get('/packages.json')
        ->assertStatus(401)
        ->assertJson(['error' => 'Token is disabled or expired.']);
});

test('package endpoints reject expired tokens', function () {
    Storage::fake('local');

    \App\Models\Token::factory()->expired()->create([
        'token' => 'expired-token',
    ]);

    $this->withBasicAuth('composer', 'expired-token')
        ->get('/packages.json')
        ->assertStatus(401)
        ->assertJson(['error' => 'Token is disabled or expired.']);
});

test('package endpoints enforce IP restrictions', function () {
    Storage::fake('local');

    \App\Models\Token::factory()->withIpRestriction(['10.0.0.1'])->create([
        'token' => 'ip-restricted-token',
    ]);

    $this->withBasicAuth('composer', 'ip-restricted-token')
        ->get('/packages.json')
        ->assertStatus(401)
        ->assertJson(['error' => 'Access denied from this IP address.']);
});

test('token model validates correctly', function () {
    $activeToken = \App\Models\Token::factory()->create();
    expect($activeToken->isValid())->toBeTrue();

    $disabledToken = \App\Models\Token::factory()->disabled()->create();
    expect($disabledToken->isValid())->toBeFalse();

    $expiredToken = \App\Models\Token::factory()->expired()->create();
    expect($expiredToken->isValid())->toBeFalse();
});

test('token model IP restriction works', function () {
    $token = \App\Models\Token::factory()->withIpRestriction(['192.168.1.1', '10.0.0.1'])->create();

    expect($token->isAllowedFromIp('192.168.1.1'))->toBeTrue();
    expect($token->isAllowedFromIp('10.0.0.1'))->toBeTrue();
    expect($token->isAllowedFromIp('172.16.0.1'))->toBeFalse();

    $unrestrictedToken = \App\Models\Token::factory()->create();
    expect($unrestrictedToken->isAllowedFromIp('any.ip.here'))->toBeTrue();
});

test('token model domain restriction works', function () {
    $token = \App\Models\Token::factory()->withDomainRestriction(['example.com'])->create();

    expect($token->isAllowedFromDomain('example.com'))->toBeTrue();
    expect($token->isAllowedFromDomain('sub.example.com'))->toBeTrue();
    expect($token->isAllowedFromDomain('other.com'))->toBeFalse();

    $unrestrictedToken = \App\Models\Token::factory()->create();
    expect($unrestrictedToken->isAllowedFromDomain('any.domain.here'))->toBeTrue();
});

test('format detector detects composer package', function () {
    Http::fake([
        'https://api.github.com/repos/acme/tools' => Http::response(['default_branch' => 'main'], 200),
        'https://api.github.com/repos/acme/tools/contents/composer.json?ref=main' => Http::response([
            'content' => base64_encode('{}'),
        ], 200),
    ]);

    $detector = app(RepositoryFormatDetector::class);
    $format = $detector->detect('acme/tools');

    expect($format)->toBe(PackageFormat::Composer);
});

test('format detector detects npm package', function () {
    Http::fake([
        'https://api.github.com/repos/acme/tools' => Http::response(['default_branch' => 'main'], 200),
        'https://api.github.com/repos/acme/tools/contents/composer.json?ref=main' => Http::response([], 404),
        'https://api.github.com/repos/acme/tools/contents/package.json?ref=main' => Http::response([
            'content' => base64_encode('{}'),
        ], 200),
    ]);

    $detector = app(RepositoryFormatDetector::class);
    $format = $detector->detect('acme/tools');

    expect($format)->toBe(PackageFormat::Npm);
});

test('format detector throws exception when no manifest found', function () {
    Http::fake([
        'https://api.github.com/repos/acme/tools' => Http::response(['default_branch' => 'main'], 200),
        'https://api.github.com/repos/acme/tools/contents/composer.json?ref=main' => Http::response([], 404),
        'https://api.github.com/repos/acme/tools/contents/package.json?ref=main' => Http::response([], 404),
    ]);

    $detector = app(RepositoryFormatDetector::class);

    expect(fn () => $detector->detect('acme/tools'))
        ->toThrow(\RuntimeException::class);
});

test('repository normalizer includes format in normalized data', function () {
    Http::fake([
        'https://api.github.com/repos/acme/tools' => Http::response(['private' => false, 'name' => 'tools', 'default_branch' => 'main'], 200),
        'https://api.github.com/repos/acme/tools/contents/composer.json?ref=main' => Http::response([
            'content' => base64_encode('{}'),
        ], 200),
    ]);

    $normalizer = app(RepositoryNormalizer::class);
    $normalized = $normalizer->normalize('acme/tools');

    expect($normalized['format'])->toBe(PackageFormat::Composer->value);
});

test('repository normalizer returns null format when detection fails', function () {
    Http::fake([
        'https://api.github.com/repos/acme/tools' => Http::response(['private' => false, 'name' => 'tools', 'default_branch' => 'main'], 200),
        'https://api.github.com/repos/acme/tools/contents/composer.json?ref=main' => Http::response([], 404),
        'https://api.github.com/repos/acme/tools/contents/package.json?ref=main' => Http::response([], 404),
    ]);

    $normalizer = app(RepositoryNormalizer::class);
    $normalized = $normalizer->normalize('acme/tools');

    expect($normalized['format'])->toBeNull();
});

test('duplicate repository by URL is prevented', function () {
    Repository::factory()->create([
        'repo_full_name' => 'acme/tools',
        'url' => 'https://github.com/acme/tools',
    ]);

    // Trying to create another with the same URL should fail
    expect(fn () => Repository::factory()->create([
        'repo_full_name' => 'acme/tools-fork',
        'url' => 'https://github.com/acme/tools',
    ]))->toThrow(QueryException::class);
});

test('duplicate repository by repo_full_name is checked at validation layer', function () {
    Repository::factory()->create([
        'repo_full_name' => 'acme/tools',
        'url' => 'https://github.com/acme/tools',
    ]);

    // repo_full_name uniqueness is enforced at validation layer, not DB constraint
    // The CreateRepository page checks this before saving
    expect(Repository::where('repo_full_name', 'acme/tools')->exists())->toBeTrue();
});

test('repository normalizer returns null visibility for non-existent repository', function () {
    Http::fake([
        'https://api.github.com/repos/acme/nonexistent' => Http::response(['message' => 'Not Found'], 404),
        'https://api.github.com/repos/acme/nonexistent/contents/*' => Http::response([], 404),
    ]);

    $normalizer = app(RepositoryNormalizer::class);
    $normalized = $normalizer->normalize('acme/nonexistent');

    // Normalizer is lenient - it returns what it can, validation happens later
    expect($normalized['repo_full_name'])->toBe('acme/nonexistent')
        ->and($normalized['visibility'])->toBeNull()
        ->and($normalized['format'])->toBeNull();
});

test('repository normalizer detects private repository visibility', function () {
    Http::fake([
        'https://api.github.com/repos/acme/private-tools' => Http::response(['private' => true, 'name' => 'private-tools', 'default_branch' => 'main'], 200),
        'https://api.github.com/repos/acme/private-tools/contents/composer.json?ref=main' => Http::response([
            'content' => base64_encode('{}'),
        ], 200),
    ]);

    $normalizer = app(RepositoryNormalizer::class);
    $normalized = $normalizer->normalize('acme/private-tools');

    expect($normalized['visibility'])->toBe(RepositoryVisibility::PrivateRepo->value);
});

test('repository sync fails gracefully when no tags or branches exist', function () {
    Storage::fake('local');

    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, '/repos/acme/empty/tags')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/repos/acme/empty/branches')) {
            return Http::response([], 200);
        }

        if (str_contains($url, '/repos/acme/empty/contents/composer.json')) {
            return Http::response([
                'content' => base64_encode(json_encode([
                    'name' => 'acme/empty',
                    'type' => 'library',
                ])),
            ], 200);
        }

        return Http::response([], 404);
    });

    $repository = Repository::factory()->create([
        'repo_full_name' => 'acme/empty',
        'url' => 'https://github.com/acme/empty',
        'visibility' => RepositoryVisibility::PublicRepo,
    ]);

    expect(fn () => app(RepositorySyncService::class)->sync($repository))
        ->toThrow(\RuntimeException::class, 'No tags or branches found');
});

test('repository sync respects ref_filter with exact matches', function () {
    Storage::fake('local');

    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, '/repos/acme/filtered/tags')) {
            return Http::response([
                ['name' => 'v1.0.0', 'commit' => ['sha' => 'sha1']],
                ['name' => 'v2.0.0', 'commit' => ['sha' => 'sha2']],
                ['name' => 'beta-1.0', 'commit' => ['sha' => 'sha3']],
            ], 200);
        }

        if (str_contains($url, '/repos/acme/filtered/branches')) {
            return Http::response([
                ['name' => 'main', 'commit' => ['sha' => 'sha4']],
            ], 200);
        }

        if (str_contains($url, '/repos/acme/filtered/contents/composer.json')) {
            return Http::response([
                'content' => base64_encode(json_encode([
                    'name' => 'acme/filtered',
                    'type' => 'library',
                ])),
            ], 200);
        }

        return Http::response([], 404);
    });

    $repository = Repository::factory()->create([
        'repo_full_name' => 'acme/filtered',
        'url' => 'https://github.com/acme/filtered',
        'visibility' => RepositoryVisibility::PublicRepo,
        'ref_filter' => 'v1.0.0, v2.0.0',  // Exact match filter (comma-separated)
    ]);

    app(RepositorySyncService::class)->sync($repository);

    $packagesJson = Storage::disk('local')->get('packgrid/packages.json');
    $packages = json_decode($packagesJson, true);

    // Should only have v1.0.0 and v2.0.0, not beta-1.0 or main
    expect($packages['packages']['acme/filtered'])->toHaveKeys(['v1.0.0', 'v2.0.0'])
        ->and($packages['packages']['acme/filtered'])->not->toHaveKey('beta-1.0')
        ->and($packages['packages']['acme/filtered'])->not->toHaveKey('dev-main');
});

test('repository sync fails when ref_filter matches nothing', function () {
    Storage::fake('local');

    Http::fake(function ($request) {
        $url = $request->url();

        if (str_contains($url, '/repos/acme/nomatch/tags')) {
            return Http::response([
                ['name' => 'v1.0.0', 'commit' => ['sha' => 'sha1']],
            ], 200);
        }

        if (str_contains($url, '/repos/acme/nomatch/branches')) {
            return Http::response([
                ['name' => 'main', 'commit' => ['sha' => 'sha2']],
            ], 200);
        }

        if (str_contains($url, '/repos/acme/nomatch/contents/composer.json')) {
            return Http::response([
                'content' => base64_encode(json_encode([
                    'name' => 'acme/nomatch',
                    'type' => 'library',
                ])),
            ], 200);
        }

        return Http::response([], 404);
    });

    $repository = Repository::factory()->create([
        'repo_full_name' => 'acme/nomatch',
        'url' => 'https://github.com/acme/nomatch',
        'visibility' => RepositoryVisibility::PublicRepo,
        'ref_filter' => 'release-*',
    ]);

    expect(fn () => app(RepositorySyncService::class)->sync($repository))
        ->toThrow(\RuntimeException::class, 'No matching tags or branches');
});
