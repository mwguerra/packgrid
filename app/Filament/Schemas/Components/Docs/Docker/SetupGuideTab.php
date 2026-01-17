<?php

namespace App\Filament\Schemas\Components\Docs\Docker;

use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs\Tab;

class SetupGuideTab extends Tab
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('docs.tab.setup_guide'));
        $this->icon('heroicon-o-wrench-screwdriver');

        $this->schema([
            Livewire::make(\App\Livewire\Docs\Docker\SetupGuideContent::class),
        ]);
    }
}
