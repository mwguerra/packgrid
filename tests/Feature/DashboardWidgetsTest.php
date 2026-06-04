<?php

use App\Enums\CredentialStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\AttentionRequired;
use App\Filament\Widgets\OnboardingChecklist;
use App\Filament\Widgets\PackgridStats;
use App\Filament\Widgets\RecentDockerActivity;
use App\Filament\Widgets\SecurityAccess;
use App\Filament\Widgets\StorageCapacity;
use App\Filament\Widgets\SyncActivity;
use App\Filament\Widgets\SystemHealth;
use App\Filament\Widgets\UsageTrend;
use App\Models\Credential;
use App\Models\DockerRepository;
use App\Models\Repository;
use App\Models\Setting;
use App\Models\SyncLog;
use App\Models\Token;
use App\Models\User;
use App\Services\RepositorySyncService;
use App\Support\PackgridSettings;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

beforeEach(function () {
    PackgridSettings::clearCache();
    actingAs(User::factory()->create());
});

/** Update the singleton settings row and clear its cache. */
function applySettings(array $attributes): void
{
    Setting::query()->firstOrCreate([])->update($attributes);
    PackgridSettings::clearCache();
}

/** A repository that is healthy (synced recently, no error). */
function healthyRepository(array $attributes = []): Repository
{
    return Repository::factory()->create(array_merge([
        'last_sync_at' => now(),
        'last_error' => null,
        'enabled' => true,
    ], $attributes));
}

describe('Dashboard page', function () {
    it('renders for an authenticated admin', function () {
        livewire(Dashboard::class)->assertOk();
    });

    it('orders the widgets by urgency (onboarding/attention first, activity last)', function () {
        expect((new Dashboard)->getWidgets())->toBe([
            OnboardingChecklist::class,
            AttentionRequired::class,
            SystemHealth::class,
            PackgridStats::class,
            SecurityAccess::class,
            UsageTrend::class,
            StorageCapacity::class,
            SyncActivity::class,
            RecentDockerActivity::class,
        ]);
    });
});

describe('PackgridStats widget', function () {
    it('renders', function () {
        livewire(PackgridStats::class)->assertOk();
    });

    it('sums package counts across repositories', function () {
        healthyRepository(['package_count' => 5]);
        healthyRepository(['package_count' => 3]);

        livewire(PackgridStats::class)
            ->assertOk()
            ->assertSee(__('widget.stats.packages'))
            ->assertSee('8');
    });

    it('flags stale (never-synced) repositories in the repositories card', function () {
        Repository::factory()->create(['last_sync_at' => null, 'last_error' => null]);

        livewire(PackgridStats::class)
            ->assertOk()
            ->assertSee(__('widget.stats.repositories_stale', ['stale' => 1]));
    });

    it('only shows the git clones card when the git feature is enabled', function () {
        applySettings(['git_enabled' => false]);
        livewire(PackgridStats::class)
            ->assertOk()
            ->assertDontSee(__('widget.stats.git_clones'));

        applySettings(['git_enabled' => true]);
        livewire(PackgridStats::class)
            ->assertOk()
            ->assertSee(__('widget.stats.git_clones'));
    });
});

describe('SystemHealth widget', function () {
    it('renders', function () {
        livewire(SystemHealth::class)->assertOk();
    });

    it('reports the scheduler as idle when no job has ever run', function () {
        livewire(SystemHealth::class)
            ->assertOk()
            ->assertSee(__('widget.health.scheduler_idle'));
    });

    it('reports the scheduler as active after a recent sync run', function () {
        SyncLog::factory()->create(['started_at' => now()->subMinutes(10)]);

        livewire(SystemHealth::class)
            ->assertOk()
            ->assertSee(__('widget.health.scheduler_active'));
    });

    it('does not show any backup information', function () {
        SyncLog::factory()->create(['started_at' => now()->subMinutes(10)]);

        livewire(SystemHealth::class)
            ->assertOk()
            ->assertDontSee(__('widget.health.backup'))
            ->assertDontSee(__('widget.health.backup_never'));
    });
});

describe('SecurityAccess widget', function () {
    it('renders', function () {
        livewire(SecurityAccess::class)->assertOk();
    });

    it('shows the security headings', function () {
        livewire(SecurityAccess::class)
            ->assertOk()
            ->assertSee(__('widget.security.active_tokens'))
            ->assertSee(__('widget.security.two_factor'));
    });

    it('flags admins without two-factor authentication', function () {
        // The acting admin from beforeEach has no app_authentication_secret.
        livewire(SecurityAccess::class)
            ->assertOk()
            ->assertSee(__('widget.security.two_factor_missing', ['count' => User::count()]));
    });

    it('counts never-used tokens as idle', function () {
        Token::factory()->count(2)->create(['last_used_at' => null]);

        livewire(SecurityAccess::class)
            ->assertOk()
            ->assertSee(__('widget.security.idle_tokens'));
    });
});

describe('AttentionRequired widget', function () {
    it('is hidden on a clean, empty instance', function () {
        expect(AttentionRequired::canView())->toBeFalse();
    });

    it('stays hidden when everything is healthy', function () {
        healthyRepository();
        Credential::factory()->create(['status' => CredentialStatus::Ok]);

        expect(AttentionRequired::canView())->toBeFalse();
    });

    it('appears when a repository sync has failed', function () {
        healthyRepository(['name' => 'Broken Repo', 'last_error' => 'Unauthorized']);

        expect(AttentionRequired::canView())->toBeTrue();

        livewire(AttentionRequired::class)
            ->assertOk()
            ->assertSee('Broken Repo')
            ->assertSee(__('widget.attention.failed_repos'));
    });

    it('shows a never-synced repository under "Awaiting first sync" (benign)', function () {
        Repository::factory()->create(['name' => 'Never Synced', 'last_sync_at' => null, 'last_error' => null]);

        expect(AttentionRequired::canView())->toBeTrue();

        livewire(AttentionRequired::class)
            ->assertOk()
            ->assertSee('Never Synced')
            ->assertSee(__('widget.attention.awaiting_repos'))
            ->assertDontSee(__('widget.attention.overdue_repos'));
    });

    it('shows an overdue repository under "Outdated mirrors" with a Sync now action', function () {
        Repository::factory()->create([
            'name' => 'Overdue Repo',
            'last_sync_at' => now()->subHours(9),
            'last_error' => null,
            'enabled' => true,
        ]);

        livewire(AttentionRequired::class)
            ->assertOk()
            ->assertSee('Overdue Repo')
            ->assertSee(__('widget.attention.overdue_repos'))
            ->assertSee(__('widget.attention.sync_now'));
    });

    it('diagnoses a stopped scheduler when several repositories are overdue at once', function () {
        Repository::factory()->count(3)->create([
            'last_sync_at' => now()->subHours(9),
            'last_error' => null,
            'enabled' => true,
        ]);

        livewire(AttentionRequired::class)
            ->assertOk()
            ->assertSee(__('widget.attention.scheduler_down_heading'));
    });

    it('syncRepository triggers the sync service for the given repository', function () {
        $repo = Repository::factory()->create(['last_sync_at' => now()->subHours(9), 'last_error' => null]);

        $mock = Mockery::mock(RepositorySyncService::class);
        $mock->shouldReceive('sync')->once()->with(Mockery::on(fn ($r) => $r->id === $repo->id));
        app()->instance(RepositorySyncService::class, $mock);

        livewire(AttentionRequired::class)
            ->call('syncRepository', $repo->id)
            ->assertOk();
    });

    it('appears when a token is already expired', function () {
        Token::factory()->create([
            'name' => 'Dead Token',
            'enabled' => true,
            'expires_at' => now()->subDay(),
        ]);

        expect(AttentionRequired::canView())->toBeTrue();

        livewire(AttentionRequired::class)
            ->assertOk()
            ->assertSee('Dead Token');
    });

    it('appears when a credential is invalid', function () {
        Credential::factory()->create(['name' => 'Bad Cred', 'status' => CredentialStatus::Fail]);

        expect(AttentionRequired::canView())->toBeTrue();

        livewire(AttentionRequired::class)
            ->assertOk()
            ->assertSee('Bad Cred')
            ->assertSee(__('widget.attention.invalid_credentials'));
    });

    it('no longer surfaces backup alerts (backup status lives on the backup page)', function () {
        // Data exists but no backup has been taken — the dashboard must stay silent
        // about backups now that the concern lives on the Backup & Restore page.
        healthyRepository();

        expect(AttentionRequired::canView())->toBeFalse();
    });
});

describe('UsageTrend widget', function () {
    it('renders', function () {
        livewire(UsageTrend::class)->assertOk();
    });
});

describe('StorageCapacity widget', function () {
    it('is visible only when Docker is enabled', function () {
        applySettings(['docker_enabled' => true]);
        expect(StorageCapacity::canView())->toBeTrue();

        applySettings(['docker_enabled' => false]);
        expect(StorageCapacity::canView())->toBeFalse();
    });

    it('renders the storage stats', function () {
        applySettings(['docker_enabled' => true]);

        livewire(StorageCapacity::class)
            ->assertOk()
            ->assertSee(__('widget.storage.used'))
            ->assertSee(__('widget.storage.reclaimable'));
    });
});

describe('RecentDockerActivity widget', function () {
    it('is visible only when Docker is enabled', function () {
        applySettings(['docker_enabled' => true]);
        expect(RecentDockerActivity::canView())->toBeTrue();

        applySettings(['docker_enabled' => false]);
        expect(RecentDockerActivity::canView())->toBeFalse();
    });

    it('renders', function () {
        applySettings(['docker_enabled' => true]);
        livewire(RecentDockerActivity::class)->assertOk();
    });
});

describe('OnboardingChecklist widget', function () {
    it('is visible on a fresh, unconfigured instance', function () {
        expect(OnboardingChecklist::canView())->toBeTrue();

        livewire(OnboardingChecklist::class)
            ->assertOk()
            ->assertSee(__('widget.onboarding.credential'))
            ->assertSee(__('widget.onboarding.two_factor'));
    });

    it('hides once every setup step is complete', function () {
        $user = User::factory()->create();
        $user->forceFill(['app_authentication_secret' => 'DEMOSECRET'])->save();
        actingAs($user);

        Credential::factory()->create(['status' => CredentialStatus::Ok, 'last_checked_at' => now()]);
        Token::factory()->create();
        DockerRepository::factory()->create();

        expect(OnboardingChecklist::canView())->toBeFalse();
    });
});
