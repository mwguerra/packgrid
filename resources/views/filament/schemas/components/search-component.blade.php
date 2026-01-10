@php
    $heading = $getHeading();
    $placeholder = $getPlaceholder();
    $noResultsText = $getNoResultsText();
    $noResultsDescription = $getNoResultsDescription();
    $githubUrl = $getGithubUrl();
    $haystackData = $getHaystackData();
@endphp

<div
    class="space-y-4"
    x-data="{
        search: '',
        items: @js($haystackData),
        get isSearchActive() {
            return this.search.trim().length > 0 && /[a-zA-Z0-9]/.test(this.search);
        },
        matches(itemId) {
            if (!this.isSearchActive) return true;
            const item = this.items.find(i => i.id === itemId);
            if (!item) return false;
            const searchLower = this.search.toLowerCase().trim();
            return (item.title && item.title.toLowerCase().includes(searchLower)) ||
                   (item.description && item.description.toLowerCase().includes(searchLower)) ||
                   (item.error && item.error.toLowerCase().includes(searchLower));
        },
        get hasResults() {
            if (!this.isSearchActive) return true;
            return this.items.some(item => this.matches(item.id));
        }
    }"
>
    {{-- Search Header --}}
    <div class="border-l-4 border-amber-300 mb-6 bg-white dark:bg-gray-900 mx-auto max-w-7xl px-8 py-12">
        <h2 class="max-w-2xl text-4xl font-semibold tracking-tight text-center sm:text-left text-balance text-gray-900 sm:text-5xl dark:text-white">{{ $heading }}</h2>
        <div class="mt-8 flex flex-col items-center sm:items-start gap-x-6">
            <x-filament::input.wrapper
                class="w-full max-w-lg"
                prefix-icon="heroicon-m-magnifying-glass"
            >
                <x-filament::input
                    type="text"
                    x-model="search"
                    :placeholder="$placeholder"
                />
            </x-filament::input.wrapper>
            @if($githubUrl)
                <p class="mt-3 text-gray-600 dark:text-gray-400">
                    Nothing worked? <a href="{{ $githubUrl }}" target="_blank" class="font-medium text-amber-600 hover:text-amber-700 dark:text-amber-400 hover:underline">Open an issue on GitHub</a>.
                </p>
            @endif
        </div>
    </div>

    {{-- No Results Message --}}
    <div x-show="isSearchActive && !hasResults" x-cloak class="rounded-xl border border-gray-200 bg-gray-50 p-8 text-center dark:border-white/10 dark:bg-gray-900">
        <x-heroicon-o-magnifying-glass class="mx-auto h-8 w-8 text-gray-400" />
        <p class="mt-3 text-base font-medium text-gray-900 dark:text-white">{{ $noResultsText }}</p>
        <p class="mt-1 text-base text-gray-500 dark:text-gray-400">{{ $noResultsDescription }}</p>
        <button type="button" x-on:click="search = ''" class="mt-3 text-base font-medium text-amber-600 hover:text-amber-700 dark:text-amber-400">
            Clear search
        </button>
    </div>

    {{-- Render child schema with search visibility --}}
    {{ $getChildSchema() }}
</div>
