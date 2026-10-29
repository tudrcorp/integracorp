@php
    /**
     * @var list<array<string, mixed>> $users
     * @var array<string, int> $totals
     * @var array<string, int> $previous
     */
    $columns = [
        'created' => ['label' => 'Altas', 'hint' => 'Proveedores registrados en el período (de los propios registros).'],
        'updated' => ['label' => 'Actualizaciones', 'hint' => 'Veces que editó la ficha de un proveedor.'],
        'status' => ['label' => 'Cambios de estatus', 'hint' => 'Cambios del estatus del convenio.'],
        'won' => ['label' => 'Convenios logrados', 'hint' => 'Cambios de estatus a Activo afiliado, Afiliado o Activo.'],
        'agreement' => ['label' => 'Tipo de convenio', 'hint' => 'Cambios entre general, preferencial, contado o no convenido.'],
        'letters' => ['label' => 'Cartas cargadas', 'hint' => 'Cartas de aceptación cargadas o reemplazadas.'],
    ];
    $max = $users === [] ? 0 : max(array_column($users, 'score'));
@endphp

<x-filament-widgets::widget>
    <x-filament::section>
        <div x-data="{ shown: false }" x-intersect.once="shown = true">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Productividad del equipo de proveedores</h3>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                        {{ $periodLabel }}{{ $compare ? ' · comparado con '.$previousLabel : '' }}.
                        Las altas salen de cada registro; actualizaciones, estatus, convenios y cartas se registran desde {{ $since ?? 'hoy' }}.
                    </p>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                @foreach ($columns as $key => $column)
                    @php
                        $value = $totals[$key] ?? 0;
                        $before = $previous[$key] ?? 0;
                        $delta = \App\Filament\Operations\Widgets\Dashboard\OperationsKpiTiles::delta($value, $before);
                    @endphp
                    <div class="rounded-xl bg-gray-50 p-3 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10" x-tooltip="{ content: @js($column['hint']), theme: $store.theme }">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $column['label'] }}</p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($value, 0, ',', '.') }}</p>
                        @if ($compare)
                            <p class="text-xs font-semibold" style="color: {{ $delta['direction'] === 'flat' ? 'var(--ops-muted)' : ($delta['direction'] === 'up' ? 'var(--ops-good-ink)' : 'var(--ops-bad-ink)') }}">{{ $delta['text'] }} <span class="font-normal text-gray-400">vs ant.</span></p>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                            <th class="py-2 pe-3 font-medium">Persona</th>
                            @foreach ($columns as $column)
                                <th class="px-2 py-2 text-end font-medium">{{ $column['label'] }}</th>
                            @endforeach
                            <th class="w-40 py-2 ps-3 font-medium">Actividad</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <td class="py-2 pe-3 font-medium {{ $user['user'] === $unknown ? 'text-gray-400 dark:text-gray-500' : 'text-gray-950 dark:text-white' }}">
                                    {{ $user['user'] }}
                                    @if ($user['created'] > 0)
                                        <span class="block text-[11px] font-normal text-gray-500 dark:text-gray-400">
                                            {{ collect(['juridico' => 'jurídicos', 'natural' => 'naturales', 'aliado' => 'aliados'])->map(fn ($label, $type) => $user['created_'.$type] > 0 ? $user['created_'.$type].' '.$label : null)->filter()->implode(' · ') }}
                                        </span>
                                    @endif
                                </td>
                                @foreach ($columns as $key => $column)
                                    <td class="px-2 py-2 text-end tabular-nums {{ ($user[$key] ?? 0) > 0 ? 'text-gray-950 dark:text-white' : 'text-gray-300 dark:text-gray-600' }}">{{ number_format($user[$key] ?? 0, 0, ',', '.') }}</td>
                                @endforeach
                                <td class="py-2 ps-3">
                                    <div class="h-2 overflow-hidden rounded-full" style="background: var(--ops-track)">
                                        <div class="ops-bar-fill h-full rounded-full" x-bind:style="'width: ' + (shown ? {{ $max > 0 ? $user['score'] * 100 / $max : 0 }} : 0) + '%; background: var(--ops-series1)'"></div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($columns) + 2 }}" class="py-10 text-center text-gray-500 dark:text-gray-400">Sin actividad del equipo en el período elegido.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">«{{ $unknown }}»: registros viejos que no guardaron quién los cargó. Desde ahora el sistema lo registra solo.</p>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
