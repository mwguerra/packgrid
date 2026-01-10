<?php

namespace App\Filament\Resources\CredentialResource\Pages;

use App\Filament\Resources\CredentialResource;
use Filament\Resources\Pages\EditRecord;

class EditCredential extends EditRecord
{
    protected static string $resource = CredentialResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
