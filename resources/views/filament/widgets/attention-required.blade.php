<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-exclamation-triangle"
        icon-color="warning"
        :heading="$this->getHeading()"
    >
        <div class="grid gap-4 md:grid-cols-3">
            {{-- Failed Repositories --}}
            @if($this->getFailedRepositories()->isNotEmpty())
                <div class="space-y-2">
                    <h4 class="text-sm font-medium text-danger-600 dark:text-danger-400 flex items-center gap-2">
                        <x-heroicon-o-archive-box class="w-4 h-4" />
                        {{ __('widget.attention.failed_repos') }}
                    </h4>
                    <ul class="space-y-1">
                        @foreach($this->getFailedRepositories() as $repo)
                            <li class="text-sm">
                                <a href="{{ route('filament.admin.resources.repositories.view', $repo) }}"
                                   class="text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400">
                                    {{ $repo->name }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Expiring Tokens --}}
            @if($this->getExpiringTokens()->isNotEmpty())
                <div class="space-y-2">
                    <h4 class="text-sm font-medium text-warning-600 dark:text-warning-400 flex items-center gap-2">
                        <x-heroicon-o-ticket class="w-4 h-4" />
                        {{ __('widget.attention.expiring_tokens') }}
                    </h4>
                    <ul class="space-y-1">
                        @foreach($this->getExpiringTokens() as $token)
                            <li class="text-sm">
                                <a href="{{ route('filament.admin.resources.tokens.view', $token) }}"
                                   class="text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400">
                                    {{ $token->name }}
                                    <span class="text-xs text-gray-500">
                                        ({{ $token->expires_at->diffForHumans() }})
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Invalid Credentials --}}
            @if($this->getInvalidCredentials()->isNotEmpty())
                <div class="space-y-2">
                    <h4 class="text-sm font-medium text-danger-600 dark:text-danger-400 flex items-center gap-2">
                        <x-heroicon-o-key class="w-4 h-4" />
                        {{ __('widget.attention.invalid_credentials') }}
                    </h4>
                    <ul class="space-y-1">
                        @foreach($this->getInvalidCredentials() as $credential)
                            <li class="text-sm">
                                <a href="{{ route('filament.admin.resources.credentials.view', $credential) }}"
                                   class="text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400">
                                    {{ $credential->name }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
