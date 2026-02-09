@php
    $errorMessage = $getErrorMessage();
    $solutionSchema = $getSolutionSchema();
    $searchId = $getSearchId();
    $icon = $getIcon();
    $iconColor = $getIconColor();
@endphp

<div
    @if($searchId)
        x-init="$watch('search', () => {
            const shouldHide = isSearchActive && !matches('{{ $searchId }}');
            $el.closest('.fi-grid-col')?.classList.toggle('hidden', shouldHide);
        })"
        x-effect="$el.closest('.fi-grid-col')?.classList.toggle('hidden', isSearchActive && !matches('{{ $searchId }}'))"
    @endif
>
<x-filament::section
        :icon="$icon"
        icon-color="danger"
        :collapsible="$isCollapsible()"
        :collapsed="$isCollapsed()"
    >
        <x-slot name="heading">{{ $getHeading() }}</x-slot>
        @if($getDescription())
            <x-slot name="description">{{ $getDescription() }}</x-slot>
        @endif

        <div class="space-y-6">
            @if($errorMessage)
                <div class="space-y-3">
                    <p class="font-medium text-gray-700 dark:text-gray-300">Error message:</p>
                    <div class="rounded-md p-4 bg-red-400/80">
                        <p class="font-medium text-white">{!! nl2br(e($errorMessage)) !!}</p>
                    </div>
                </div>
            @endif

            @if(count($solutionSchema) > 0)
                <div class="space-y-3">
                    <p class="font-medium text-gray-700 dark:text-gray-300">Solution:</p>
                    {{ $getChildSchema() }}
                </div>
            @endif
        </div>
    </x-filament::section>
</div>
