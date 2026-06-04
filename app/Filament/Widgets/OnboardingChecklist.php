<?php

namespace App\Filament\Widgets;

use App\Models\Credential;
use App\Models\DockerRepository;
use App\Models\Repository;
use App\Models\SyncLog;
use App\Models\Token;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Route;

class OnboardingChecklist extends Widget
{
    protected string $view = 'filament.widgets.onboarding-checklist';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return ! self::isComplete();
    }

    protected static function isComplete(): bool
    {
        foreach (self::steps() as $step) {
            if (! $step['done']) {
                return false;
            }
        }

        return true;
    }

    protected function getViewData(): array
    {
        $steps = self::steps();

        return [
            'steps' => $steps,
            'doneCount' => count(array_filter($steps, fn (array $s): bool => $s['done'])),
            'totalCount' => count($steps),
        ];
    }

    /**
     * @return array<int, array{key: string, done: bool, url: ?string}>
     */
    protected static function steps(): array
    {
        $user = auth()->user();

        return [
            [
                'key' => 'credential',
                'done' => Credential::query()->exists(),
                'url' => self::routeOrNull('filament.admin.resources.credentials.index'),
            ],
            [
                'key' => 'token',
                'done' => Token::query()->exists(),
                'url' => self::routeOrNull('filament.admin.resources.tokens.index'),
            ],
            [
                'key' => 'repository',
                'done' => Repository::query()->exists() || DockerRepository::query()->exists(),
                'url' => self::routeOrNull('filament.admin.resources.repositories.index'),
            ],
            [
                'key' => 'two_factor',
                'done' => $user !== null && ! is_null($user->getAppAuthenticationSecret()),
                'url' => self::routeOrNull('filament.admin.auth.profile'),
            ],
            [
                'key' => 'scheduler',
                'done' => SyncLog::query()->exists()
                    || Credential::query()->whereNotNull('last_checked_at')->exists(),
                'url' => null,
            ],
        ];
    }

    protected static function routeOrNull(string $name): ?string
    {
        return Route::has($name) ? route($name) : null;
    }

    public function getHeading(): string
    {
        return __('widget.onboarding.heading');
    }
}
