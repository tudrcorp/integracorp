@php
    use App\Models\CorporatePopulationUpdate;
    use App\Models\CorporatePopulationUpdateItem as Item;

    $update = $this->populationUpdate();
    $summary = (array) ($update->summary ?? []);
    $counts = (array) ($summary['counts'] ?? []);
    $selected = $update->status === CorporatePopulationUpdate::STATUS_ANALYZED ? $this->selectedCounts() : (array) ($summary['selected'] ?? []);
    $busy = $update->isBusy();
    $tz = (string) config('app.timezone');
    $tone = [
        'success' => 'bg-emerald-50 text-emerald-800 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-400/30',
        'danger' => 'bg-rose-50 text-rose-800 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-200 dark:ring-rose-400/30',
        'warning' => 'bg-amber-50 text-amber-900 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-400/30',
        'info' => 'bg-sky-50 text-sky-800 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-200 dark:ring-sky-400/30',
        'primary' => 'bg-indigo-50 text-indigo-800 ring-indigo-600/20 dark:bg-indigo-500/10 dark:text-indigo-200 dark:ring-indigo-400/30',
        'gray' => 'bg-gray-50 text-gray-700 ring-gray-500/20 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10',
    ];
    $cards = [
        [Item::ACTION_ADD, 'Agregar', 'Nuevos en la afiliación'],
        [Item::ACTION_REACTIVATE, 'Reactivar', 'Estaban inactivos'],
        [Item::ACTION_DEACTIVATE, 'Dar de baja', 'No están en el archivo'],
        [Item::ACTION_FIX_DOCUMENT, 'Corregir cédula', 'Coinciden con otra cédula'],
        [Item::ACTION_DUPLICATE_BASE, 'Duplicados en base', 'Copias que sobran'],
        [Item::ACTION_REVIEW, 'Revisar', 'No se deciden solos'],
        [Item::ACTION_BLOCKED_OPEN_CASE, 'Con casos abiertos', 'No se dan de baja'],
    ];
@endphp

<x-filament-panels::page>
    <div @if ($busy) wire:poll.3s @endif class="space-y-4">
        <div class="flex flex-wrap items-center gap-3 rounded-xl bg-white px-4 py-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold ring-1 ring-inset {{ $tone[CorporatePopulationUpdate::colorFor($update->status)] ?? $tone['gray'] }}">
                {{ $update->statusLabel() }}
            </span>
            <span class="text-sm text-gray-600 dark:text-gray-300">
                @switch($update->status)
                    @case(CorporatePopulationUpdate::STATUS_ANALYZING)
                        Comparando el archivo con los afiliados… esta pantalla se actualiza sola.
                        @break
                    @case(CorporatePopulationUpdate::STATUS_ANALYZED)
                        Revise las pestañas, ajuste las casillas «Incluir» y pulse «Aprobar y aplicar». Nada ha cambiado todavía.
                        @break
                    @case(CorporatePopulationUpdate::STATUS_APPLYING)
                        Aplicando los cambios aprobados por {{ $update->approved_by_name }}… todo o nada.
                        @break
                    @case(CorporatePopulationUpdate::STATUS_APPLIED)
                        Aplicada el {{ $update->applied_at?->timezone($tz)->format('d/m/Y H:i') }} por {{ $update->approved_by_name }}. Se puede revertir mientras no haya una carga posterior.
                        @break
                    @case(CorporatePopulationUpdate::STATUS_ROLLING_BACK)
                        Revirtiendo… esta pantalla se actualiza sola.
                        @break
                    @case(CorporatePopulationUpdate::STATUS_ROLLED_BACK)
                        Revertida el {{ $update->rolled_back_at?->timezone($tz)->format('d/m/Y H:i') }} por {{ $update->rolled_back_by_name }}. Motivo: {{ $update->rollback_note }}
                        @break
                    @case(CorporatePopulationUpdate::STATUS_DISCARDED)
                        Descartada sin aplicar cambios.
                        @break
                    @default
                        No se completó. No se cambió ningún afiliado.
                @endswitch
            </span>
        </div>

        @if (filled($update->error))
            <div class="rounded-xl px-4 py-3 text-sm ring-1 ring-inset {{ $tone['danger'] }}">{{ $update->error }}</div>
        @endif

        @if ($summary !== [])
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                @foreach ($cards as [$action, $label, $hint])
                    <button type="button" wire:click="setTab('{{ $action }}')"
                        @class([
                            'rounded-xl bg-white p-3 text-left shadow-sm ring-1 transition hover:ring-primary-500 dark:bg-gray-900',
                            'ring-primary-500' => $this->activeTab === $action,
                            'ring-gray-950/5 dark:ring-white/10' => $this->activeTab !== $action,
                        ])>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</p>
                        <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $counts[$action] ?? 0 }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $hint }}
                            @if (in_array($action, [...Item::CHANGING_ACTIONS, Item::ACTION_DUPLICATE_BASE], true) && ($counts[$action] ?? 0) > 0)
                                · {{ $selected[$action] ?? 0 }} incluidos
                            @endif
                        </p>
                    </button>
                @endforeach
            </div>

            <p class="text-xs text-gray-500 dark:text-gray-400">
                Archivo: {{ $summary['file_rows'] ?? 0 }} filas · En la base: {{ $summary['base_countable'] ?? 0 }} afiliados vigentes de {{ $summary['base_total'] ?? 0 }} ·
                Coinciden sin cambios: {{ $counts[Item::ACTION_MATCH] ?? 0 }} · Duplicados en el archivo: {{ $counts[Item::ACTION_DUPLICATE_FILE] ?? 0 }} · Filas incompletas: {{ $counts[Item::ACTION_INVALID] ?? 0 }}
            </p>
        @endif

        @if (filled($update->totals_before) && filled($update->totals_after))
            <div class="grid gap-3 sm:grid-cols-3">
                @foreach (['poblation' => 'Población', 'fee_anual' => 'Tarifa anual (US$)', 'total_amount' => 'Monto por período (US$)'] as $key => $label)
                    <div class="rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</p>
                        <p class="mt-1 text-sm text-gray-950 dark:text-white">
                            <span class="text-gray-500 line-through dark:text-gray-400">{{ is_float($update->totals_before[$key] ?? null) ? number_format($update->totals_before[$key], 2, ',', '.') : ($update->totals_before[$key] ?? '—') }}</span>
                            → <strong>{{ is_float($update->totals_after[$key] ?? null) ? number_format($update->totals_after[$key], 2, ',', '.') : ($update->totals_after[$key] ?? '—') }}</strong>
                        </p>
                    </div>
                @endforeach
            </div>
        @endif

        @if (filled($update->rollback_summary) && ! empty($update->rollback_summary['not_reverted']))
            <div class="rounded-xl px-4 py-3 text-sm ring-1 ring-inset {{ $tone['warning'] }}">
                <p class="font-semibold">{{ count($update->rollback_summary['not_reverted']) }} cambio(s) no se revirtieron:</p>
                <ul class="mt-1 list-disc space-y-0.5 ps-5">
                    @foreach (array_slice($update->rollback_summary['not_reverted'], 0, 20) as $row)
                        <li>{{ $row['name'] ?? '' }} — {{ $row['reason'] ?? '' }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($summary !== [])
            <div class="flex flex-wrap gap-2">
                @foreach ($this->tabs() as $tab)
                    <button type="button" wire:click="setTab('{{ $tab['key'] }}')"
                        @class([
                            'inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium ring-1 ring-inset transition',
                            'bg-primary-600 text-white ring-primary-600' => $this->activeTab === $tab['key'],
                            'bg-white text-gray-700 ring-gray-950/10 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/5' => $this->activeTab !== $tab['key'],
                        ])>
                        {{ $tab['label'] }}
                        <span class="rounded-full bg-black/10 px-1.5 text-xs dark:bg-white/10">{{ $tab['count'] }}</span>
                    </button>
                @endforeach
            </div>

            {{ $this->table }}
        @endif
    </div>
</x-filament-panels::page>
