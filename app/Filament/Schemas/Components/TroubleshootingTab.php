<?php

namespace App\Filament\Schemas\Components;

use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs\Tab;

class TroubleshootingTab extends Tab
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Troubleshooting');
        $this->icon('heroicon-o-wrench-screwdriver');

        $this->schema([
            Livewire::make(\App\Livewire\TroubleshootingContent::class),
        ]);
    }
}
