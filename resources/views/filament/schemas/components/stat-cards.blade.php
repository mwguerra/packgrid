@php
    $cards = $getCards();
    $gridColumns = $getGridColumns();
@endphp

@if(count($cards) > 0)
    <div @class([
        'grid gap-4',
        'sm:grid-cols-2' => $gridColumns === 2,
        'sm:grid-cols-3' => $gridColumns === 3,
        'sm:grid-cols-4' => $gridColumns === 4,
    ])>
        @foreach($cards as $card)
            @php
                $icon = $card['icon'] ?? 'heroicon-s-check-circle';
                $color = $card['color'] ?? 'gray';
                $title = $card['title'] ?? '';
                $description = $card['description'] ?? '';
            @endphp
            <div class="flex items-center gap-4 rounded-xl border border-gray-200/70 bg-white/60 p-4 backdrop-blur dark:border-white/10 dark:bg-gray-900/60">
                <div @class([
                    'flex h-10 w-10 items-center justify-center rounded-lg',
                    'bg-emerald-100 dark:bg-emerald-500/10' => $color === 'emerald',
                    'bg-blue-100 dark:bg-blue-500/10' => $color === 'blue',
                    'bg-purple-100 dark:bg-purple-500/10' => $color === 'purple',
                    'bg-amber-100 dark:bg-amber-500/10' => $color === 'amber',
                    'bg-red-100 dark:bg-red-500/10' => $color === 'red',
                    'bg-gray-100 dark:bg-gray-500/10' => $color === 'gray',
                ])>
                    <x-dynamic-component
                        :component="$icon"
                        @class([
                            'h-5 w-5',
                            'text-emerald-600 dark:text-emerald-400' => $color === 'emerald',
                            'text-blue-600 dark:text-blue-400' => $color === 'blue',
                            'text-purple-600 dark:text-purple-400' => $color === 'purple',
                            'text-amber-600 dark:text-amber-400' => $color === 'amber',
                            'text-red-600 dark:text-red-400' => $color === 'red',
                            'text-gray-600 dark:text-gray-400' => $color === 'gray',
                        ])
                    />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $title }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $description }}</p>
                </div>
            </div>
        @endforeach
    </div>
@endif
