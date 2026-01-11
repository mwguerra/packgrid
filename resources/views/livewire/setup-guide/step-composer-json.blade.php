<x-filament::section icon="heroicon-o-document-text" icon-color="info" collapsible>
    <x-slot name="heading">{{ __('docs.setup.step3.heading') }}</x-slot>
    <x-slot name="description">{{ __('docs.setup.step3.description') }}</x-slot>
    <x-slot name="headerEnd">
        <x-filament::button
            color="gray"
            size="sm"
            icon="heroicon-o-clipboard-document"
            x-on:click="navigator.clipboard.writeText(@js($this->composerSnippet)); $wire.showCopiedNotification('Snippet')"
        >
            {{ __('docs.setup.step3.copy_button') }}
        </x-filament::button>
    </x-slot>

    <div class="relative">
        <div class="absolute right-3 top-3">
            <span class="rounded-md bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-500/10 dark:text-blue-300">JSON</span>
        </div>
        <pre class="overflow-x-auto rounded-xl border border-gray-200 bg-gray-900 p-4 pr-16 text-sm dark:border-white/10"><code class="text-gray-100">{{ $this->composerSnippet }}</code></pre>
    </div>
</x-filament::section>
