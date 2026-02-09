<?php

namespace App\Filament\Schemas\Components;

use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs\Tab;

class IntroductionTab extends Tab
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Introduction');
        $this->icon('heroicon-o-home');

        $this->schema([
            Livewire::make(\App\Livewire\IntroductionContent::class),
        ]);
    }
}
