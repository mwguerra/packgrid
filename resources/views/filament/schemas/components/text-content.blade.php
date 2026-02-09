@php
    $content = $getContent();
@endphp

@if($content)
    <p class="text-gray-600 dark:text-gray-400">{!! $content !!}</p>
@endif
