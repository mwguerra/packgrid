<?php

namespace Database\Seeders;

use App\Enums\CredentialStatus;
use App\Enums\PackageFormat;
use App\Enums\RepositoryVisibility;
use App\Enums\SyncStatus;
use App\Models\Credential;
use App\Models\Repository;
use App\Models\SyncLog;
use App\Models\Token;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create test user
        $user = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@packgrid.dev',
            'password' => bcrypt('password'),
        ]);

        // Create credentials with various statuses
        $githubCredential = Credential::factory()->create([
            'name' => 'GitHub Personal',
            'provider' => 'github',
            'username' => 'demo-user',
            'status' => CredentialStatus::Ok,
            'last_checked_at' => now()->subHours(2),
        ]);

        $failedCredential = Credential::factory()->create([
            'name' => 'Old GitHub Token',
            'provider' => 'github',
            'username' => 'old-account',
            'status' => CredentialStatus::Fail,
            'last_checked_at' => now()->subDay(),
            'last_error' => 'Token has expired or been revoked',
        ]);

        // Create public Composer repositories
        $laravelRepo = Repository::factory()->create([
            'name' => 'Laravel Framework',
            'repo_full_name' => 'laravel/framework',
            'url' => 'https://github.com/laravel/framework',
            'visibility' => RepositoryVisibility::PublicRepo,
            'format' => PackageFormat::Composer,
            'enabled' => true,
            'package_count' => 1,
            'last_sync_at' => now()->subMinutes(30),
        ]);

        $filamentRepo = Repository::factory()->create([
            'name' => 'Filament Admin',
            'repo_full_name' => 'filamentphp/filament',
            'url' => 'https://github.com/filamentphp/filament',
            'visibility' => RepositoryVisibility::PublicRepo,
            'format' => PackageFormat::Composer,
            'enabled' => true,
            'package_count' => 12,
            'last_sync_at' => now()->subHours(1),
        ]);

        // Create private Composer repository
        $privateComposerRepo = Repository::factory()->create([
            'name' => 'Internal PHP Utils',
            'repo_full_name' => 'acme-corp/php-utils',
            'url' => 'https://github.com/acme-corp/php-utils',
            'visibility' => RepositoryVisibility::PrivateRepo,
            'format' => PackageFormat::Composer,
            'credential_id' => $githubCredential->id,
            'enabled' => true,
            'package_count' => 3,
            'last_sync_at' => now()->subHours(2),
        ]);

        // Create npm repositories
        $reactRepo = Repository::factory()->create([
            'name' => 'React Components',
            'repo_full_name' => 'acme-corp/react-components',
            'url' => 'https://github.com/acme-corp/react-components',
            'visibility' => RepositoryVisibility::PrivateRepo,
            'format' => PackageFormat::Npm,
            'credential_id' => $githubCredential->id,
            'enabled' => true,
            'package_count' => 5,
            'last_sync_at' => now()->subMinutes(45),
        ]);

        $vueRepo = Repository::factory()->create([
            'name' => 'Vue Design System',
            'repo_full_name' => 'acme-corp/vue-design-system',
            'url' => 'https://github.com/acme-corp/vue-design-system',
            'visibility' => RepositoryVisibility::PrivateRepo,
            'format' => PackageFormat::Npm,
            'credential_id' => $githubCredential->id,
            'enabled' => true,
            'package_count' => 8,
            'last_sync_at' => now()->subHours(3),
        ]);

        // Create a disabled repository
        $disabledRepo = Repository::factory()->create([
            'name' => 'Legacy API Client',
            'repo_full_name' => 'acme-corp/legacy-api',
            'url' => 'https://github.com/acme-corp/legacy-api',
            'visibility' => RepositoryVisibility::PrivateRepo,
            'format' => PackageFormat::Composer,
            'credential_id' => $failedCredential->id,
            'enabled' => false,
            'package_count' => 1,
            'last_sync_at' => now()->subWeek(),
            'last_error' => 'Failed to authenticate with credential',
        ]);

        // Create tokens
        Token::factory()->create([
            'name' => 'CI/CD Pipeline',
            'enabled' => true,
            'last_used_at' => now()->subMinutes(15),
            'expires_at' => now()->addMonths(6),
        ]);

        Token::factory()->create([
            'name' => 'Local Development',
            'enabled' => true,
            'allowed_ips' => ['127.0.0.1', '192.168.1.0/24'],
            'last_used_at' => now()->subHours(1),
            'expires_at' => null,
        ]);

        Token::factory()->create([
            'name' => 'Production Server',
            'enabled' => true,
            'allowed_domains' => ['*.acme-corp.com', 'deploy.internal'],
            'last_used_at' => now()->subMinutes(5),
            'expires_at' => now()->addYear(),
        ]);

        Token::factory()->disabled()->create([
            'name' => 'Deprecated Token',
            'last_used_at' => now()->subMonth(),
        ]);

        // Create sync logs for various repositories
        $repositories = [$laravelRepo, $filamentRepo, $privateComposerRepo, $reactRepo, $vueRepo];

        foreach ($repositories as $repo) {
            // Create successful syncs
            SyncLog::factory()->count(3)->create([
                'repository_id' => $repo->id,
                'status' => SyncStatus::Success,
            ]);
        }

        // Create some failed syncs for the disabled repo
        SyncLog::factory()->count(2)->failed()->create([
            'repository_id' => $disabledRepo->id,
        ]);

        // Create a recent failed sync for one active repo
        SyncLog::factory()->failed()->create([
            'repository_id' => $vueRepo->id,
            'started_at' => now()->subHours(4),
            'finished_at' => now()->subHours(4)->addSeconds(5),
            'error' => 'Rate limit exceeded. Please try again later.',
        ]);
    }
}
