<?php

namespace App\Filament\Resources\Tokens;

use App\Filament\Resources\Tokens\Pages\CreateToken;
use App\Filament\Resources\Tokens\Pages\EditToken;
use App\Filament\Resources\Tokens\Pages\ListTokens;
use App\Filament\Resources\Tokens\Pages\ViewToken;
use App\Filament\Resources\Tokens\Schemas\TokenForm;
use App\Filament\Resources\Tokens\Schemas\TokenInfolist;
use App\Filament\Resources\Tokens\Tables\TokensTable;
use App\Models\Token;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class TokenResource extends Resource
{
    protected static ?string $model = Token::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.group.access_control');
    }

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('token.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('token.model_label_plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('token.navigation_label');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Token::where('enabled', true)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        $hasExpiredTokens = Token::where('enabled', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->exists();

        return $hasExpiredTokens ? 'danger' : 'primary';
    }

    public static function form(Schema $schema): Schema
    {
        return TokenForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TokenInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TokensTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTokens::route('/'),
            'create' => CreateToken::route('/create'),
            'view' => ViewToken::route('/{record}'),
            'edit' => EditToken::route('/{record}/edit'),
        ];
    }
}
