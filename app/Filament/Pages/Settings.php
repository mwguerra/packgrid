<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\PackgridSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;

class Settings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected string $view = 'filament.pages.settings';

    public ?array $data = [];

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getNavigationLabel(): string
    {
        return __('settings.title');
    }

    public function getTitle(): string
    {
        return __('settings.title');
    }

    public function mount(): void
    {
        $settings = Setting::firstOrCreate([], [
            'composer_enabled' => true,
            'npm_enabled' => true,
            'docker_enabled' => true,
        ]);

        $this->form->fill([
            'composer_enabled' => $settings->composer_enabled,
            'npm_enabled' => $settings->npm_enabled,
            'docker_enabled' => $settings->docker_enabled,
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make(__('settings.section.features'))
                    ->description(__('settings.section.features_description'))
                    ->schema([
                        Toggle::make('composer_enabled')
                            ->label(__('settings.field.composer_enabled'))
                            ->helperText(__('settings.field.composer_enabled_helper')),
                        Toggle::make('npm_enabled')
                            ->label(__('settings.field.npm_enabled'))
                            ->helperText(__('settings.field.npm_enabled_helper')),
                        Toggle::make('docker_enabled')
                            ->label(__('settings.field.docker_enabled'))
                            ->helperText(__('settings.field.docker_enabled_helper')),
                    ]),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment($this->getFormActionsAlignment())
                    ->fullWidth($this->hasFullWidthFormActions())
                    ->key('form-actions'),
            ]);
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return Action::make('save')
            ->label(__('settings.action.save'))
            ->submit('save')
            ->keyBindings(['mod+s']);
    }

    protected function hasFullWidthFormActions(): bool
    {
        return false;
    }

    public function getFormActionsAlignment(): string|Alignment
    {
        return Alignment::Start;
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Validate that at least one feature is enabled
        if (! $data['composer_enabled'] && ! $data['npm_enabled'] && ! $data['docker_enabled']) {
            Notification::make()
                ->title(__('settings.notification.at_least_one_required'))
                ->danger()
                ->send();

            return;
        }

        $settings = Setting::firstOrCreate([]);
        $settings->update($data);

        // Clear the cached settings
        PackgridSettings::clearCache();

        Notification::make()
            ->title(__('settings.notification.saved'))
            ->success()
            ->send();
    }
}
