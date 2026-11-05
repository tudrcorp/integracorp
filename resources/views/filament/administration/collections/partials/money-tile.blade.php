@php
    /**
     * Tarjeta de un monto del Dashboard de Cobranza: rótulo e icono, cifra con conteo animado,
     * barra repartida entre sus partes y el detalle de cada una: individuales y corporativas
     * (`split`) o tramos de antigüedad (`buckets`, en «Vencido»). Si trae
     * `url`, toda la tarjeta lleva a Cuentas por cobrar con su filtro.
     *
     * @var array{key: string, label: string, icon: string, accent: string, amount: float, detail: string, split: ?array<string, array{affiliations: int, count: int, amount: float}>, buckets?: list<array{label: string, count: int, amount: float}>, url?: ?string, title?: ?string} $tile
     * @var int $index
     * @var bool|null $compact  solo las afiliaciones en el detalle (tarjetas angostas)
     */
    $money = static fn (float $amount): string => 'US$ '.number_format($amount, 2, ',', '.');
    $plural = static fn (int $count, string $one, string $many): string => $count === 1 ? '1 '.$one : number_format($count, 0, ',', '.').' '.$many;
    $formatted = $money($tile['amount']);
    /** Las partes de la tarjeta: tramos de antigüedad (`buckets`) o individuales/corporativas (`split`). */
    $parts = [];

    if (! empty($tile['buckets'])) {
        foreach (array_values($tile['buckets']) as $position => $bucket) {
            $parts[] = [
                'label' => $bucket['label'],
                'detail' => $plural((int) $bucket['count'], 'cuota', 'cuotas'),
                'amount' => (float) $bucket['amount'],
                'shade' => ['100%', '65%', '35%'][$position] ?? '25%',
            ];
        }
    } else {
        foreach (['individual' => ['Individuales', '100%'], 'corporate' => ['Corporativas', '45%']] as $type => [$typeLabel, $shade]) {
            $part = $tile['split'][$type];
            $parts[] = [
                'label' => $typeLabel,
                'detail' => $plural($part['affiliations'], 'afiliación', 'afiliaciones').(($compact ?? false) ? '' : ' · '.$plural($part['count'], 'cuota', 'cuotas')),
                'amount' => (float) $part['amount'],
                'shade' => $shade,
            ];
        }
    }

    $sum = array_sum(array_column($parts, 'amount'));
    $tag = filled($tile['url'] ?? null) ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($tag === 'a') href="{{ $tile['url'] }}" wire:navigate @endif
    @if (filled($tile['title'] ?? null)) title="{{ $tile['title'] }}" @endif
    wire:key="cxc-kpi-{{ $tile['key'] }}-{{ $tile['amount'] }}"
    x-data="{
        shown: false,
        display: @js($formatted),
        target: @js($tile['amount']),
        run() {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || this.target === 0) { return; }
            const formatter = new Intl.NumberFormat('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const started = performance.now();
            const step = (now) => {
                const progress = Math.min(1, (now - started) / 1100);
                this.display = 'US$ ' + formatter.format(this.target * (1 - Math.pow(1 - progress, 4)));
                if (progress < 1) { requestAnimationFrame(step); }
            };
            requestAnimationFrame(step);
        },
    }"
    x-intersect.once="shown = true; run()"
    x-bind:class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-2'"
    style="transition: opacity .45s ease, transform .45s ease, box-shadow .15s; transition-delay: {{ $index * 60 }}ms; border-top: 3px solid {{ $tile['accent'] }};"
    class="relative block overflow-hidden rounded-2xl bg-white px-4 py-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10 {{ $tag === 'a' ? 'hover:shadow-md' : '' }}"
>
    <div class="flex items-center justify-between gap-2">
        <span class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $tile['label'] }}</span>
        <span
            class="inline-flex size-7 shrink-0 items-center justify-center rounded-lg"
            style="color: {{ $tile['accent'] }}; background: color-mix(in srgb, {{ $tile['accent'] }} 14%, transparent);"
        >
            <x-filament::icon :icon="$tile['icon']" class="size-4" />
        </span>
    </div>

    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5">
        <span class="text-2xl font-semibold tracking-tight text-gray-950 dark:text-white" style="font-variant-numeric: tabular-nums;" x-text="display">{{ $formatted }}</span>
        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $tile['detail'] }}</span>
    </div>

    <div class="mt-2 flex h-1 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10" aria-hidden="true">
        @if ($sum > 0)
            @foreach ($parts as $part)
                <div class="h-full" x-bind:style="'width: ' + (shown ? {{ round($part['amount'] * 100 / $sum, 2) }} : 0) + '%; background: color-mix(in srgb, {{ $tile['accent'] }} {{ $part['shade'] }}, transparent); transition: width .9s ease;'"></div>
            @endforeach
        @endif
    </div>

    <div class="mt-2 space-y-1.5">
        @foreach ($parts as $part)
            <div class="flex items-center justify-between gap-3 text-xs leading-4">
                <span class="flex min-w-0 items-center gap-1.5">
                    <span class="inline-block size-2 shrink-0 rounded-full" style="background: color-mix(in srgb, {{ $tile['accent'] }} {{ $part['shade'] }}, transparent);"></span>
                    <span class="min-w-0">
                        <span class="block font-medium text-gray-600 dark:text-gray-300">{{ $part['label'] }}</span>
                        <span class="block text-[11px] text-gray-400 dark:text-gray-500">{{ $part['detail'] }}</span>
                    </span>
                </span>
                <span class="shrink-0 font-semibold text-gray-950 dark:text-white" style="font-variant-numeric: tabular-nums; white-space: nowrap;">{{ $money($part['amount']) }}</span>
            </div>
        @endforeach
    </div>
</{{ $tag }}>
