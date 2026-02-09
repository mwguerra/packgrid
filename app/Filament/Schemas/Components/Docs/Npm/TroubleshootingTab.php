<?php

namespace App\Filament\Schemas\Components\Docs\Npm;

use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs\Tab;

class TroubleshootingTab extends Tab
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('docs.tab.troubleshooting'));
        $this->icon('heroicon-o-wrench-screwdriver');

        $this->schema([
            Livewire::make(\App\Livewire\Docs\Npm\TroubleshootingContent::class),
        ]);
    }
}
