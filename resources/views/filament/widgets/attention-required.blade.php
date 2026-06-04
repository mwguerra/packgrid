@php
    use Illuminate\Support\Str;
@endphp

<x-filament-widgets::widget>
    <x-filament::section
        icon="heroicon-o-exclamation-triangle"
        icon-color="warning"
        :heading="$this->getHeading()"
    >
        {{-- Instance-wide operational alerts (backups, garbage collection) --}}
        @if(! empty($operationalAlerts))
            @php
                // Literal class strings (not interpolated) so Tailwind's scanner picks them up.
                $alertClasses = [
                    'danger' => 'bg-danger-50 dark:bg-danger-500/10 text-danger-700 dark:text-danger-400',
                    'warning' => 'bg-warning-50 dark:bg-warning-500/10 text-warning-700 dark:text-warning-400',
                ];
            @endphp
            <div class="mb-4 space-y-2">
                @foreach($operationalAlerts as $alert)
                    @php
                        $alertUrl = $alert['key'] === 'gc_disabled'
                            ? (\Illuminate\Support\Facades\Route::has('filament.admin.resources.docker-repositories.index') ? route('filament.admin.resources.docker-repositories.index') : null)
                            : (\Illuminate\Support\Facades\Route::has('filament.admin.pages.backup-restore') ? route('filament.admin.pages.backup-restore') : null);
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
                    <h4 class="text-sm font-medium text-danger-600 dark:text-danger-400 flex items-center justify-between gap-2">
                        <span class="flex items-center gap-2">
                            <x-heroicon-o-archive-box class="w-4 h-4" />
                            {{ __('widget.attention.failed_repos') }}
                        </span>
                        <x-filament::badge color="danger" size="sm">{{ $failedRepositoriesCount }}</x-filament::badge>
                    </h4>
                    <ul class="space-y-2">
                        @foreach($failedRepositories as $repo)
                            <li class="text-sm">
                                <a href="{{ route('filament.admin.resources.repositories.view', $repo) }}"
                                   class="font-medium text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400">
                                    {{ $repo->name }}
                                </a>
                                @if($repo->last_error)
                                    <p class="text-xs text-gray-500 dark:text-gray-400" title="{{ $repo->last_error }}">
                                        {{ Str::limit($repo->last_error, 70) }}
                                    </p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if($failedRepositoriesCount > $failedRepositories->count())
                        <a href="{{ route('filament.admin.resources.repositories.index') }}"
                           class="text-xs text-primary-600 dark:text-primary-400 hover:underline">
                            {{ __('widget.attention.view_all', ['count' => $failedRepositoriesCount - $failedRepositories->count()]) }}
                        </a>
                    @endif
                </div>
            @endif

            {{-- Stale / never-synced Repositories --}}
            @if($staleRepositories->isNotEmpty())
                <div class="space-y-2">
                    <h4 class="text-sm font-medium text-warning-600 dark:text-warning-400 flex items-center justify-between gap-2">
                        <span class="flex items-center gap-2">
                            <x-heroicon-o-clock class="w-4 h-4" />
                            {{ __('widget.attention.stale_repos') }}
                        </span>
                        <x-filament::badge color="warning" size="sm">{{ $staleRepositoriesCount }}</x-filament::badge>
                    </h4>
                    <ul class="space-y-1">
                        @foreach($staleRepositories as $repo)
                            <li class="text-sm">
                                <a href="{{ route('filament.admin.resources.repositories.view', $repo) }}"
                                   class="text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400">
                                    {{ $repo->name }}
                                    <span class="text-xs text-gray-500">
                                        ({{ $repo->last_sync_at ? $repo->last_sync_at->diffForHumans() : __('widget.attention.never_synced') }})
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    @if($staleRepositoriesCount > $staleRepositories->count())
                        <a href="{{ route('filament.admin.resources.repositories.index') }}"
                           class="text-xs text-primary-600 dark:text-primary-400 hover:underline">
                            {{ __('widget.attention.view_all', ['count' => $staleRepositoriesCount - $staleRepositories->count()]) }}
                        </a>
                    @endif
                </div>
            @endif

            {{-- Expiring / expired Tokens --}}
            @if($problematicTokens->isNotEmpty())
                <div class="space-y-2">
                    <h4 class="text-sm font-medium text-warning-600 dark:text-warning-400 flex items-center justify-between gap-2">
                        <span class="flex items-center gap-2">
                            <x-heroicon-o-ticket class="w-4 h-4" />
                            {{ __('widget.attention.expiring_tokens') }}
                        </span>
                        <x-filament::badge color="warning" size="sm">{{ $problematicTokensCount }}</x-filament::badge>
                    </h4>
                    <ul class="space-y-1">
                        @foreach($problematicTokens as $token)
                            <li class="text-sm">
                                <a href="{{ route('filament.admin.resources.tokens.view', $token) }}"
                                   class="text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400">
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
                        <a href="{{ route('filament.admin.resources.tokens.index') }}"
                           class="text-xs text-primary-600 dark:text-primary-400 hover:underline">
                            {{ __('widget.attention.view_all', ['count' => $problematicTokensCount - $problematicTokens->count()]) }}
                        </a>
                    @endif
                </div>
            @endif

            {{-- Invalid Credentials --}}
            @if($invalidCredentials->isNotEmpty())
                <div class="space-y-2">
                    <h4 class="text-sm font-medium text-danger-600 dark:text-danger-400 flex items-center justify-between gap-2">
                        <span class="flex items-center gap-2">
                            <x-heroicon-o-key class="w-4 h-4" />
                            {{ __('widget.attention.invalid_credentials') }}
                        </span>
                        <x-filament::badge color="danger" size="sm">{{ $invalidCredentialsCount }}</x-filament::badge>
                    </h4>
                    <ul class="space-y-2">
                        @foreach($invalidCredentials as $credential)
                            <li class="text-sm">
                                <a href="{{ route('filament.admin.resources.credentials.view', $credential) }}"
                                   class="font-medium text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400">
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
                        <a href="{{ route('filament.admin.resources.credentials.index') }}"
                           class="text-xs text-primary-600 dark:text-primary-400 hover:underline">
                            {{ __('widget.attention.view_all', ['count' => $invalidCredentialsCount - $invalidCredentials->count()]) }}
                        </a>
                    @endif
                </div>
            @endif

            {{-- Stale Docker uploads --}}
            @if($staleUploadsCount > 0)
                <div class="space-y-2">
                    <h4 class="text-sm font-medium text-warning-600 dark:text-warning-400 flex items-center justify-between gap-2">
                        <span class="flex items-center gap-2">
                            <x-heroicon-o-cloud-arrow-up class="w-4 h-4" />
                            {{ __('widget.attention.stale_uploads') }}
                        </span>
                        <x-filament::badge color="warning" size="sm">{{ $staleUploadsCount }}</x-filament::badge>
                    </h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ __('widget.attention.stale_uploads_desc') }}
                    </p>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
