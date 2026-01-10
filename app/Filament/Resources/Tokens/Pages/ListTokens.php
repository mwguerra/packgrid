<?php

namespace App\Filament\Resources\Tokens\Pages;

use App\Filament\Resources\Tokens\TokenResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTokens extends ListRecords
{
    protected static string $resource = TokenResource::class;

    public function getSubheading(): ?string
    {
        return __('token.subheading');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
