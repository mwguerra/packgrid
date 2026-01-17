<?php

namespace App\Filament\Pages;

use App\Filament\Schemas\Components\Docs\Composer\HowItWorksTab as ComposerHowItWorksTab;
use App\Filament\Schemas\Components\Docs\Composer\IntroductionTab as ComposerIntroductionTab;
use App\Filament\Schemas\Components\Docs\Composer\SetupGuideTab as ComposerSetupGuideTab;
use App\Filament\Schemas\Components\Docs\Composer\TroubleshootingTab as ComposerTroubleshootingTab;
use App\Filament\Schemas\Components\Docs\Docker\HowItWorksTab as DockerHowItWorksTab;
use App\Filament\Schemas\Components\Docs\Docker\IntroductionTab as DockerIntroductionTab;
use App\Filament\Schemas\Components\Docs\Docker\SetupGuideTab as DockerSetupGuideTab;
use App\Filament\Schemas\Components\Docs\Docker\TroubleshootingTab as DockerTroubleshootingTab;
use App\Filament\Schemas\Components\Docs\Npm\HowItWorksTab as NpmHowItWorksTab;
use App\Filament\Schemas\Components\Docs\Npm\IntroductionTab as NpmIntroductionTab;
use App\Filament\Schemas\Components\Docs\Npm\SetupGuideTab as NpmSetupGuideTab;
use App\Filament\Schemas\Components\Docs\Npm\TroubleshootingTab as NpmTroubleshootingTab;
use App\Support\PackgridSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

class Documentation extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.documentation';

    public static function getNavigationLabel(): string
    {
        return __('navigation.documentation');
    }

    public function getTitle(): string
    {
        return __('docs.title');
    }

    public ?array $data = [];

    #[Url(as: 'type')]
    public string $packageType = 'composer';

    public function mount(): void
    {
        // If current package type is disabled, redirect to first enabled type
        $enabledTypes = PackgridSettings::getEnabledPackageTypes();

        if (! in_array($this->packageType, $enabledTypes)) {
            $defaultType = PackgridSettings::getDefaultPackageType();
            if ($defaultType && $defaultType !== $this->packageType) {
                $this->redirect(static::getUrl(['type' => $defaultType]));

                return;
            }
        }

        $this->form->fill();
    }

    protected function getHeaderActions(): array
    {
        $enabledTypes = PackgridSettings::getEnabledPackageTypes();

        // If only one type is enabled, don't show the selector
        if (count($enabledTypes) <= 1) {
            return [];
        }

        $actions = [];

        if (PackgridSettings::composerEnabled()) {
            $actions[] = Action::make('composer')
                ->label(__('docs.action.composer'))
                ->icon('heroicon-o-cube')
                ->color($this->packageType === 'composer' ? 'primary' : 'gray')
                ->action(fn () => $this->setPackageType('composer'));
        }

        if (PackgridSettings::npmEnabled()) {
            $actions[] = Action::make('npm')
                ->label(__('docs.action.npm'))
                ->icon('heroicon-o-cube')
                ->color($this->packageType === 'npm' ? 'primary' : 'gray')
                ->action(fn () => $this->setPackageType('npm'));
        }

        if (PackgridSettings::dockerEnabled()) {
            $actions[] = Action::make('docker')
                ->label(__('docs.action.docker'))
                ->icon('heroicon-o-cube-transparent')
                ->color($this->packageType === 'docker' ? 'primary' : 'gray')
                ->action(fn () => $this->setPackageType('docker'));
        }

        return [
            ActionGroup::make($actions)
                ->label($this->getPackageTypeLabel())
                ->icon($this->packageType === 'docker' ? 'heroicon-o-cube-transparent' : 'heroicon-o-cube')
                ->button()
                ->color('gray'),
        ];
    }

    protected function getPackageTypeLabel(): string
    {
        return match ($this->packageType) {
            'npm' => __('docs.action.npm'),
            'docker' => __('docs.action.docker'),
            default => __('docs.action.composer'),
        };
    }

    public function setPackageType(string $type): void
    {
        $this->redirect(static::getUrl(['type' => $type]));
    }

    protected function getComposerTabsSchema(): array
    {
        return [
            Tabs::make('ComposerDocumentation')
                ->tabs([
                    ComposerIntroductionTab::make('introduction'),
                    ComposerHowItWorksTab::make('how-it-works'),
                    ComposerSetupGuideTab::make('setup-guide'),
                    ComposerTroubleshootingTab::make('troubleshooting'),
                ])
                ->contained(false),
        ];
    }

    protected function getNpmTabsSchema(): array
    {
        return [
            Tabs::make('NpmDocumentation')
                ->tabs([
                    NpmIntroductionTab::make('introduction'),
                    NpmHowItWorksTab::make('how-it-works'),
                    NpmSetupGuideTab::make('setup-guide'),
                    NpmTroubleshootingTab::make('troubleshooting'),
                ])
                ->contained(false),
        ];
    }

    protected function getDockerTabsSchema(): array
    {
        return [
            Tabs::make('DockerDocumentation')
                ->tabs([
                    DockerIntroductionTab::make('introduction'),
                    DockerHowItWorksTab::make('how-it-works'),
                    DockerSetupGuideTab::make('setup-guide'),
                    DockerTroubleshootingTab::make('troubleshooting'),
                ])
                ->contained(false),
        ];
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema(match ($this->packageType) {
                'npm' => $this->getNpmTabsSchema(),
                'docker' => $this->getDockerTabsSchema(),
                default => $this->getComposerTabsSchema(),
            })
            ->statePath('data');
    }

    #[On('copied')]
    public function showCopiedNotification(string $label = 'Content'): void
    {
        Notification::make()
            ->title(__('common.copied_to_clipboard', ['label' => $label]))
            ->success()
            ->send();
    }
}
