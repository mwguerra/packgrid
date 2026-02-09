<?php

namespace App\Filament\Resources\RepositoryResource\Pages;

use App\Enums\RepositoryVisibility;
use App\Filament\Resources\RepositoryResource;
use App\Models\Credential;
use App\Models\Repository;
use App\Services\RepositoryMetadataBuilder;
use App\Services\RepositoryNormalizer;
use App\Services\RepositorySyncService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateRepository extends CreateRecord
{
    protected static string $resource = RepositoryResource::class;

    protected static bool $canCreateAnother = false;

    public function getTitle(): string
    {
        return __('repository.action.add_title');
    }

    public static function getNavigationLabel(): string
    {
        return __('repository.action.add_title');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->normalizeAndValidateRepository($data);
    }

    protected function afterCreate(): void
    {
        // Run initial sync after creation
        try {
            app(RepositorySyncService::class)->sync($this->record);
        } catch (Throwable $e) {
            // Record created but sync failed - error is already stored in last_error by sync service
            // We don't need to do anything here as the sync service handles error storage
        }
    }

    private function normalizeAndValidateRepository(array $data): array
    {
        $credential = empty($data['credential_id'])
            ? null
            : Credential::find($data['credential_id']);

        // Step 1: Normalize URL and detect visibility + format
        try {
            $normalized = app(RepositoryNormalizer::class)->normalize($data['url'], $credential);
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'data.url' => $exception->getMessage(),
            ]);
        }

        $data['repo_full_name'] = $normalized['repo_full_name'];
        $data['url'] = $normalized['url'];
        $data['name'] = $normalized['repo_full_name'];

        // Step 2: Check for duplicate repository
        if (Repository::where('url', $data['url'])->orWhere('repo_full_name', $data['repo_full_name'])->exists()) {
            throw ValidationException::withMessages([
                'data.url' => __('repository.validation.duplicate', ['repo' => $normalized['repo_full_name']]),
            ]);
        }

        // Step 3: Set visibility (auto-detected)
        if ($normalized['visibility']) {
            $data['visibility'] = $normalized['visibility'];
        } else {
            // Default to public if detection failed
            $data['visibility'] = $data['visibility'] ?? RepositoryVisibility::PublicRepo->value;
        }

        // Step 4: Validate credential requirement for private repos
        if (($data['visibility'] === RepositoryVisibility::PrivateRepo->value) && empty($data['credential_id'])) {
            throw ValidationException::withMessages([
                'data.credential_id' => __('repository.validation.credential_required'),
            ]);
        }

        // Step 5: Set format (auto-detected)
        if ($normalized['format']) {
            $data['format'] = $normalized['format'];
        } else {
            throw ValidationException::withMessages([
                'data.url' => __('repository.validation.no_manifest', ['repo' => $normalized['repo_full_name']]),
            ]);
        }

        // Step 6: Pre-validate the repository can be synced
        $this->validateRepositoryCanSync($data, $credential);

        return $data;
    }

    private function validateRepositoryCanSync(array $data, ?Credential $credential): void
    {
        // Create a temporary in-memory repository model for validation
        $tempRepo = new Repository([
            'repo_full_name' => $data['repo_full_name'],
            'url' => $data['url'],
            'format' => $data['format'],
            'visibility' => $data['visibility'],
            'enabled' => true,
            'ref_filter' => $data['ref_filter'] ?? null,
        ]);

        // Set credential relationship without saving
        if ($credential) {
            $tempRepo->setRelation('credential', $credential);
        }

        // Validate by attempting to build metadata (without actually storing)
        try {
            app(RepositoryMetadataBuilder::class)->build($tempRepo);
        } catch (Throwable $e) {
            $message = $e->getMessage();

            // Map exception to appropriate field
            if (str_contains($message, 'composer.json') || str_contains($message, 'package.json')) {
                throw ValidationException::withMessages([
                    'data.url' => __('repository.validation.manifest_error', ['error' => $message]),
                ]);
            }

            if (str_contains($message, 'No tags or branches found')) {
                throw ValidationException::withMessages([
                    'data.url' => __('repository.validation.no_refs'),
                ]);
            }

            if (str_contains($message, 'No matching tags or branches')) {
                throw ValidationException::withMessages([
                    'data.ref_filter' => __('repository.validation.invalid_ref_filter'),
                ]);
            }

            // Generic error
            throw ValidationException::withMessages([
                'data.url' => __('repository.validation.sync_failed', ['error' => $message]),
            ]);
        }
    }
}
