<x-filament::section icon="heroicon-o-key" icon-color="warning" collapsible>
    <x-slot name="heading">{{ __('docs.setup.step1.heading') }}</x-slot>
    <x-slot name="description">{{ __('docs.setup.step1.description') }}</x-slot>

    <ol class="space-y-4">
        <li class="flex gap-4">
            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-100 text-xs font-semibold text-amber-800 dark:bg-amber-500/20 dark:text-amber-300">1</span>
            <div class="text-sm text-gray-700 dark:text-gray-300">
                {{ __('docs.setup.step1.instruction1') }}
                <a class="inline-flex items-center gap-1 font-medium text-amber-600 underline decoration-amber-300 underline-offset-2 hover:text-amber-700 dark:text-amber-400 dark:decoration-amber-500" href="https://github.com/settings/tokens/new" rel="noopener" target="_blank">
                    {{ __('docs.setup.step1.link_text') }}
                    <x-heroicon-o-arrow-top-right-on-square class="h-3.5 w-3.5" />
                </a>
            </div>
        </li>
        <li class="flex gap-4">
            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-100 text-xs font-semibold text-amber-800 dark:bg-amber-500/20 dark:text-amber-300">2</span>
            <span class="text-sm text-gray-700 dark:text-gray-300">{!! __('docs.setup.step1.instruction2') !!}</span>
        </li>
        <li class="flex gap-4">
            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-100 text-xs font-semibold text-amber-800 dark:bg-amber-500/20 dark:text-amber-300">3</span>
            <span class="text-sm text-gray-700 dark:text-gray-300">{!! __('docs.setup.step1.instruction3', ['url' => route('filament.admin.resources.credentials.create')]) !!}</span>
        </li>
        <li class="flex gap-4">
            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-100 text-xs font-semibold text-amber-800 dark:bg-amber-500/20 dark:text-amber-300">4</span>
            <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('docs.setup.step1.instruction4') }}</span>
        </li>
    </ol>
</x-filament::section>
