@php
    /** @var list<array{key: string, label: string}> $columns */
    /** @var list<array{label: string, cells: array<string, string|null>}> $rows */
    /** @var string $rowHeader */
    /** @var string $emptyMessage */
    /** @var string|null $emptyCellLabel */

    $columns = (array) ($columns ?? []);
    $rows = (array) ($rows ?? []);
    $rowHeader = (string) ($rowHeader ?? 'Concepto');
    $emptyMessage = (string) ($emptyMessage ?? 'Sin información configurada.');
    $emptyCellLabel = $emptyCellLabel ?? '—';
@endphp

{{-- La tabla desborda en su propio contenedor: con muchas coberturas la página
     nunca se desplaza horizontalmente. --}}
<div class="overflow-x-auto rounded-xl border border-slate-200/80 bg-white shadow-sm dark:border-white/10 dark:bg-slate-950/40">
    @if ($columns === [] || $rows === [])
        <p class="p-6 text-center text-sm text-slate-500 dark:text-slate-400">
            {{ $emptyMessage }}
        </p>
    @else
        <table class="min-w-full border-collapse text-xs leading-snug text-slate-800 dark:text-slate-100">
            <thead>
                <tr class="bg-[#052F60] text-white">
                    <th scope="col" class="border border-[#04244b] px-3 py-2.5 text-left font-bold uppercase tracking-wide min-w-[220px]">
                        {{ $rowHeader }}
                    </th>
                    @foreach ($columns as $column)
                        <th scope="col" class="border border-[#04244b] px-2 py-2.5 text-center font-bold uppercase min-w-[120px]">
                            {{ $column['label'] ?? '—' }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr class="{{ $loop->even ? 'bg-white dark:bg-slate-900/40' : 'bg-slate-50/80 dark:bg-white/[0.03]' }}">
                        <th scope="row" class="border border-slate-200 px-3 py-2.5 text-left align-top font-medium dark:border-white/10">
                            <span class="font-semibold text-slate-500 dark:text-slate-400">{{ $loop->iteration }}.</span>
                            <span class="ml-1">{{ $row['label'] ?? '—' }}</span>
                        </th>
                        @foreach ($columns as $column)
                            @php
                                $value = $row['cells'][$column['key']] ?? null;
                            @endphp
                            <td class="border border-slate-200 px-2 py-2.5 text-center align-middle tabular-nums dark:border-white/10">
                                @if ($value === null)
                                    <span class="text-slate-400 dark:text-slate-500" title="{{ $emptyCellLabel }}">
                                        {{ $emptyCellLabel }}
                                    </span>
                                @else
                                    <span class="font-medium">{{ $value }}</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
