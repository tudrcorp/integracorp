<x-filament-widgets::widget class="admin-upcoming-dates">
    <x-filament::section heading="Próximas fechas" description="Cumpleaños y aniversarios laborales de los próximos 30 días.">
        @if ($events === [])
            <div class="flex h-48 flex-col items-center justify-center gap-2 text-center">
                <x-filament::icon icon="heroicon-o-cake" class="size-8 text-gray-300 dark:text-gray-600" />
                <p class="text-sm text-gray-500 dark:text-gray-400">No hay cumpleaños ni aniversarios en los próximos 30 días.</p>
            </div>
        @else
            <ul class="space-y-1">
                @foreach ($events as $event)
                    <li class="flex items-center gap-3 rounded-lg px-2 py-1.5 text-sm" wire:key="admin-date-{{ md5($event['name'].$event['kind']) }}">
                        <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-lg {{ $event['kind'] === 'Cumpleaños' ? 'bg-pink-50 text-pink-600 dark:bg-pink-500/10 dark:text-pink-400' : 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400' }}">
                            <x-filament::icon :icon="$event['kind'] === 'Cumpleaños' ? 'heroicon-o-cake' : 'heroicon-o-trophy'" class="size-4" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-gray-950 dark:text-white" title="{{ $event['name'] }}">{{ $event['name'] }}</span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $event['kind'] }} · {{ $event['detail'] }}</span>
                        </span>
                        <span class="shrink-0 text-end">
                            <span class="block text-xs font-semibold tabular-nums text-gray-950 dark:text-white">{{ $event['date'] }}</span>
                            <span class="block text-[11px] text-gray-500 dark:text-gray-400">{{ $event['days'] === 0 ? 'Hoy' : ($event['days'] === 1 ? 'Mañana' : 'En '.$event['days'].' días') }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
