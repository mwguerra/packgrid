@php
    $features = $getFeatures();
    $products = $getProducts();
@endphp

<div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-gray-50 dark:bg-white/5">
                <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-400">Feature</th>
                @foreach($products as $product)
                    <th class="px-4 py-3 text-center font-medium text-gray-900 dark:text-white">
                        <div class="flex flex-col items-center gap-1">
                            <span>{{ $product['name'] }}</span>
                            @if(isset($product['highlight']) && $product['highlight'])
                                <span class="inline-flex items-center rounded-full bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-400">
                                    You are here
                                </span>
                            @endif
                        </div>
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
            @foreach($features as $feature)
                <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                        {{ $feature['name'] }}
                        @if(isset($feature['description']))
                            <p class="text-xs text-gray-500 dark:text-gray-500">{{ $feature['description'] }}</p>
                        @endif
                    </td>
                    @foreach($products as $productKey => $product)
                        <td class="px-4 py-3 text-center">
                            @php
                                $value = $feature['values'][$productKey] ?? null;
                            @endphp
                            @if($value === true)
                                <x-heroicon-s-check-circle class="mx-auto h-5 w-5 text-emerald-500" />
                            @elseif($value === false)
                                <x-heroicon-s-x-circle class="mx-auto h-5 w-5 text-gray-300 dark:text-gray-600" />
                            @elseif($value === 'planned')
                                <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-500/10 dark:text-blue-400">
                                    <x-heroicon-s-clock class="h-3 w-3" />
                                    Planned
                                </span>
                            @elseif($value === 'partial')
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-500/10 dark:text-amber-400">
                                    Partial
                                </span>
                            @elseif(is_string($value))
                                <span class="text-gray-700 dark:text-gray-300">{!! $value !!}</span>
                            @else
                                <span class="text-gray-400 dark:text-gray-600">—</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
