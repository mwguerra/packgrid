<?php

namespace App\Filament\Schemas\Components;

use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs\Tab;

class SetupGuideTab extends Tab
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Setup Guide');
        $this->icon('heroicon-o-book-open');

        $this->schema([
            Livewire::make(\App\Livewire\SetupGuideContent::class),
        ]);
    }
}
