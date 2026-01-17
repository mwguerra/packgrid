@php
    $code = $getCode();
    $language = $getLanguage();
    $copyLabel = $getCopyLabel();
    $hasCopyButton = $hasCopyButton();
@endphp

@if($code)
    <div x-data="{ copied: false }" class="relative">
        @if($hasCopyButton)
            <button
                type="button"
                x-on:click="
                    navigator.clipboard.writeText(@js($code));
                    copied = true;
                    $dispatch('open-modal', { id: 'filament-notifications' });
                    new FilamentNotification()
                        .title('{{ __('Copied to clipboard') }}')
                        .icon('heroicon-o-clipboard-document-check')
                        .iconColor('success')
                        .duration(3000)
                        .send();
                    setTimeout(() => copied = false, 2000)
                "
                class="absolute right-3 top-3 rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-700 hover:text-white"
                title="Copy to clipboard"
            >
                <x-heroicon-o-clipboard-document x-show="!copied" class="h-4 w-4" />
                <x-heroicon-o-check x-show="copied" x-cloak class="h-4 w-4 text-emerald-400" />
            </button>
        @endif
        @if($language)
            <div class="absolute right-3 top-3 {{ $hasCopyButton ? 'right-12' : '' }}">
                <span @class([
                    'rounded-md px-2 py-0.5 text-xs font-medium',
                    'bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300' => $language === 'JSON',
                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => $language === 'Terminal',
                    'bg-purple-100 text-purple-700 dark:bg-purple-500/10 dark:text-purple-300' => !in_array($language, ['JSON', 'Terminal']),
                ])>{{ $language }}</span>
            </div>
        @endif
        <pre class="overflow-x-auto rounded-xl border border-gray-200 bg-gray-900 p-4 {{ $hasCopyButton ? 'pr-16' : '' }} font-mono text-sm dark:border-white/10"><code class="text-gray-100">{{ $code }}</code></pre>
    </div>
@endif
