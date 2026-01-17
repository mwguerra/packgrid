<?php

namespace App\Filament\Schemas\Components\Docs\Docker;

use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs\Tab;

class HowItWorksTab extends Tab
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('docs.tab.how_it_works'));
        $this->icon('heroicon-o-cog-6-tooth');

        $this->schema([
            Livewire::make(\App\Livewire\Docs\Docker\HowItWorksContent::class),
        ]);
    }
}
