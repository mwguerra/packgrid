<?php

namespace App\Filament\Schemas\Components\Docs\Npm;

use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs\Tab;

class SetupGuideTab extends Tab
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('docs.tab.setup_guide'));
        $this->icon('heroicon-o-book-open');

        $this->schema([
            Livewire::make(\App\Livewire\Docs\Npm\SetupGuideContent::class),
        ]);
    }
}
