<x-filament::section icon="heroicon-o-server" icon-color="warning" collapsible>
    <x-slot name="heading">Server URL</x-slot>
    <x-slot name="description">Your Packgrid endpoint</x-slot>
    <x-slot name="headerEnd">
        <x-filament::button
            color="gray"
            size="sm"
            icon="heroicon-o-clipboard-document"
            x-on:click="navigator.clipboard.writeText(@js($this->serverUrl)); $wire.showCopiedNotification('URL')"
        >
            Copy URL
        </x-filament::button>
    </x-slot>

    <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 font-mono text-sm dark:border-white/10 dark:bg-gray-800">
        <span class="h-2.5 w-2.5 rounded-full bg-emerald-500 shadow-sm shadow-emerald-500/50"></span>
        <span class="text-gray-900 dark:text-gray-100">{{ $this->serverUrl }}</span>
    </div>
</x-filament::section>
