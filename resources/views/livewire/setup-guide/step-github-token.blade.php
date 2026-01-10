<x-filament::section icon="heroicon-o-key" icon-color="warning" collapsible>
    <x-slot name="heading">GitHub Credential (for private repos)</x-slot>
    <x-slot name="description">Add a GitHub token to Packgrid for private repository access</x-slot>

    <ol class="space-y-4">
        <li class="flex gap-4">
            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-100 text-xs font-semibold text-amber-800 dark:bg-amber-500/20 dark:text-amber-300">1</span>
            <div class="text-sm text-gray-700 dark:text-gray-300">
                Open
                <a class="inline-flex items-center gap-1 font-medium text-amber-600 underline decoration-amber-300 underline-offset-2 hover:text-amber-700 dark:text-amber-400 dark:decoration-amber-500" href="https://github.com/settings/tokens/new" rel="noopener" target="_blank">
                    GitHub Token Settings
                    <x-heroicon-o-arrow-top-right-on-square class="h-3.5 w-3.5" />
                </a>
            </div>
        </li>
        <li class="flex gap-4">
            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-100 text-xs font-semibold text-amber-800 dark:bg-amber-500/20 dark:text-amber-300">2</span>
            <span class="text-sm text-gray-700 dark:text-gray-300">Name the token <code class="rounded bg-amber-100 px-1.5 py-0.5 font-mono text-xs dark:bg-amber-500/20">Packgrid</code> and select the <code class="rounded bg-amber-100 px-1.5 py-0.5 font-mono text-xs dark:bg-amber-500/20">repo</code> scope</span>
        </li>
        <li class="flex gap-4">
            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-100 text-xs font-semibold text-amber-800 dark:bg-amber-500/20 dark:text-amber-300">3</span>
            <span class="text-sm text-gray-700 dark:text-gray-300">Copy the token and <a href="{{ route('filament.admin.resources.credentials.create') }}" class="font-medium text-amber-600 underline decoration-amber-300 underline-offset-2 hover:text-amber-700 dark:text-amber-400">create a credential</a> in Packgrid</span>
        </li>
        <li class="flex gap-4">
            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-100 text-xs font-semibold text-amber-800 dark:bg-amber-500/20 dark:text-amber-300">4</span>
            <span class="text-sm text-gray-700 dark:text-gray-300">Link your private repositories to this credential when adding them</span>
        </li>
    </ol>
</x-filament::section>
