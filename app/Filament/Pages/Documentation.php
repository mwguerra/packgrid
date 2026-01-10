<?php

namespace App\Filament\Pages;

use App\Filament\Schemas\Components\Docs\Composer\HowItWorksTab as ComposerHowItWorksTab;
use App\Filament\Schemas\Components\Docs\Composer\IntroductionTab as ComposerIntroductionTab;
use App\Filament\Schemas\Components\Docs\Composer\SetupGuideTab as ComposerSetupGuideTab;
use App\Filament\Schemas\Components\Docs\Composer\TroubleshootingTab as ComposerTroubleshootingTab;
use App\Filament\Schemas\Components\Docs\Npm\HowItWorksTab as NpmHowItWorksTab;
use App\Filament\Schemas\Components\Docs\Npm\IntroductionTab as NpmIntroductionTab;
use App\Filament\Schemas\Components\Docs\Npm\SetupGuideTab as NpmSetupGuideTab;
use App\Filament\Schemas\Components\Docs\Npm\TroubleshootingTab as NpmTroubleshootingTab;
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

    public ?array $data = [];

    #[Url(as: 'type')]
    public string $packageType = 'composer';

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('composer')
                    ->label('Composer')
                    ->icon('heroicon-o-cube')
                    ->color($this->packageType === 'composer' ? 'primary' : 'gray')
                    ->action(fn () => $this->setPackageType('composer')),
                Action::make('npm')
                    ->label('npm')
                    ->icon('heroicon-o-cube')
                    ->color($this->packageType === 'npm' ? 'primary' : 'gray')
                    ->action(fn () => $this->setPackageType('npm')),
            ])
                ->label($this->packageType === 'composer' ? 'Composer' : 'npm')
                ->icon('heroicon-o-cube')
                ->button()
                ->color('gray'),
        ];
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

    public function form(Schema $form): Schema
    {
        return $form
            ->schema($this->packageType === 'npm'
                ? $this->getNpmTabsSchema()
                : $this->getComposerTabsSchema())
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
