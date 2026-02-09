<?php

namespace App\Filament\Schemas\Components\Docs\Npm;

use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs\Tab;

class IntroductionTab extends Tab
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label(__('docs.tab.introduction'));
        $this->icon('heroicon-o-home');

        $this->schema([
            Livewire::make(\App\Livewire\Docs\Npm\IntroductionContent::class),
        ]);
    }
}
