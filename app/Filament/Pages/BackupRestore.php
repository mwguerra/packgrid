<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\BackupService;
use App\Support\PackgridSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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

    /**
     * Data for the page view: the system-state table rows and the last
     * backup/restore timestamps.
     *
     * @return array{rows: array<int, array{label: string, value: string, authenticated?: bool}>, dockerEnabled: bool, lastBackupAt: ?Carbon, lastRestoreAt: ?Carbon}
     */
    public function systemState(): array
    {
        $summary = (new BackupService)->getBackupSummary();
        $settings = Setting::first();

        return [
            'rows' => [
                ['label' => __('backup.state.label.database'), 'value' => strtoupper($summary['driver'])],
                ['label' => __('backup.state.label.tables'), 'value' => number_format($summary['table_count'])],
                ['label' => __('backup.state.label.records'), 'value' => number_format($summary['record_count'])],
                ['label' => __('backup.state.label.encryption'), 'value' => $summary['encryption'], 'authenticated' => true],
            ],
            'dockerEnabled' => PackgridSettings::dockerEnabled(),
            'lastBackupAt' => $settings?->last_backup_at,
            'lastRestoreAt' => $settings?->last_restore_at,
        ];
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

                // createBackup records last_backup_at on the settings row.
                $encrypted = $service->createBackup($data['password']);

                Notification::make()
                    ->title(__('backup.notification.backup_created'))
                    ->success()
                    ->send();

                $filename = 'packgrid-backup-'.now()->format('Y-m-d-His').'.bin';

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
                    $file = $data['backup_file'];

                    if ($file instanceof TemporaryUploadedFile) {
                        $content = $file->get();
                    } else {
                        $content = Storage::get($file);
                    }

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
