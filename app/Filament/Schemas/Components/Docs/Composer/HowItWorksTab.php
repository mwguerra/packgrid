<?php

namespace App\Filament\Schemas\Components\Docs\Composer;

use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs\Tab;

class HowItWorksTab extends Tab
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('How It Works');
        $this->icon('heroicon-o-arrows-right-left');

        $this->schema([
            Livewire::make(\App\Livewire\HowItWorksContent::class),
        ]);
    }
}
