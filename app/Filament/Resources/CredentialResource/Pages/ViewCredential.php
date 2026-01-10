<?php

namespace App\Filament\Resources\CredentialResource\Pages;

use App\Filament\Resources\CredentialResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCredential extends ViewRecord
{
    protected static string $resource = CredentialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
