<?php

namespace Database\Seeders;

use App\Enums\CredentialStatus;
use App\Enums\PackageFormat;
use App\Enums\RepositoryVisibility;
use App\Enums\SyncStatus;
use App\Models\Credential;
use App\Models\DockerActivity;
use App\Models\DockerBlob;
use App\Models\DockerManifest;
use App\Models\DockerRepository;
use App\Models\DockerTag;
use App\Models\DownloadLog;
use App\Models\Repository;
use App\Models\Setting;
use App\Models\SyncLog;
use App\Models\Token;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Settings — enable all package formats
        Setting::create([
            'composer_enabled' => true,
            'npm_enabled' => true,
            'docker_enabled' => true,
            'git_enabled' => false,
        ]);

        // Admin user
        $user = User::factory()->create([
            'name' => 'Marcelo Guerra',
            'email' => 'admin@packgrid.dev',
            'password' => bcrypt('password'),
        ]);

        // --- Credentials (GitHub only) ---

        $mainCredential = Credential::factory()->create([
            'name' => 'GitHub Organization',
            'provider' => 'github',
            'username' => 'acme-corp',
            'status' => CredentialStatus::Ok,
            'last_checked_at' => now()->subHours(2),
        ]);

        $personalCredential = Credential::factory()->create([
            'name' => 'GitHub Personal',
            'provider' => 'github',
            'username' => 'mguerra',
            'status' => CredentialStatus::Ok,
            'last_checked_at' => now()->subHours(6),
        ]);

        $revokedCredential = Credential::factory()->create([
            'name' => 'GitHub (Expired)',
            'provider' => 'github',
            'username' => 'old-deploy-bot',
            'status' => CredentialStatus::Fail,
            'last_checked_at' => now()->subDay(),
            'last_error' => 'Bad credentials — token was revoked or expired',
        ]);

        // --- Tokens ---

        $ciToken = Token::factory()->create([
            'name' => 'GitHub Actions',
            'enabled' => true,
            'last_used_at' => now()->subMinutes(8),
            'expires_at' => now()->addMonths(6),
        ]);

        $devToken = Token::factory()->create([
            'name' => 'Local Development',
            'enabled' => true,
            'allowed_ips' => ['127.0.0.1', '10.0.0.0/8'],
            'last_used_at' => now()->subHour(),
            'expires_at' => null,
        ]);

        $prodToken = Token::factory()->create([
            'name' => 'Production Cluster',
            'enabled' => true,
            'allowed_domains' => ['*.acme-corp.com', 'deploy.internal.io'],
            'last_used_at' => now()->subMinutes(3),
            'expires_at' => now()->addYear(),
        ]);

        $stagingToken = Token::factory()->create([
            'name' => 'Staging Environment',
            'enabled' => true,
            'allowed_ips' => ['192.168.1.0/24'],
            'last_used_at' => now()->subHours(4),
            'expires_at' => now()->addMonths(3),
        ]);

        Token::factory()->disabled()->create([
            'name' => 'Old CI Pipeline',
            'last_used_at' => now()->subMonth(),
            'expires_at' => now()->subWeek(),
        ]);

        $allTokens = [$ciToken, $devToken, $prodToken, $stagingToken];

        // --- Composer repositories ---

        $composerRepos = [];

        $composerRepos[] = Repository::factory()->create([
            'name' => 'Laravel Helpers',
            'repo_full_name' => 'acme-corp/laravel-helpers',
            'url' => 'https://github.com/acme-corp/laravel-helpers',
            'visibility' => RepositoryVisibility::PrivateRepo,
            'format' => PackageFormat::Composer,
            'credential_id' => $mainCredential->id,
            'enabled' => true,
            'package_count' => 14,
            'download_count' => 237,
            'last_sync_at' => now()->subMinutes(18),
        ]);

        $composerRepos[] = Repository::factory()->create([
            'name' => 'Payment Gateway SDK',
            'repo_full_name' => 'acme-corp/payment-sdk',
            'url' => 'https://github.com/acme-corp/payment-sdk',
            'visibility' => RepositoryVisibility::PrivateRepo,
            'format' => PackageFormat::Composer,
            'credential_id' => $mainCredential->id,
            'enabled' => true,
            'package_count' => 8,
            'download_count' => 142,
            'last_sync_at' => now()->subHours(1),
        ]);

        $composerRepos[] = Repository::factory()->create([
            'name' => 'Multi-Tenancy Package',
            'repo_full_name' => 'acme-corp/multi-tenancy',
            'url' => 'https://github.com/acme-corp/multi-tenancy',
            'visibility' => RepositoryVisibility::PrivateRepo,
            'format' => PackageFormat::Composer,
            'credential_id' => $mainCredential->id,
            'enabled' => true,
            'package_count' => 6,
            'download_count' => 89,
            'last_sync_at' => now()->subHours(2),
        ]);

        $composerRepos[] = Repository::factory()->create([
            'name' => 'Filament Form Builder',
            'repo_full_name' => 'mguerra/filament-form-builder',
            'url' => 'https://github.com/mguerra/filament-form-builder',
            'visibility' => RepositoryVisibility::PrivateRepo,
            'format' => PackageFormat::Composer,
            'credential_id' => $personalCredential->id,
            'enabled' => true,
            'package_count' => 3,
            'download_count' => 51,
            'last_sync_at' => now()->subHours(4),
        ]);

        $composerRepos[] = Repository::factory()->create([
            'name' => 'Blade Icon Pack',
            'repo_full_name' => 'acme-corp/blade-icons',
            'url' => 'https://github.com/acme-corp/blade-icons',
            'visibility' => RepositoryVisibility::PublicRepo,
            'format' => PackageFormat::Composer,
            'enabled' => true,
            'package_count' => 2,
            'download_count' => 412,
            'last_sync_at' => now()->subMinutes(45),
        ]);

        // --- NPM repositories ---

        $npmRepos = [];

        $npmRepos[] = Repository::factory()->create([
            'name' => 'React Design System',
            'repo_full_name' => 'acme-corp/react-design-system',
            'url' => 'https://github.com/acme-corp/react-design-system',
            'visibility' => RepositoryVisibility::PrivateRepo,
            'format' => PackageFormat::Npm,
            'credential_id' => $mainCredential->id,
            'enabled' => true,
            'package_count' => 22,
            'download_count' => 318,
            'last_sync_at' => now()->subMinutes(30),
        ]);

        $npmRepos[] = Repository::factory()->create([
            'name' => 'Vue Admin Components',
            'repo_full_name' => 'acme-corp/vue-admin-components',
            'url' => 'https://github.com/acme-corp/vue-admin-components',
            'visibility' => RepositoryVisibility::PrivateRepo,
            'format' => PackageFormat::Npm,
            'credential_id' => $mainCredential->id,
            'enabled' => true,
            'package_count' => 15,
            'download_count' => 196,
            'last_sync_at' => now()->subHours(1),
        ]);

        $npmRepos[] = Repository::factory()->create([
            'name' => 'Shared TypeScript Utils',
            'repo_full_name' => 'acme-corp/ts-utils',
            'url' => 'https://github.com/acme-corp/ts-utils',
            'visibility' => RepositoryVisibility::PrivateRepo,
            'format' => PackageFormat::Npm,
            'credential_id' => $mainCredential->id,
            'enabled' => true,
            'package_count' => 9,
            'download_count' => 574,
            'last_sync_at' => now()->subMinutes(55),
        ]);

        $npmRepos[] = Repository::factory()->create([
            'name' => 'Tailwind Preset',
            'repo_full_name' => 'mguerra/tailwind-preset',
            'url' => 'https://github.com/mguerra/tailwind-preset',
            'visibility' => RepositoryVisibility::PublicRepo,
            'format' => PackageFormat::Npm,
            'enabled' => true,
            'package_count' => 4,
            'download_count' => 823,
            'last_sync_at' => now()->subHours(3),
        ]);

        // Disabled / errored repository
        $disabledRepo = Repository::factory()->create([
            'name' => 'Legacy Auth Module',
            'repo_full_name' => 'acme-corp/legacy-auth',
            'url' => 'https://github.com/acme-corp/legacy-auth',
            'visibility' => RepositoryVisibility::PrivateRepo,
            'format' => PackageFormat::Composer,
            'credential_id' => $revokedCredential->id,
            'enabled' => false,
            'package_count' => 1,
            'download_count' => 12,
            'last_sync_at' => now()->subWeek(),
            'last_error' => 'Failed to authenticate with credential',
        ]);

        // --- Sync logs ---

        $allRepos = array_merge($composerRepos, $npmRepos);

        foreach ($allRepos as $repo) {
            SyncLog::factory()->count(3)->create([
                'repository_id' => $repo->id,
                'status' => SyncStatus::Success,
            ]);
        }

        SyncLog::factory()->count(2)->failed()->create([
            'repository_id' => $disabledRepo->id,
        ]);

        SyncLog::factory()->failed()->create([
            'repository_id' => $npmRepos[1]->id,
            'started_at' => now()->subHours(6),
            'finished_at' => now()->subHours(6)->addSeconds(4),
            'error' => 'GitHub API rate limit exceeded. Retry after 14:32 UTC.',
        ]);

        // --- Download logs ---

        foreach ($composerRepos as $repo) {
            DownloadLog::factory()->count(rand(3, 8))->composer()->create([
                'repository_id' => $repo->id,
                'token_id' => $allTokens[array_rand($allTokens)]->id,
            ]);
        }

        foreach ($npmRepos as $repo) {
            DownloadLog::factory()->count(rand(3, 8))->npm()->create([
                'repository_id' => $repo->id,
                'token_id' => $allTokens[array_rand($allTokens)]->id,
            ]);
        }

        // --- Docker repositories ---

        $dockerRepos = [];

        // 1. Web API image
        $apiRepo = DockerRepository::factory()->create([
            'name' => 'acme-corp/api',
            'visibility' => RepositoryVisibility::PrivateRepo,
            'description' => 'Production REST API service',
            'enabled' => true,
            'total_size' => 487 * 1024 * 1024,
            'pull_count' => 342,
            'push_count' => 67,
            'download_count' => 342,
            'tag_count' => 8,
            'manifest_count' => 8,
            'last_push_at' => now()->subHours(3),
            'last_pull_at' => now()->subMinutes(12),
        ]);
        $dockerRepos[] = $apiRepo;

        // 2. Worker image
        $workerRepo = DockerRepository::factory()->create([
            'name' => 'acme-corp/worker',
            'visibility' => RepositoryVisibility::PrivateRepo,
            'description' => 'Background job processor (Laravel Horizon)',
            'enabled' => true,
            'total_size' => 312 * 1024 * 1024,
            'pull_count' => 189,
            'push_count' => 45,
            'download_count' => 189,
            'tag_count' => 5,
            'manifest_count' => 5,
            'last_push_at' => now()->subHours(6),
            'last_pull_at' => now()->subMinutes(45),
        ]);
        $dockerRepos[] = $workerRepo;

        // 3. Frontend image
        $frontendRepo = DockerRepository::factory()->create([
            'name' => 'acme-corp/frontend',
            'visibility' => RepositoryVisibility::PrivateRepo,
            'description' => 'Next.js customer portal',
            'enabled' => true,
            'total_size' => 156 * 1024 * 1024,
            'pull_count' => 278,
            'push_count' => 52,
            'download_count' => 278,
            'tag_count' => 6,
            'manifest_count' => 6,
            'last_push_at' => now()->subHours(1),
            'last_pull_at' => now()->subMinutes(8),
        ]);
        $dockerRepos[] = $frontendRepo;

        // 4. Nginx base image
        $nginxRepo = DockerRepository::factory()->create([
            'name' => 'acme-corp/nginx',
            'visibility' => RepositoryVisibility::PublicRepo,
            'description' => 'Custom Nginx with security headers',
            'enabled' => true,
            'total_size' => 42 * 1024 * 1024,
            'pull_count' => 1204,
            'push_count' => 12,
            'download_count' => 1204,
            'tag_count' => 3,
            'manifest_count' => 3,
            'last_push_at' => now()->subDays(5),
            'last_pull_at' => now()->subHour(),
        ]);
        $dockerRepos[] = $nginxRepo;

        // 5. Disabled legacy image
        $legacyDockerRepo = DockerRepository::factory()->disabled()->create([
            'name' => 'acme-corp/monolith',
            'visibility' => RepositoryVisibility::PrivateRepo,
            'description' => 'Deprecated monolith (migrated to microservices)',
            'total_size' => 1.2 * 1024 * 1024 * 1024,
            'pull_count' => 34,
            'push_count' => 8,
            'download_count' => 34,
            'tag_count' => 2,
            'manifest_count' => 2,
            'last_push_at' => now()->subMonths(2),
            'last_pull_at' => now()->subMonth(),
        ]);
        $dockerRepos[] = $legacyDockerRepo;

        // Docker manifests, tags, blobs, and activity for each active repo
        $repoTagPairs = [
            [$apiRepo, ['latest', 'v2.4.1', 'v2.4.0', 'v2.3.9', 'v2.3.8', 'v2.3.7', 'v2.3.6', 'staging']],
            [$workerRepo, ['latest', 'v1.8.3', 'v1.8.2', 'v1.8.1', 'v1.7.0']],
            [$frontendRepo, ['latest', 'v3.1.0', 'v3.0.2', 'v3.0.1', 'v3.0.0', 'canary']],
            [$nginxRepo, ['latest', '1.27-alpine', '1.26-alpine']],
            [$legacyDockerRepo, ['latest', 'v0.9.0']],
        ];

        foreach ($repoTagPairs as [$repo, $tags]) {
            $blobs = DockerBlob::factory()->count(4)->layer()->create([
                'size' => fake()->numberBetween(8 * 1024 * 1024, 120 * 1024 * 1024),
                'reference_count' => count($tags),
            ]);
            $configBlob = DockerBlob::factory()->config()->create([
                'size' => fake()->numberBetween(2048, 8192),
                'reference_count' => count($tags),
            ]);

            foreach ($blobs as $blob) {
                $repo->blobs()->attach($blob->id);
            }
            $repo->blobs()->attach($configBlob->id);

            foreach ($tags as $tagName) {
                $manifest = DockerManifest::factory()->forRepository($repo)->create([
                    'config_digest' => $configBlob->digest,
                    'layer_digests' => $blobs->pluck('digest')->toArray(),
                    'size' => $blobs->sum('size') + $configBlob->size,
                ]);

                DockerTag::factory()
                    ->forRepository($repo)
                    ->forManifest($manifest)
                    ->named($tagName)
                    ->create();
            }
        }

        // Docker activity logs — spread over past 7 days for chart data
        $activeDockerRepos = [
            [$apiRepo, ['latest', 'v2.4.1', 'v2.4.0', 'staging']],
            [$workerRepo, ['latest', 'v1.8.3', 'v1.8.2']],
            [$frontendRepo, ['latest', 'v3.1.0', 'canary']],
            [$nginxRepo, ['latest', '1.27-alpine']],
        ];

        foreach ($activeDockerRepos as [$repo, $activityTags]) {
            for ($day = 6; $day >= 0; $day--) {
                $pullsPerDay = rand(2, 8);
                for ($i = 0; $i < $pullsPerDay; $i++) {
                    DockerActivity::factory()->pull()->forRepository($repo)->create([
                        'tag' => fake()->randomElement($activityTags),
                        'size' => fake()->numberBetween(20 * 1024 * 1024, 200 * 1024 * 1024),
                        'created_at' => now()->subDays($day)->subMinutes(rand(0, 1440)),
                    ]);
                }

                if (rand(0, 2) > 0) {
                    DockerActivity::factory()->push()->forRepository($repo)->create([
                        'tag' => fake()->randomElement($activityTags),
                        'size' => fake()->numberBetween(40 * 1024 * 1024, 300 * 1024 * 1024),
                        'created_at' => now()->subDays($day)->subMinutes(rand(0, 1440)),
                    ]);
                }
            }
        }

        // Token ↔ Repository access (pivot tables)
        foreach ($allRepos as $repo) {
            $repo->tokens()->attach([$ciToken->id, $prodToken->id, $devToken->id]);
        }

        foreach ($dockerRepos as $repo) {
            if ($repo->enabled) {
                $repo->tokens()->attach([$ciToken->id, $prodToken->id]);
            }
        }
    }
}
