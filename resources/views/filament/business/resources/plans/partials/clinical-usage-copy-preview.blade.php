@php
    /** @var array{apply: list<array{label: string, from: string, to: string, was_complete: bool}>, keep: list<array{label: string, reason: string}>, extra: array<int, string>} $preview */
    $replacing = collect($preview['apply'])->where('was_complete', true)->count();
@endphp

<div class="space-y-3 text-sm">
    <div class="flex flex-wrap gap-1.5 text-xs">
        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/30">{{ count($preview['apply']) }} se copian</span>
        <span class="inline-flex items-center rounded-full bg-gray-50 px-2.5 py-1 font-medium text-gray-600 ring-1 ring-inset ring-gray-500/20 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">{{ count($preview['keep']) }} quedan igual</span>
        @if (count($preview['extra']) > 0)
            <span class="inline-flex items-center rounded-full bg-sky-50 px-2.5 py-1 font-medium text-sky-700 ring-1 ring-inset ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-400/30">{{ count($preview['extra']) }} solo en el plan de origen</span>
        @endif
    </div>

    @if (($preview['by_name'] ?? 0) > 0)
        <div class="rounded-lg bg-indigo-50 px-3 py-2 text-xs text-indigo-900 ring-1 ring-inset ring-indigo-600/20 dark:bg-indigo-500/10 dark:text-indigo-200 dark:ring-indigo-400/30">
            {{ $preview['by_name'] }} beneficio(s) se emparejaron por nombre: son la misma prestación pero con otro registro en el catálogo.
            La configuración se copia al beneficio de este plan; no se agrega ni se cambia ningún beneficio.
        </div>
    @endif

    @if ($replacing > 0)
        <div class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-400/30">
            Atención: {{ $replacing }} beneficio(s) que ya estaban listos cambiarán su configuración.
        </div>
    @endif

    @if (count($preview['apply']) > 0)
        <div class="overflow-hidden rounded-lg ring-1 ring-gray-950/10 dark:ring-white/10">
            <table class="w-full table-fixed text-left text-xs">
                <thead class="bg-gray-50 text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <tr>
                        <th class="w-1/3 px-3 py-2 font-medium">Beneficio</th>
                        <th class="w-1/3 px-3 py-2 font-medium">Hoy</th>
                        <th class="w-1/3 px-3 py-2 font-medium">Quedará</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-950/5 dark:divide-white/5">
                    @foreach ($preview['apply'] as $row)
                        <tr>
                            <td class="px-3 py-2 font-medium text-gray-900 dark:text-white">
                                {{ $row['label'] }}
                                @if ($row['by_name'] ?? false)
                                    <span class="mt-0.5 block text-[0.7rem] font-normal text-indigo-700 dark:text-indigo-300">Por nombre ↔ {{ $row['source_label'] }}</span>
                                @endif
                            </td>
                            <td @class([
                                'px-3 py-2',
                                'text-amber-700 dark:text-amber-300' => ! $row['was_complete'],
                                'text-gray-500 line-through dark:text-gray-400' => $row['was_complete'],
                            ])>{{ $row['from'] }}</td>
                            <td class="px-3 py-2 text-emerald-700 dark:text-emerald-300">{{ $row['to'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600 dark:bg-white/5 dark:text-gray-300">
            Con esta opción no hay filas que copiar. Pruebe «Reemplazar todo lo que coincida» o elija otro plan.
        </p>
    @endif

    @if (count($preview['keep']) > 0)
        <details class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600 dark:bg-white/5 dark:text-gray-300">
            <summary class="cursor-pointer font-medium text-gray-700 dark:text-gray-200">Ver los {{ count($preview['keep']) }} que quedan igual</summary>
            <ul class="mt-2 space-y-1">
                @foreach ($preview['keep'] as $row)
                    <li><span class="font-medium text-gray-900 dark:text-white">{{ $row['label'] }}</span> — {{ $row['reason'] }}</li>
                @endforeach
            </ul>
        </details>
    @endif
</div>
