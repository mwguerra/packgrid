@if($this->hasTokens)
<x-filament::section icon="heroicon-o-lock-closed" icon-color="primary" collapsible>
    <x-slot name="heading">{{ __('docs.setup.step4.heading') }}</x-slot>
    <x-slot name="description">{{ __('docs.setup.step4.description') }}</x-slot>
    <x-slot name="headerEnd">
        <x-filament::button
            color="gray"
            size="sm"
            icon="heroicon-o-clipboard-document"
            x-on:click="navigator.clipboard.writeText(@js($this->authSnippet)); $wire.showCopiedNotification('Auth snippet')"
        >
            {{ __('docs.setup.step4.copy_button') }}
        </x-filament::button>
    </x-slot>

    <div class="space-y-4">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            {!! __('docs.setup.step4.instruction', ['url' => route('filament.admin.resources.tokens.index')]) !!}
        </p>
        <div class="relative">
            <div class="absolute right-3 top-3">
                <span class="rounded-md bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-700 dark:bg-purple-500/10 dark:text-purple-300">JSON</span>
            </div>
            <pre class="overflow-x-auto rounded-xl border border-gray-200 bg-gray-900 p-4 pr-16 text-sm dark:border-white/10"><code class="text-gray-100">{{ $this->authSnippet }}</code></pre>
        </div>
    </div>
</x-filament::section>
@else
<x-filament::section icon="heroicon-o-lock-open" icon-color="gray" collapsible>
    <x-slot name="heading">{{ __('docs.setup.step4_alt.heading') }}</x-slot>
    <x-slot name="description">{!! __('docs.setup.step4_alt.description', ['url' => route('filament.admin.resources.tokens.create')]) !!}</x-slot>
</x-filament::section>
@endif
