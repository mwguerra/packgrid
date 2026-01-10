@if($this->hasTokens)
<x-filament::section icon="heroicon-o-lock-closed" icon-color="primary" collapsible>
    <x-slot name="heading">auth.json</x-slot>
    <x-slot name="description">Packgrid token for authentication</x-slot>
    <x-slot name="headerEnd">
        <x-filament::button
            color="gray"
            size="sm"
            icon="heroicon-o-clipboard-document"
            x-on:click="navigator.clipboard.writeText(@js($this->authSnippet)); $wire.showCopiedNotification('Auth snippet')"
        >
            Copy Snippet
        </x-filament::button>
    </x-slot>

    <div class="space-y-4">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Create a token in <a href="{{ route('filament.admin.resources.tokens.index') }}" class="font-medium text-amber-600 underline hover:text-amber-700 dark:text-amber-400">Tokens</a> and add it to your auth.json:
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
    <x-slot name="heading">Authentication (Optional)</x-slot>
    <x-slot name="description">Currently public. <a href="{{ route('filament.admin.resources.tokens.create') }}" class="font-medium text-amber-600 underline hover:text-amber-700 dark:text-amber-400">Create a token</a> to require authentication.</x-slot>
</x-filament::section>
@endif
