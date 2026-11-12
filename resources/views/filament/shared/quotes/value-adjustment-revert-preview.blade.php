@php
    $money = fn ($cents) => number_format(((int) $cents) / 100, 2, ',', '.');
    $affiliation = $preview['effects']['affiliation'] ?? null;
@endphp
<div class="space-y-2 text-sm">
    @if ($preview['blocker'] !== null)
        <div class="rounded-lg bg-rose-50 px-3 py-2 text-rose-800 ring-1 ring-inset ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-200 dark:ring-rose-400/30">
            <p class="font-semibold">No se puede revertir:</p>
            <p>{{ $preview['blocker'] }}</p>
        </div>
    @else
        <div class="rounded-lg bg-gray-50 px-3 py-2 ring-1 ring-inset ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <p class="font-semibold text-gray-950 dark:text-white">Al revertir:</p>
            <ul class="mt-1 list-disc space-y-0.5 ps-5 text-gray-700 dark:text-gray-200">
                <li>{{ $preview['effects']['lines'] }} línea(s) de la cotización vuelven a sus valores anteriores.</li>
                @if (is_array($affiliation))
                    <li>Afiliación {{ $affiliation['code'] }}: cuota US$ {{ $affiliation['totals']['before']['total_amount'] }} → <span class="font-semibold">US$ {{ $affiliation['totals']['after']['total_amount'] }}</span>; tarifa anual US$ {{ $affiliation['totals']['before']['fee_anual'] }} → <span class="font-semibold">US$ {{ $affiliation['totals']['after']['fee_anual'] }}</span>.</li>
                    @if ($affiliation['affiliates'] > 0)
                        <li>{{ $affiliation['affiliates'] }} afiliado(s) vuelven a su tarifa anterior.</li>
                    @endif
                    @foreach ($affiliation['restored'] as $collection)
                        <li>Aviso {{ $collection['invoice'] }}: US$ {{ $money($collection['before_cents']) }} → US$ {{ $money($collection['after_cents']) }}</li>
                    @endforeach
                    @foreach ($affiliation['spread'] as $collection)
                        <li>Aviso {{ $collection['invoice'] }} (diferencia de un aviso ya pagado): US$ {{ $money($collection['before_cents']) }} → US$ {{ $money($collection['after_cents']) }}</li>
                    @endforeach
                @else
                    <li>La afiliación no cambia (el ajuste no llegó a ella).</li>
                @endif
            </ul>
        </div>
    @endif
</div>
