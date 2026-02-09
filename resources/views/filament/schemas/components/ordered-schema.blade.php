<div class="flex gap-4 w-full">
    <div class="shrink-0">
        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-500 text-sm font-bold text-white">{{ $getNumber() }}</span>
    </div>
    <div class="flex-1 min-w-0">
        @if($getCustomView())
            @include($getCustomView())
        @else
            {{ $getChildSchema() }}
        @endif
    </div>
</div>
