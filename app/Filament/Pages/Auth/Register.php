<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Schemas\Components\AlertBox;
use Filament\Auth\Pages\Register as FilamentRegister;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;

class Register extends FilamentRegister
{
    public function mount(): void
    {
        if ($this->hasUsers()) {
            redirect()->to(Filament::getLoginUrl());

            return;
        }

        parent::mount();
    }

    protected function hasUsers(): bool
    {
        try {
            return DB::table('users')->count() > 0;
        } catch (\Exception) {
            return true;
        }
    }

    public function getTitle(): string|Htmlable
    {
        return __('auth.register.title');
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('auth.register.heading');
    }

    public function getSubheading(): string
    {
        return __('auth.register.subheading');
    }

    public function form(Schema $schema): Schema
    {
        $components = parent::form($schema)->getComponents();

        return $schema->components([
            ...$components,
            AlertBox::make()
                ->warning()
                ->icon('heroicon-o-exclamation-triangle')
                ->title(__('auth.register.password_warning_title'))
                ->description(__('auth.register.password_warning_description')),
        ]);
    }
}
