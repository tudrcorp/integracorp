@php
    /** @var array<string, mixed> $analysis */
    $tones = [
        'critical' => ['label' => 'Crítico', 'icon' => 'heroicon-m-exclamation-circle', 'token' => 'critical'],
        'warning' => ['label' => 'Atención', 'icon' => 'heroicon-m-exclamation-triangle', 'token' => 'warning'],
        'good' => ['label' => 'Bien', 'icon' => 'heroicon-m-check-circle', 'token' => 'good'],
        'neutral' => ['label' => 'Dato', 'icon' => 'heroicon-m-information-circle', 'token' => 'muted'],
    ];
    $typeLabels = ['individual' => 'Individuales', 'corporate' => 'Corporativas'];
    $compare = (bool) $analysis['compare'];

    $n = fn (int|float $value): string => number_format((float) $value, 0, ',', '.');
    $pct = fn (?float $value): string => $value === null ? '—' : number_format($value, 1, ',', '.').' %';

    /**
     * Variación con color: $higherIsBetter decide si subir es bueno.
     *
     * @return array{text: string, color: string, icon: string}
     */
    $delta = function (?float $value, bool $higherIsBetter = true, string $unit = '%'): array {
        if ($value === null) {
            return ['text' => 'sin base', 'color' => 'var(--ops-muted)', 'icon' => 'heroicon-m-minus'];
        }
        if (abs($value) < 0.05) {
            return ['text' => 'sin cambios', 'color' => 'var(--ops-muted)', 'icon' => 'heroicon-m-minus'];
        }
        $good = ($value > 0) === $higherIsBetter;

        return [
            'text' => ($value > 0 ? '+' : '−').number_format(abs($value), 1, ',', '.').($unit === 'pp' ? ' pp' : ' %'),
            'color' => $good ? 'var(--ops-good-ink)' : 'var(--ops-bad-ink)',
            'icon' => $value > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down',
        ];
    };
@endphp

<x-filament-widgets::widget class="corp-plan-quotes-analysis">
    <x-filament::section
        heading="Análisis de cotizaciones por plan"
        :description="'Individuales y corporativas por separado · '.$periodLabel.($compare ? ' frente a '.$previousLabel : '')"
        icon="heroicon-o-presentation-chart-bar"
    >
        <div class="space-y-6" wire:key="plan-quotes-analysis-{{ $periodKey }}">
            {{-- Totales del período por tipo --}}
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($typeLabels as $type => $typeLabel)
                    @php
                        $total = $analysis['totals'][$type];
                        $change = $compare ? $delta(\App\Support\Metrics\Corporate\CorporateMetrics::change((float) $total['current'], (float) $total['previous'])) : null;
                    @endphp
                    <div class="flex items-center justify-between gap-3 rounded-2xl bg-gray-50 px-4 py-3 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Cotizaciones {{ mb_strtolower($typeLabel) }} creadas</p>
                            <p class="text-2xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ $n($total['current']) }}</p>
                        </div>
                        @if ($change)
                            <div class="text-right text-xs">
                                <span class="inline-flex items-center gap-1 font-semibold" style="color: {{ $change['color'] }}">
                                    <x-filament::icon :icon="$change['icon']" class="size-4" />
                                    {{ $change['text'] }}
                                </span>
                                <p class="text-gray-500 dark:text-gray-400">antes {{ $n($total['previous']) }}</p>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            @if ($analysis['cohort_open'])
                <div class="flex gap-3 rounded-xl px-3 py-2.5 text-xs ring-1 ring-amber-500/30" style="background: color-mix(in srgb, var(--ops-warning) 10%, transparent)">
                    <x-filament::icon icon="heroicon-m-clock" class="mt-0.5 size-4 shrink-0" style="color: var(--ops-warning)" />
                    <p class="text-gray-700 dark:text-gray-200">
                        <span class="font-semibold">Período todavía en maduración.</span>
                        Las cotizaciones de los últimos 15 días aún pueden emitirse o anularse: la tasa de pérdida subirá cuando venzan. Comparada con un período ya cerrado, la pérdida parecerá menor de lo que será.
                    </p>
                </div>
            @endif

            {{-- Lectura del período --}}
            @if ($analysis['insights'] !== [])
                <div>
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Lectura del período</p>
                    <ul class="grid gap-2 lg:grid-cols-2">
                        @foreach ($analysis['insights'] as $index => $insight)
                            @php
                                $tone = $tones[$insight['tone']] ?? $tones['neutral'];
                            @endphp
                            <li wire:key="pqa-insight-{{ $index }}-{{ md5($insight['area'].$insight['title']) }}" class="flex gap-3 rounded-xl p-2.5 ring-1 ring-gray-950/5 dark:ring-white/10">
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
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Un bloque por plan --}}
            <div class="grid gap-4 xl:grid-cols-3">
                @foreach ($analysis['plans'] as $plan)
                    @php
                        $planChange = $compare ? $delta(\App\Support\Metrics\Corporate\CorporateMetrics::change((float) $plan['total_created'], (float) $plan['total_previous'])) : null;
                    @endphp
                    <article wire:key="pqa-plan-{{ $plan['id'] }}" class="flex flex-col gap-4 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <header class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-base font-semibold text-gray-950 dark:text-white">{{ $plan['label'] }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $n($plan['total_created']) }} cotizaciones en el período (individuales + corporativas)</p>
                            </div>
                            @if ($planChange)
                                <span class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold" style="color: {{ $planChange['color'] }}" x-tooltip="{ content: @js('Período anterior: '.$n($plan['total_previous'])), theme: $store.theme }">
                                    <x-filament::icon :icon="$planChange['icon']" class="size-4" />
                                    {{ $planChange['text'] }}
                                </span>
                            @endif
                        </header>

                        @foreach ($typeLabels as $type => $typeLabel)
                            @php
                                $block = $plan['types'][$type];
                                $c = $block['current'];
                                $p = $block['previous'];
                                $createdChange = $compare ? $delta($block['created_change']) : null;
                                $issuedDelta = $compare ? $delta($block['issued_rate_delta'], true, 'pp') : null;
                                $voidedDelta = $compare ? $delta($block['voided_rate_delta'], false, 'pp') : null;
                            @endphp
                            <section wire:key="pqa-{{ $plan['id'] }}-{{ $type }}" class="rounded-xl bg-gray-50/80 p-3 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                                <div class="flex items-baseline justify-between gap-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $typeLabel }}</p>
                                    @if ($block['share'] !== null)
                                        <p class="text-[11px] text-gray-500 dark:text-gray-400">{{ $pct($block['share']) }} de las {{ mb_strtolower($typeLabel) }}</p>
                                    @endif
                                </div>

                                @if ($c['created'] === 0)
                                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                        Sin cotizaciones {{ mb_strtolower($typeLabel) }} de este plan en el período{{ $compare ? ' (antes: '.$n($p['created']).')' : '' }}.
                                    </p>
                                @else
                                    <div class="mt-1 flex items-end justify-between gap-3">
                                        <p class="text-2xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ $n($c['created']) }} <span class="text-sm font-normal text-gray-500 dark:text-gray-400">creadas</span></p>
                                        @if ($createdChange)
                                            <span class="inline-flex items-center gap-1 text-xs font-semibold" style="color: {{ $createdChange['color'] }}" x-tooltip="{ content: @js('Período anterior: '.$n($p['created']).' creadas'), theme: $store.theme }">
                                                <x-filament::icon :icon="$createdChange['icon']" class="size-4" />
                                                {{ $createdChange['text'] }}
                                            </span>
                                        @endif
                                    </div>

                                    {{-- Partición: suma el total de creadas --}}
                                    <div class="mt-2 flex h-2.5 overflow-hidden rounded-full" style="background: var(--ops-track)" role="img" aria-label="Distribución de las cotizaciones creadas">
                                        @foreach ($block['segments'] as $segment)
                                            @if ($segment['value'] > 0)
                                                <div class="h-full" style="width: {{ $segment['percent'] }}%; background: {{ $segment['color'] }}" x-tooltip="{ content: @js($segment['label'].': '.$n($segment['value']).' ('.$pct($segment['percent']).')'), theme: $store.theme }"></div>
                                            @endif
                                        @endforeach
                                    </div>
                                    <dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
                                        @foreach ($block['segments'] as $segment)
                                            @continue($segment['key'] === 'anulated' && $type === 'corporate')
                                            <div class="flex items-center justify-between gap-2">
                                                <dt class="flex items-center gap-1.5 text-gray-600 dark:text-gray-300">
                                                    <span class="size-2 rounded-full" style="background: {{ $segment['color'] }}"></span>
                                                    {{ $segment['label'] }}
                                                </dt>
                                                <dd class="tabular-nums text-gray-950 dark:text-white"><span class="font-semibold">{{ $n($segment['value']) }}</span> <span class="text-gray-400">{{ $pct($segment['percent']) }}</span></dd>
                                            </div>
                                        @endforeach
                                    </dl>

                                    <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                                        <div class="rounded-lg bg-white px-2.5 py-2 ring-1 ring-gray-950/5 dark:bg-gray-900/60 dark:ring-white/10">
                                            <p class="text-gray-500 dark:text-gray-400">Tasa de emisión @if ($analysis['cohort_open'])<span class="text-[10px]" style="color: var(--ops-warning)">· en maduración</span>@endif</p>
                                            <p class="text-sm font-semibold tabular-nums text-gray-950 dark:text-white">{{ $pct($block['issued_rate']) }}</p>
                                            @if ($issuedDelta)
                                                <p class="inline-flex items-center gap-1 font-medium" style="color: {{ $issuedDelta['color'] }}">
                                                    <x-filament::icon :icon="$issuedDelta['icon']" class="size-3.5" />{{ $issuedDelta['text'] }}
                                                </p>
                                            @endif
                                        </div>
                                        <div class="rounded-lg bg-white px-2.5 py-2 ring-1 ring-gray-950/5 dark:bg-gray-900/60 dark:ring-white/10">
                                            <p class="text-gray-500 dark:text-gray-400">{{ $type === 'corporate' ? 'Tasa de eliminación' : 'Tasa de pérdida' }} @if ($analysis['cohort_open'] && $type === 'individual')<span class="text-[10px]" style="color: var(--ops-warning)">· en maduración</span>@endif</p>
                                            <p class="text-sm font-semibold tabular-nums text-gray-950 dark:text-white">{{ $pct($block['voided_rate']) }}</p>
                                            @if ($voidedDelta)
                                                <p class="inline-flex items-center gap-1 font-medium" style="color: {{ $voidedDelta['color'] }}">
                                                    <x-filament::icon :icon="$voidedDelta['icon']" class="size-3.5" />{{ $voidedDelta['text'] }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>

                                    @if ($c['open'] > 0)
                                        <p class="mt-2 text-xs text-gray-600 dark:text-gray-300">
                                            <span class="font-medium">En curso:</span>
                                            {{ $n($c['approved']) }} {{ $c['approved'] === 1 ? 'aprobada' : 'aprobadas' }} sin afiliación
                                            · {{ $n($c['pre_approved']) }} pre-{{ $c['pre_approved'] === 1 ? 'aprobada' : 'aprobadas' }}
                                            @if ($c['stale'] > 0)
                                                <span style="color: var(--ops-bad-ink)">({{ $n($c['stale']) }} con más de 15 días)</span>
                                            @endif
                                            @if ($c['other_open'] > 0)
                                                · {{ $n($c['other_open']) }} en otro estado
                                            @endif
                                        </p>
                                    @endif
                                @endif
                            </section>
                        @endforeach
                    </article>
                @endforeach
            </div>

            {{-- Cómo se calcula --}}
            <details class="rounded-xl bg-gray-50 px-4 py-3 text-xs text-gray-600 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
                <summary class="cursor-pointer font-medium text-gray-700 dark:text-gray-200">Cómo se calcula</summary>
                <ul class="mt-2 list-disc space-y-1 ps-5">
                    <li><strong>Creadas</strong>: cotizaciones creadas en el período que incluyen el plan. Una cotización múltiple cuenta en cada plan que cotiza; por eso los planes suman más que el total.</li>
                    <li>Cada creada cae en una sola parte de la barra: <strong>emitida</strong> (tiene una afiliación vigente de ese plan), <strong>anulada</strong>, <strong>eliminada</strong> o <strong>en curso</strong>.</li>
                    <li><strong>Tasa de emisión</strong> = emitidas ÷ creadas. <strong>Tasa de pérdida</strong> = (anuladas + eliminadas) ÷ creadas. La variación se mide en puntos porcentuales (pp).</li>
                    <li>Las corporativas no se anulan: su pérdida solo son las eliminadas. Las eliminaciones se registran desde el 09/11/2026; las anteriores no dejaron rastro.</li>
                    @if (($analysis['unattributed']['individual'] ?? 0) + ($analysis['unattributed']['corporate'] ?? 0) > 0)
                        <li>No entran en ningún plan: {{ $n($analysis['unattributed']['individual']) }} individuales y {{ $n($analysis['unattributed']['corporate']) }} corporativas del período (Dress Tylor, planes cotizables o múltiples sin plan en su detalle).</li>
                    @endif
                </ul>
            </details>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
