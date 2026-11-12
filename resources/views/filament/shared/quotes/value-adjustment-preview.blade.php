@php
    $money = fn ($cents) => number_format(((int) $cents) / 100, 2, ',', '.');
    $affiliation = $preview['affiliation'] ?? null;
@endphp
<div class="space-y-3 text-sm">
    @if ($preview['blockers'] !== [])
        <div class="rounded-lg bg-rose-50 px-3 py-2 text-rose-800 ring-1 ring-inset ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-200 dark:ring-rose-400/30">
            <p class="font-semibold">No se puede aplicar así:</p>
            <ul class="list-disc space-y-0.5 ps-5">
                @foreach ($preview['blockers'] as $blocker)
                    <li>{{ $blocker }}</li>
                @endforeach
            </ul>
        </div>
    @else
        <div class="rounded-lg bg-gray-50 px-3 py-2 ring-1 ring-inset ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <p class="font-semibold text-gray-950 dark:text-white">En la cotización</p>
            <ul class="mt-1 list-disc space-y-0.5 ps-5 text-gray-700 dark:text-gray-200">
                @foreach ($preview['lines'] as $line)
                    <li>{{ $line['label'] }}: US$ {{ $line['fee_before'] }} × {{ $line['persons_before'] }} → <span class="font-semibold">US$ {{ $line['fee_after'] }} × {{ $line['persons_after'] }}</span></li>
                @endforeach
            </ul>
        </div>

        @if (is_array($affiliation) && ($affiliation['cascade'] ?? false))
            <div class="rounded-lg bg-gray-50 px-3 py-2 ring-1 ring-inset ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                <p class="font-semibold text-gray-950 dark:text-white">En la afiliación {{ $affiliation['code'] }}</p>
                <ul class="mt-1 list-disc space-y-0.5 ps-5 text-gray-700 dark:text-gray-200">
                    @foreach ($affiliation['rows'] as $row)
                        @if (isset($row['row_id']))
                            <li>{{ $row['label'] }}: tarifa US$ {{ $row['before']['fee'] }} → <span class="font-semibold">US$ {{ $row['fee_after'] }}</span> para {{ $row['affiliates'] }} afiliado(s) activo(s)</li>
                        @else
                            <li>{{ $row['label'] }}: tarifa {{ $row['rate_delta_cents'] > 0 ? '+' : '−' }}US$ {{ $money(abs($row['rate_delta_cents'])) }} para {{ $row['affiliates'] }} afiliado(s)@if ($row['names'] !== []) ({{ implode(', ', $row['names']) }})@endif</li>
                        @endif
                    @endforeach
                    <li>
                        Población activa {{ $affiliation['totals']['before']['persons'] }} → <span class="font-semibold">{{ $affiliation['totals']['after']['persons'] }}</span>
                        · tarifa anual US$ {{ $affiliation['totals']['before']['fee_anual'] }} → <span class="font-semibold">US$ {{ $affiliation['totals']['after']['fee_anual'] }}</span>
                        · cuota ({{ $affiliation['frequency'] }}) US$ {{ $affiliation['totals']['before']['total_amount'] }} → <span class="font-semibold">US$ {{ $affiliation['totals']['after']['total_amount'] }}</span>
                    </li>
                    <li>Los totales y los avisos se calculan con la población activa real (afiliados ACTIVO y PRE-APROBADA).</li>
                </ul>
            </div>

            @if ($affiliation['collections'] !== [])
                <div class="overflow-hidden rounded-lg ring-1 ring-inset ring-gray-950/5 dark:ring-white/10">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-white/5 dark:text-gray-400">
                            <tr>
                                <th class="px-3 py-2">Aviso POR PAGAR</th>
                                <th class="px-3 py-2">Vence</th>
                                <th class="px-3 py-2 text-right">Personas</th>
                                <th class="px-3 py-2 text-right">Antes</th>
                                <th class="px-3 py-2 text-right">Después</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-950/5 dark:divide-white/10">
                            @foreach ($affiliation['collections'] as $collection)
                                <tr wire:key="qva-collection-{{ $collection['id'] }}">
                                    <td class="px-3 py-1.5 text-gray-950 dark:text-white">{{ $collection['invoice'] }}</td>
                                    <td class="px-3 py-1.5 text-gray-600 dark:text-gray-300">{{ $collection['due'] ?? '—' }}</td>
                                    <td class="px-3 py-1.5 text-right text-gray-600 dark:text-gray-300">{{ $collection['persons_before'] ?? '—' }} → {{ $collection['persons_after'] ?? '—' }}</td>
                                    <td class="px-3 py-1.5 text-right text-gray-600 dark:text-gray-300">US$ {{ $money($collection['before_cents']) }}</td>
                                    <td class="px-3 py-1.5 text-right font-semibold text-gray-950 dark:text-white">US$ {{ $money($collection['after_cents']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif

        @foreach (array_merge($preview['warnings'], (array) ($affiliation['notes'] ?? [])) as $note)
            <p class="rounded-lg bg-amber-50 px-3 py-2 text-amber-900 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-400/30">{{ $note }}</p>
        @endforeach
    @endif
</div>
