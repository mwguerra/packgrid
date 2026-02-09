<?php

namespace App\Filament\Resources\RepositoryResource\Pages;

use App\Enums\RepositoryVisibility;
use App\Filament\Resources\RepositoryResource;
use App\Models\Credential;
use App\Services\RepositoryNormalizer;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditRepository extends EditRecord
{
    protected static string $resource = RepositoryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->normalizeRepositoryData($data);
    }

    private function normalizeRepositoryData(array $data): array
    {
        $credential = empty($data['credential_id'])
            ? null
            : Credential::find($data['credential_id']);

        try {
            $normalized = app(RepositoryNormalizer::class)->normalize($data['url'], $credential);
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages([
                'url' => $exception->getMessage(),
            ]);
        }

        $data['repo_full_name'] = $normalized['repo_full_name'];
        $data['url'] = $normalized['url'];
        $data['name'] = $normalized['repo_full_name'];

        // On edit, keep user's format and visibility choices - don't override
        // The user has explicitly chosen these values and may need to override auto-detection

        if (($data['visibility'] === RepositoryVisibility::PrivateRepo->value) && empty($data['credential_id'])) {
            throw ValidationException::withMessages([
                'credential_id' => __('repository.validation.credential_required'),
            ]);
        }

        return $data;
    }
}
