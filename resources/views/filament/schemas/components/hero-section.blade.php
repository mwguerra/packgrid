@php
    $badgeIcon = $getBadgeIcon();
    $badgeLabel = $getBadgeLabel();
    $title = $getTitle();
    $description = $getDescription();
    $heroIcon = $getHeroIcon();
    $fromColor = $getHeroIconFromColor();
    $toColor = $getHeroIconToColor();
@endphp

<div class="rounded-2xl border border-gray-200/70 bg-gradient-to-br from-white to-amber-50/30 p-8 shadow-sm backdrop-blur dark:border-white/10 dark:from-gray-900 dark:to-amber-950/20">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
        <div class="space-y-4">
            @if($badgeIcon && $badgeLabel)
                <div class="inline-flex items-center gap-2 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                    <x-dynamic-component :component="$badgeIcon" class="h-3.5 w-3.5" />
                    {{ $badgeLabel }}
                </div>
            @endif
            @if($title)
                <h2 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $title }}</h2>
            @endif
            @if($description)
                <p class="max-w-2xl text-base text-gray-600 dark:text-gray-400">{{ $description }}</p>
            @endif
        </div>
        @if($heroIcon)
            <div class="hidden shrink-0 lg:block">
                <div @class([
                    'flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br shadow-lg',
                    'from-amber-400 to-orange-500 shadow-amber-500/25' => $fromColor === 'amber' && $toColor === 'orange',
                    'from-emerald-400 to-teal-500 shadow-emerald-500/25' => $fromColor === 'emerald' && $toColor === 'teal',
                    'from-blue-400 to-indigo-500 shadow-blue-500/25' => $fromColor === 'blue' && $toColor === 'indigo',
                    'from-purple-400 to-pink-500 shadow-purple-500/25' => $fromColor === 'purple' && $toColor === 'pink',
                ])>
                    <x-dynamic-component :component="$heroIcon" class="h-10 w-10 text-white" />
                </div>
            </div>
        @endif
    </div>
</div>
