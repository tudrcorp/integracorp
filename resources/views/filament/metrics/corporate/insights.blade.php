@php
    /** @var list<array{tone: string, area: string, title: string, text: string, url_key: string}> $insights */
    $tones = [
        'critical' => ['label' => 'Crítico', 'icon' => 'heroicon-m-exclamation-circle', 'token' => 'critical'],
        'warning' => ['label' => 'Atención', 'icon' => 'heroicon-m-exclamation-triangle', 'token' => 'warning'],
        'good' => ['label' => 'Bien', 'icon' => 'heroicon-m-check-circle', 'token' => 'good'],
        'neutral' => ['label' => 'Dato', 'icon' => 'heroicon-m-information-circle', 'token' => 'muted'],
    ];
@endphp

<x-filament-widgets::widget class="corp-insights">
    <x-filament::section heading="Lectura del período" description="Lo que pide atención primero. Reglas fijas sobre las cifras de todas las áreas.">
        @if ($insights === [])
            <div class="flex h-48 flex-col items-center justify-center gap-2 text-center">
                <x-filament::icon icon="heroicon-o-light-bulb" class="size-8 text-gray-300 dark:text-gray-600" />
                <p class="text-sm text-gray-500 dark:text-gray-400">Aún no hay movimientos suficientes en este período.</p>
            </div>
        @else
            <ul class="space-y-2" x-data="{ shown: false }" x-intersect.once="shown = true" wire:key="corp-insights-{{ $periodKey }}">
                @foreach ($insights as $index => $insight)
                    @php
                        $tone = $tones[$insight['tone']] ?? $tones['neutral'];
                        $url = $urls[$insight['url_key']] ?? null;
                    @endphp
                    <li
                        wire:key="corp-insight-{{ $index }}-{{ md5($insight['title']) }}"
                        x-bind:class="shown ? 'opacity-100 translate-x-0' : 'opacity-0 -translate-x-2'"
                        style="transition-delay: {{ $index * 60 }}ms"
                        class="ops-reveal"
                    >
                        <{{ $url ? 'a' : 'div' }}
                            @if ($url) href="{{ $url }}" wire:navigate @endif
                            class="group flex gap-3 rounded-xl p-2.5 ring-1 ring-gray-950/5 transition dark:ring-white/10 {{ $url ? 'hover:bg-gray-50 hover:ring-gray-950/10 dark:hover:bg-white/5' : '' }}"
                        >
                            <span class="mt-0.5 shrink-0" style="color: var(--ops-{{ $tone['token'] }})">
                                <x-filament::icon :icon="$tone['icon']" class="size-5" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-x-2 text-[11px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <span>{{ $tone['label'] }}</span>
                                    <span aria-hidden="true">·</span>
                                    <span>{{ $insight['area'] }}</span>
                                </span>
                                <span class="mt-0.5 block text-sm font-semibold text-gray-950 dark:text-white">{{ $insight['title'] }}</span>
                                <span class="mt-0.5 block text-xs leading-relaxed text-gray-600 dark:text-gray-300">{{ $insight['text'] }}</span>
                            </span>
                            @if ($url)
                                <x-filament::icon icon="heroicon-m-chevron-right" class="mt-1 size-4 shrink-0 text-gray-300 transition group-hover:translate-x-0.5 group-hover:text-gray-500 dark:text-gray-600" />
                            @endif
                        </{{ $url ? 'a' : 'div' }}>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
