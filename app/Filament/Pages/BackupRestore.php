<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\BackupService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class BackupRestore extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected string $view = 'filament.pages.backup-restore';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getNavigationLabel(): string
    {
        return __('backup.title');
    }

    public function getTitle(): string
    {
        return __('backup.title');
    }

    public function content(Schema $schema): Schema
    {
        $settings = Setting::first();

        return $schema
            ->components([
                Section::make(__('backup.section.backup'))
                    ->description(__('backup.section.backup_description'))
                    ->schema([
                        Text::make('last_backup_at')
                            ->content(fn () => $settings?->last_backup_at
                                ? $settings->last_backup_at->diffForHumans()
                                : __('common.never')),
                    ]),
                Section::make(__('backup.section.restore'))
                    ->description(__('backup.section.restore_description'))
                    ->schema([
                        Text::make('last_restore_at')
                            ->content(fn () => $settings?->last_restore_at
                                ? $settings->last_restore_at->diffForHumans()
                                : __('common.never')),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getCreateBackupAction(),
            $this->getRestoreAction(),
        ];
    }

    protected function getCreateBackupAction(): Action
    {
        return Action::make('createBackup')
            ->label(__('backup.action.create_backup'))
            ->icon('heroicon-o-arrow-down-tray')
            ->form([
                TextInput::make('password')
                    ->label(__('backup.field.password'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->minLength(8),
                TextInput::make('password_confirmation')
                    ->label(__('backup.field.password_confirmation'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->same('password'),
            ])
            ->action(function (array $data) {
                $service = new BackupService;

                $encrypted = $service->createBackup($data['password']);

                Setting::first()?->update(['last_backup_at' => now()]);

                Notification::make()
                    ->title(__('backup.notification.backup_created'))
                    ->success()
                    ->send();

                $filename = 'packgrid-backup-' . now()->format('Y-m-d-His') . '.bin';

                return response()->streamDownload(function () use ($encrypted) {
                    echo $encrypted;
                }, $filename, [
                    'Content-Type' => 'application/octet-stream',
                ]);
            });
    }

    protected function getRestoreAction(): Action
    {
        return Action::make('restore')
            ->label(__('backup.action.restore'))
            ->icon('heroicon-o-arrow-up-tray')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('backup.action.restore'))
            ->modalDescription(__('backup.action.restore_warning'))
            ->form([
                FileUpload::make('backup_file')
                    ->label(__('backup.field.backup_file'))
                    ->required()
                    ->acceptedFileTypes(['application/octet-stream', '.bin'])
                    ->maxSize(512000),
                TextInput::make('password')
                    ->label(__('backup.field.password'))
                    ->password()
                    ->revealable()
                    ->required(),
            ])
            ->action(function (array $data) {
                $service = new BackupService;

                try {
                    /** @var TemporaryUploadedFile $file */
                    $file = $data['backup_file'];
                    $content = file_get_contents($file->getRealPath());

                    $service->restoreBackup($content, $data['password']);

                    Notification::make()
                        ->title(__('backup.notification.restore_completed'))
                        ->success()
                        ->send();

                    $this->redirect('/admin');
                } catch (\RuntimeException $e) {
                    if (str_contains($e->getMessage(), 'Wrong password') || str_contains($e->getMessage(), 'Decryption failed')) {
                        Notification::make()
                            ->title(__('backup.notification.wrong_password'))
                            ->danger()
                            ->send();
                    } else {
                        Log::error('Backup restore failed', ['error' => $e->getMessage()]);

                        Notification::make()
                            ->title(__('backup.notification.restore_failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }
            });
    }
}
