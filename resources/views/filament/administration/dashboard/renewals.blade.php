<x-filament-widgets::widget class="admin-renewals">
    <x-filament::section heading="Renovaciones" description="Lo renovado en el período y lo que vence pronto: ingreso a cuidar.">
        <div class="grid grid-cols-2 gap-3" x-data="{ shown: false }" x-intersect.once="shown = true" wire:key="admin-renewals-{{ $periodKey }}">
            @php
                $cards = [
                    ['label' => 'Renovadas', 'value' => number_format($renewed, 0, ',', '.'), 'hint' => $periodLabel, 'icon' => 'heroicon-o-arrow-path', 'tone' => 'good'],
                    ['label' => 'Vencidas sin renovar', 'value' => number_format($expired, 0, ',', '.'), 'hint' => $periodLabel, 'icon' => 'heroicon-o-x-circle', 'tone' => $expired > 0 ? 'critical' : 'muted'],
                    ['label' => 'Tasa de renovación', 'value' => $rate === null ? '—' : number_format((float) $rate, 1, ',', '.').' %', 'hint' => 'Renovadas sobre las que llegaron a su fecha', 'icon' => 'heroicon-o-chart-pie', 'tone' => 'series1'],
                    ['label' => 'Vencen en '.$horizon.' días', 'value' => number_format($upcoming['count'], 0, ',', '.'), 'hint' => 'US$ '.number_format($upcoming['amount'], 0, ',', '.').' anuales · '.$upcoming['in_window'].' en período de renovación', 'icon' => 'heroicon-o-calendar-days', 'tone' => 'warning'],
                ];
            @endphp

            @foreach ($cards as $index => $card)
                <div
                    x-bind:class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-2'"
                    style="transition-delay: {{ $index * 70 }}ms"
                    class="ops-reveal rounded-xl p-3 ring-1 ring-gray-950/5 dark:ring-white/10"
                >
                    <div class="flex items-center gap-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                        <x-filament::icon :icon="$card['icon']" class="size-4" style="color: var(--ops-{{ $card['tone'] }})" />
                        {{ $card['label'] }}
                    </div>
                    <p class="mt-1 text-2xl font-semibold tabular-nums tracking-tight text-gray-950 dark:text-white">{{ $card['value'] }}</p>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $card['hint'] }}</p>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
