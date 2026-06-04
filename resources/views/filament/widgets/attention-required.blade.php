@php
    use Illuminate\Support\Str;
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-exclamation-triangle"
        icon-color="warning"
        :heading="$this->getHeading()"
    >
        {{-- Scheduler-down diagnosis: many repos overdue at once usually means cron isn't running --}}
        @if($schedulerLikelyDown)
            <div class="mb-4 flex items-start gap-2 rounded-lg bg-danger-50 px-4 py-3 dark:bg-danger-500/10">
                <x-heroicon-o-bolt-slash class="mt-0.5 h-5 w-5 shrink-0 text-danger-600 dark:text-danger-400" />
                <div class="space-y-1">
                    <p class="text-sm font-semibold text-danger-700 dark:text-danger-400">{{ __('widget.attention.scheduler_down_heading') }}</p>
                    <p class="text-xs text-danger-700/90 dark:text-danger-400/90">{{ __('widget.attention.scheduler_down_body') }}</p>
                </div>
            </div>
        @endif

        {{-- Instance-wide operational alerts (garbage collection) --}}
        @if(! empty($operationalAlerts))
            @php
                $alertClasses = [
                    'danger' => 'bg-danger-50 dark:bg-danger-500/10 text-danger-700 dark:text-danger-400',
                    'warning' => 'bg-warning-50 dark:bg-warning-500/10 text-warning-700 dark:text-warning-400',
                ];
            @endphp
            <div class="mb-4 space-y-2">
                @foreach($operationalAlerts as $alert)
                    @php
                        $alertUrl = \Illuminate\Support\Facades\Route::has('filament.admin.resources.docker-repositories.index')
                            ? route('filament.admin.resources.docker-repositories.index') : null;
                    @endphp
                    <a @if($alertUrl) href="{{ $alertUrl }}" @endif
                       class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm hover:opacity-80 {{ $alertClasses[$alert['color']] ?? $alertClasses['warning'] }}">
                        <x-heroicon-o-exclamation-circle class="w-4 h-4 shrink-0" />
                        <span>{{ __('widget.attention.' . $alert['key']) }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        <div wire:poll.60s class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            {{-- Failed Repositories --}}
            @if($failedRepositories->isNotEmpty())
                <div class="space-y-2">
                    <h4 class="flex items-center justify-between gap-2 text-sm font-medium text-danger-600 dark:text-danger-400">
                        <span class="flex items-center gap-2">
                            <x-heroicon-o-archive-box class="h-4 w-4" />
                            {{ __('widget.attention.failed_repos') }}
                        </span>
                        <x-filament::badge color="danger" size="sm">{{ $failedRepositoriesCount }}</x-filament::badge>
                    </h4>
                    <ul class="space-y-2">
                        @foreach($failedRepositories as $repo)
                            <li class="text-sm">
                                <div class="flex items-center justify-between gap-2">
                                    <a href="{{ route('filament.admin.resources.repositories.view', $repo) }}"
                                       class="font-medium text-gray-700 hover:text-primary-600 dark:text-gray-300 dark:hover:text-primary-400">
                                        {{ $repo->name }}
                                    </a>
                                    <button type="button" wire:click="syncRepository('{{ $repo->id }}')"
                                            wire:loading.attr="disabled" wire:target="syncRepository"
                                            class="shrink-0 text-xs font-medium text-danger-600 hover:underline disabled:opacity-50 dark:text-danger-400">
                                        {{ __('repository.action.retry_sync') }}
                                    </button>
                                </div>
                                @if($repo->last_error)
                                    <p class="text-xs text-gray-500 dark:text-gray-400" title="{{ $repo->last_error }}">
                                        {{ Str::limit($repo->last_error, 70) }}
                                    </p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if($failedRepositoriesCount > $failedRepositories->count())
                        <a href="{{ route('filament.admin.resources.repositories.index') }}" class="text-xs text-primary-600 hover:underline dark:text-primary-400">
                            {{ __('widget.attention.view_all', ['count' => $failedRepositoriesCount - $failedRepositories->count()]) }}
                        </a>
                    @endif
                </div>
            @endif

            {{-- Outdated mirrors (overdue: synced before, but not in the last 5h) --}}
            @if($overdueRepositories->isNotEmpty())
                <div class="space-y-2">
                    <h4 class="flex items-center justify-between gap-2 text-sm font-medium text-warning-600 dark:text-warning-400">
                        <span class="flex items-center gap-2">
                            <x-heroicon-o-clock class="h-4 w-4" />
                            {{ __('widget.attention.overdue_repos') }}
                        </span>
                        <x-filament::badge color="warning" size="sm">{{ $overdueCount }}</x-filament::badge>
                    </h4>
                    <ul class="space-y-2">
                        @foreach($overdueRepositories as $repo)
                            <li class="text-sm">
                                <div class="flex items-center justify-between gap-2">
                                    <a href="{{ route('filament.admin.resources.repositories.view', $repo) }}"
                                       class="text-gray-700 hover:text-primary-600 dark:text-gray-300 dark:hover:text-primary-400">
                                        {{ $repo->name }}
                                    </a>
                                    <button type="button" wire:click="syncRepository('{{ $repo->id }}')"
                                            wire:loading.attr="disabled" wire:target="syncRepository"
                                            class="shrink-0 text-xs font-medium text-primary-600 hover:underline disabled:opacity-50 dark:text-primary-400">
                                        {{ __('widget.attention.sync_now') }}
                                    </button>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ __('widget.attention.last_refreshed', ['time' => $repo->last_sync_at->diffForHumans()]) }}
                                </p>
                            </li>
                        @endforeach
                    </ul>
                    @if($overdueCount > $overdueRepositories->count())
                        <a href="{{ route('filament.admin.resources.repositories.index') }}" class="text-xs text-primary-600 hover:underline dark:text-primary-400">
                            {{ __('widget.attention.view_all', ['count' => $overdueCount - $overdueRepositories->count()]) }}
                        </a>
                    @endif
                </div>
            @endif

            {{-- Awaiting first sync (never synced — benign, just added) --}}
            @if($awaitingRepositories->isNotEmpty())
                <div class="space-y-2">
                    <h4 class="flex items-center justify-between gap-2 text-sm font-medium text-gray-600 dark:text-gray-300">
                        <span class="flex items-center gap-2">
                            <x-heroicon-o-arrow-path class="h-4 w-4" />
                            {{ __('widget.attention.awaiting_repos') }}
                        </span>
                        <x-filament::badge color="gray" size="sm">{{ $awaitingCount }}</x-filament::badge>
                    </h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('widget.attention.awaiting_repos_hint') }}</p>
                    <ul class="space-y-1">
                        @foreach($awaitingRepositories as $repo)
                            <li class="flex items-center justify-between gap-2 text-sm">
                                <a href="{{ route('filament.admin.resources.repositories.view', $repo) }}"
                                   class="text-gray-700 hover:text-primary-600 dark:text-gray-300 dark:hover:text-primary-400">
                                    {{ $repo->name }}
                                </a>
                                <button type="button" wire:click="syncRepository('{{ $repo->id }}')"
                                        wire:loading.attr="disabled" wire:target="syncRepository"
                                        class="shrink-0 text-xs font-medium text-primary-600 hover:underline disabled:opacity-50 dark:text-primary-400">
                                    {{ __('widget.attention.sync_now') }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                    @if($awaitingCount > $awaitingRepositories->count())
                        <a href="{{ route('filament.admin.resources.repositories.index') }}" class="text-xs text-primary-600 hover:underline dark:text-primary-400">
                            {{ __('widget.attention.view_all', ['count' => $awaitingCount - $awaitingRepositories->count()]) }}
                        </a>
                    @endif
                </div>
            @endif

            {{-- Expiring / expired Tokens --}}
            @if($problematicTokens->isNotEmpty())
                <div class="space-y-2">
                    <h4 class="flex items-center justify-between gap-2 text-sm font-medium text-warning-600 dark:text-warning-400">
                        <span class="flex items-center gap-2">
                            <x-heroicon-o-ticket class="h-4 w-4" />
                            {{ __('widget.attention.expiring_tokens') }}
                        </span>
                        <x-filament::badge color="warning" size="sm">{{ $problematicTokensCount }}</x-filament::badge>
                    </h4>
                    <ul class="space-y-1">
                        @foreach($problematicTokens as $token)
                            <li class="text-sm">
                                <a href="{{ route('filament.admin.resources.tokens.view', $token) }}"
                                   class="text-gray-700 hover:text-primary-600 dark:text-gray-300 dark:hover:text-primary-400">
                                    {{ $token->name }}
                                    <span @class([
                                        'text-xs',
                                        'text-danger-500' => $token->expires_at->isPast(),
                                        'text-gray-500' => ! $token->expires_at->isPast(),
                                    ])>
                                        ({{ $token->expires_at->diffForHumans() }})
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    @if($problematicTokensCount > $problematicTokens->count())
                        <a href="{{ route('filament.admin.resources.tokens.index') }}" class="text-xs text-primary-600 hover:underline dark:text-primary-400">
                            {{ __('widget.attention.view_all', ['count' => $problematicTokensCount - $problematicTokens->count()]) }}
                        </a>
                    @endif
                </div>
            @endif

            {{-- Invalid Credentials --}}
            @if($invalidCredentials->isNotEmpty())
                <div class="space-y-2">
                    <h4 class="flex items-center justify-between gap-2 text-sm font-medium text-danger-600 dark:text-danger-400">
                        <span class="flex items-center gap-2">
                            <x-heroicon-o-key class="h-4 w-4" />
                            {{ __('widget.attention.invalid_credentials') }}
                        </span>
                        <x-filament::badge color="danger" size="sm">{{ $invalidCredentialsCount }}</x-filament::badge>
                    </h4>
                    <ul class="space-y-2">
                        @foreach($invalidCredentials as $credential)
                            <li class="text-sm">
                                <a href="{{ route('filament.admin.resources.credentials.view', $credential) }}"
                                   class="font-medium text-gray-700 hover:text-primary-600 dark:text-gray-300 dark:hover:text-primary-400">
                                    {{ $credential->name }}
                                </a>
                                @if($credential->last_error)
                                    <p class="text-xs text-gray-500 dark:text-gray-400" title="{{ $credential->last_error }}">
                                        {{ Str::limit($credential->last_error, 70) }}
                                    </p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if($invalidCredentialsCount > $invalidCredentials->count())
                        <a href="{{ route('filament.admin.resources.credentials.index') }}" class="text-xs text-primary-600 hover:underline dark:text-primary-400">
                            {{ __('widget.attention.view_all', ['count' => $invalidCredentialsCount - $invalidCredentials->count()]) }}
                        </a>
                    @endif
                </div>
            @endif

            {{-- Stale Docker uploads --}}
            @if($staleUploadsCount > 0)
                <div class="space-y-2">
                    <h4 class="flex items-center justify-between gap-2 text-sm font-medium text-warning-600 dark:text-warning-400">
                        <span class="flex items-center gap-2">
                            <x-heroicon-o-cloud-arrow-up class="h-4 w-4" />
                            {{ __('widget.attention.stale_uploads') }}
                        </span>
                        <x-filament::badge color="warning" size="sm">{{ $staleUploadsCount }}</x-filament::badge>
                    </h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('widget.attention.stale_uploads_desc') }}</p>
                </div>
            @endif
        </div>

        {{-- One-line explainer of what "sync" means --}}
        <p class="mt-4 flex items-start gap-1.5 border-t border-gray-100 pt-3 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
            <x-heroicon-o-information-circle class="mt-0.5 h-3.5 w-3.5 shrink-0" />
            <span>{{ __('widget.attention.what_is_sync') }}</span>
        </p>
    </x-filament::section>
</x-filament-widgets::widget>
