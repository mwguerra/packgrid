@php
    $items = $getItems();
    $bulletIcon = $getBulletIcon();
    $bulletColor = $getBulletColor();
@endphp

@if(count($items) > 0)
    <ul class="space-y-2 text-gray-600 dark:text-gray-400">
        @foreach($items as $item)
            <li class="flex items-start gap-2">
                <x-dynamic-component
                    :component="$bulletIcon"
                    @class([
                        'mt-0.5 h-4 w-4 shrink-0',
                        'text-gray-400' => $bulletColor === 'gray',
                        'text-emerald-500' => $bulletColor === 'emerald',
                        'text-amber-500' => $bulletColor === 'amber',
                        'text-blue-500' => $bulletColor === 'blue',
                        'text-red-500' => $bulletColor === 'red',
                    ])
                />
                <span>{!! $item !!}</span>
            </li>
        @endforeach
    </ul>
@endif
