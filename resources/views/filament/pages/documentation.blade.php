<x-filament-panels::page>
    <div class="relative">
        {{-- Background gradient decoration --}}
        <div aria-hidden="true" class="pointer-events-none absolute -inset-x-6 -top-10 -z-10 h-60 bg-gradient-to-b from-amber-100/50 via-transparent to-transparent blur-3xl dark:from-amber-500/10"></div>

        {{-- Tabs with Form Schema - key forces re-render when package type changes --}}
        <div wire:key="docs-{{ $packageType }}">
            {{ $this->form }}
        </div>
    </div>
</x-filament-panels::page>
