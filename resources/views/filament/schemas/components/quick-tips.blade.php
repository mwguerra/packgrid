<x-filament::section icon="{{ $getIcon() }}" icon-color="gray" collapsible>
    <x-slot name="heading">{{ $getTitle() }}</x-slot>
    @if($getDescription())
        <x-slot name="description">{{ $getDescription() }}</x-slot>
    @endif

    <ul class="grid gap-2 text-sm text-gray-600 sm:grid-cols-2 dark:text-gray-400">
        @foreach($getItems() as $item)
            <li class="flex items-start gap-2">
                @php
                    $itemIcon = $item['icon'] ?? 'heroicon-s-check';
                    $itemColor = $item['color'] ?? 'emerald';
                @endphp
                <x-dynamic-component
                    :component="$itemIcon"
                    @class([
                        'mt-0.5 h-4 w-4 shrink-0',
                        'text-emerald-500' => $itemColor === 'emerald',
                        'text-amber-500' => $itemColor === 'amber',
                        'text-blue-500' => $itemColor === 'blue',
                        'text-red-500' => $itemColor === 'red',
                        'text-gray-500' => $itemColor === 'gray',
                        'text-purple-500' => $itemColor === 'purple',
                    ])
                />
                <span>{{ $item['text'] }}</span>
            </li>
        @endforeach
    </ul>
</x-filament::section>
