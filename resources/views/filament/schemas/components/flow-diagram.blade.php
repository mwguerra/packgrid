@php
    $actors = $getActors();
    $steps = $getSteps();
@endphp

<div class="space-y-8">
    {{-- Actors Row --}}
    @if(count($actors) > 0)
        <div class="flex justify-center">
            <div class="grid grid-cols-3 gap-8">
                @foreach($actors as $actor)
                    <div class="flex flex-col items-center text-center">
                        <div @class([
                            'flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br shadow-lg',
                            'from-blue-500 to-blue-600 shadow-blue-500/25' => ($actor['color'] ?? '') === 'blue',
                            'from-amber-500 to-orange-500 shadow-amber-500/25' => ($actor['color'] ?? '') === 'amber',
                            'from-gray-700 to-gray-900 shadow-gray-500/25' => ($actor['color'] ?? '') === 'gray',
                            'from-emerald-500 to-teal-500 shadow-emerald-500/25' => ($actor['color'] ?? '') === 'emerald',
                            'from-purple-500 to-indigo-500 shadow-purple-500/25' => ($actor['color'] ?? '') === 'purple',
                        ])>
                            <x-filament::icon
                                :icon="$actor['icon']"
                                class="h-10 w-10 text-white"
                            />
                        </div>
                        <h3 class="mt-3 text-lg font-semibold text-gray-900 dark:text-white">{{ $actor['name'] }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $actor['description'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Flow Steps --}}
    @if(count($steps) > 0)
    <div class="space-y-6">
        @foreach($steps as $index => $step)
            <div class="relative rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-6">
                {{-- Step Number Badge --}}
                <div class="absolute -left-3 -top-3 flex h-8 w-8 items-center justify-center rounded-full bg-primary-500 text-sm font-bold text-white shadow-md">
                    {{ $index + 1 }}
                </div>

                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:gap-6">
                    {{-- Direction Indicator --}}
                    <div class="flex flex-shrink-0 items-center gap-2">
                        <span class="inline-flex items-center justify-center rounded-lg px-2 py-1 text-xs font-medium sm:px-3 sm:py-1.5 {{ $step['fromColor'] }}">
                            {{ $step['from'] }}
                        </span>
                        <x-filament::icon
                            icon="heroicon-o-arrow-right"
                            class="h-4 w-4 text-gray-400 dark:text-gray-500 sm:h-5 sm:w-5"
                        />
                        <span class="inline-flex items-center justify-center rounded-lg px-2 py-1 text-xs font-medium sm:px-3 sm:py-1.5 {{ $step['toColor'] }}">
                            {{ $step['to'] }}
                        </span>
                    </div>

                    {{-- Step Content --}}
                    <div class="min-w-0 flex-1">
                        <h4 class="font-semibold text-gray-900 dark:text-white">{{ $step['title'] }}</h4>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $step['description'] }}</p>

                        @if(isset($step['data']))
                            <div class="mt-3 overflow-x-auto rounded-lg bg-gray-50 p-3 dark:bg-gray-900">
                                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    {{ $step['dataLabel'] ?? 'Data Transferred' }}
                                </p>
                                <code class="block whitespace-pre-wrap break-all text-xs text-gray-700 dark:text-gray-300">{{ $step['data'] }}</code>
                            </div>
                        @endif
                    </div>

                    {{-- Icon (hidden on mobile) --}}
                    @if(isset($step['icon']))
                        <div class="hidden h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl sm:flex {{ $step['iconBg'] ?? 'bg-gray-100 dark:bg-gray-700' }}">
                            <x-filament::icon
                                :icon="$step['icon']"
                                class="h-6 w-6 {{ $step['iconColor'] ?? 'text-gray-600 dark:text-gray-400' }}"
                            />
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    @endif
</div>
