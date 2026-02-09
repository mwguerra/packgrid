@php
    $icon = $getIcon();
    $title = $getTitle();
    $description = $getDescription();
    $type = $getType();
    $items = $getItems();
@endphp

<div @class([
    'rounded-xl border p-4',
    'border-amber-200 bg-amber-50 dark:border-amber-500/20 dark:bg-amber-500/5' => $type === 'warning',
    'border-red-200 bg-red-50 dark:border-red-500/20 dark:bg-red-500/5' => $type === 'danger',
    'border-blue-200 bg-blue-50 dark:border-blue-500/20 dark:bg-blue-500/5' => $type === 'info',
    'border-emerald-200 bg-emerald-50 dark:border-emerald-500/20 dark:bg-emerald-500/5' => $type === 'success',
])>
    <div class="flex gap-3">
        @if($icon)
            <div class="shrink-0">
                <x-dynamic-component
                    :component="$icon"
                    @class([
                        'h-5 w-5',
                        'text-amber-600 dark:text-amber-400' => $type === 'warning',
                        'text-red-600 dark:text-red-400' => $type === 'danger',
                        'text-blue-600 dark:text-blue-400' => $type === 'info',
                        'text-emerald-600 dark:text-emerald-400' => $type === 'success',
                    ])
                />
            </div>
        @endif
        <div class="flex-1 space-y-2">
            @if($title)
                <p @class([
                    'text-sm font-semibold',
                    'text-amber-800 dark:text-amber-300' => $type === 'warning',
                    'text-red-800 dark:text-red-300' => $type === 'danger',
                    'text-blue-800 dark:text-blue-300' => $type === 'info',
                    'text-emerald-800 dark:text-emerald-300' => $type === 'success',
                ])>{{ $title }}</p>
            @endif
            @if($description)
                <p @class([
                    'text-sm',
                    'text-amber-700 dark:text-amber-400' => $type === 'warning',
                    'text-red-700 dark:text-red-400' => $type === 'danger',
                    'text-blue-700 dark:text-blue-400' => $type === 'info',
                    'text-emerald-700 dark:text-emerald-400' => $type === 'success',
                ])>{!! $description !!}</p>
            @endif
            @if(count($items) > 0)
                <ul @class([
                    'mt-2 space-y-1 text-sm',
                    'text-amber-700 dark:text-amber-400' => $type === 'warning',
                    'text-red-700 dark:text-red-400' => $type === 'danger',
                    'text-blue-700 dark:text-blue-400' => $type === 'info',
                    'text-emerald-700 dark:text-emerald-400' => $type === 'success',
                ])>
                    @foreach($items as $item)
                        <li class="flex items-start gap-2">
                            <span class="mt-1.5 h-1 w-1 shrink-0 rounded-full @if($type === 'warning') bg-amber-500 @elseif($type === 'danger') bg-red-500 @elseif($type === 'info') bg-blue-500 @else bg-emerald-500 @endif"></span>
                            <span>{!! $item !!}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
