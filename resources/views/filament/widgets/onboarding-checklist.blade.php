<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-rocket-launch"
        icon-color="primary"
        :heading="$this->getHeading()"
        :description="__('widget.onboarding.subheading', ['done' => $doneCount, 'total' => $totalCount])"
    >
        <ul class="divide-y divide-gray-100 dark:divide-white/10">
            @foreach($steps as $step)
                <li class="flex items-center gap-3 py-3">
                    @if($step['done'])
                        <x-heroicon-s-check-circle class="w-6 h-6 text-success-500 shrink-0" />
                    @else
                        <x-heroicon-o-arrow-right-circle class="w-6 h-6 text-gray-400 shrink-0" />
                    @endif

                    <div class="flex-1">
                        <p @class([
                            'text-sm font-medium',
                            'text-gray-400 line-through dark:text-gray-500' => $step['done'],
                            'text-gray-900 dark:text-gray-100' => ! $step['done'],
                        ])>
                            {{ __('widget.onboarding.' . $step['key']) }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ __('widget.onboarding.' . $step['key'] . '_desc') }}
                        </p>
                    </div>

                    @if($step['done'])
                        <x-filament::badge color="success">{{ __('widget.onboarding.done') }}</x-filament::badge>
                    @elseif($step['url'])
                        <x-filament::button tag="a" :href="$step['url']" size="sm" color="primary" outlined>
                            {{ __('widget.onboarding.action') }}
                        </x-filament::button>
                    @endif
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
