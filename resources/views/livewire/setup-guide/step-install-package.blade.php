<x-filament::section icon="heroicon-o-command-line" icon-color="success" collapsible>
    <x-slot name="heading">{{ __('docs.setup.step5.heading') }}</x-slot>
    <x-slot name="description">{{ __('docs.setup.step5.description') }}</x-slot>
    <x-slot name="headerEnd">
        <x-filament::button
            color="gray"
            size="sm"
            icon="heroicon-o-clipboard-document"
            x-on:click="navigator.clipboard.writeText('composer require vendor/package-name'); $wire.showCopiedNotification('Command')"
        >
            {{ __('docs.setup.step5.copy_button') }}
        </x-filament::button>
    </x-slot>

    <div class="space-y-4">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ __('docs.setup.step5.instruction') }}
        </p>
        <div class="relative">
            <div class="absolute right-3 top-3">
                <span class="rounded-md bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">Terminal</span>
            </div>
            <pre class="overflow-x-auto rounded-xl border border-gray-200 bg-gray-900 p-4 pr-16 text-sm dark:border-white/10"><code class="text-gray-100">composer require vendor/package-name</code></pre>
        </div>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {!! __('docs.setup.step5.note') !!}
        </p>
    </div>
</x-filament::section>
