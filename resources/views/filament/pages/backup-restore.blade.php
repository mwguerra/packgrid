@php($state = $this->systemState())

<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        {{-- Main column: backup & restore --}}
        <div class="space-y-6 xl:col-span-2">
            <x-filament::section
                :heading="__('backup.section.backup')"
                :description="__('backup.section.backup_description')"
            >
                @if($state['lastBackupAt'])
                    <p class="text-xl font-semibold text-gray-950 dark:text-white">
                        {{ $state['lastBackupAt']->diffForHumans() }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $state['lastBackupAt']->toDayDateTimeString() }}
                    </p>
                @else
                    <p class="text-xl font-semibold text-gray-400 dark:text-gray-500">
                        {{ __('common.never') }}
                    </p>
                @endif
            </x-filament::section>

            <x-filament::section
                :heading="__('backup.section.restore')"
                :description="__('backup.section.restore_description')"
            >
                @if($state['lastRestoreAt'])
                    <p class="text-xl font-semibold text-gray-950 dark:text-white">
                        {{ $state['lastRestoreAt']->diffForHumans() }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $state['lastRestoreAt']->toDayDateTimeString() }}
                    </p>
                @else
                    <p class="text-xl font-semibold text-gray-400 dark:text-gray-500">
                        {{ __('common.never') }}
                    </p>
                @endif
            </x-filament::section>
        </div>

        {{-- System state: a sidebar (1/3) from xl up; stacks to the bottom below xl --}}
        <div class="xl:col-span-1">
            <x-filament::section
                icon="heroicon-o-server-stack"
                :heading="__('backup.section.state')"
                :description="__('backup.section.state_description')"
            >
                <table class="w-full table-fixed text-sm">
                    <colgroup>
                        <col style="width: 45%">
                        <col style="width: 55%">
                    </colgroup>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach($state['rows'] as $row)
                            @if(!empty($row['authenticated']))
                                {{-- Long value (encryption) spans the full width so it never cramps --}}
                                <tr>
                                    <td colspan="2" class="py-3">
                                        <div class="text-gray-500 dark:text-gray-400">{{ $row['label'] }}</div>
                                        <div class="mt-1 flex flex-wrap items-center gap-2">
                                            <span class="font-medium text-gray-950 dark:text-white break-words">{{ $row['value'] }}</span>
                                            <x-filament::badge color="success" size="sm">
                                                {{ __('backup.state.authenticated') }}
                                            </x-filament::badge>
                                        </div>
                                    </td>
                                </tr>
                            @else
                                <tr>
                                    <th scope="row" class="py-3 pr-2 text-left align-top font-normal text-gray-500 dark:text-gray-400">
                                        {{ $row['label'] }}
                                    </th>
                                    <td class="py-3 pl-2 text-right align-top font-medium text-gray-950 dark:text-white break-words">
                                        {{ $row['value'] }}
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
