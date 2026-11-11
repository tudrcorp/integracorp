@php
    use App\Support\AffiliationCorporates\Population\PopulationFieldComparison as Field;

    $preview ??= false;
    $caption ??= null;
    $error ??= null;
    $warnings ??= [];
    $leftLabel ??= 'Archivo';
    $rightLabel ??= $preview ? 'Quedará en la ficha' : 'Base';

    $statusView = static function (array $row): array {
        if ($row['accepted'] ?? false) {
            return ['Aceptada', 'bg-gray-100 text-gray-700 ring-gray-500/20 dark:bg-white/10 dark:text-gray-300 dark:ring-white/10', '≈'];
        }

        return match (true) {
            in_array($row['status'], [Field::SAME, Field::FORMAT], true) => ['Coincide', 'bg-emerald-50 text-emerald-800 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-400/30', '✓'],
            $row['counted'] => ['No coincide', 'bg-rose-50 text-rose-800 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-200 dark:ring-rose-400/30', '✗'],
            $row['status'] === Field::MISSING_BASE => ['Se puede completar', 'bg-sky-50 text-sky-800 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-200 dark:ring-sky-400/30', '+'],
            default => ['Sin dato', 'bg-gray-50 text-gray-600 ring-gray-500/20 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10', '·'],
        };
    };

    $pending = collect($fields)->filter(fn (array $row): bool => $row['counted'] && ! ($row['accepted'] ?? false))->pluck('label')->all();
@endphp

<div class="space-y-2">
    @if ($caption)
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $caption }}</p>
    @endif

    <div class="overflow-x-auto rounded-lg ring-1 ring-gray-950/5 dark:ring-white/10">
        <table class="w-full table-fixed text-sm">
            <thead class="bg-gray-50 text-left text-xs text-gray-500 dark:bg-white/5 dark:text-gray-400">
                <tr>
                    <th class="w-32 px-3 py-2 font-medium">Dato</th>
                    <th class="px-3 py-2 font-medium">{{ $leftLabel }}</th>
                    <th class="px-3 py-2 font-medium">{{ $rightLabel }}</th>
                    <th class="w-64 px-3 py-2 font-medium">Resultado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-950/5 dark:divide-white/10">
                @foreach ($fields as $row)
                    @php([$label, $tone, $mark] = $statusView($row))
                    <tr wire:key="field-{{ $row['field'] }}" class="align-top">
                        <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-200">{{ $row['label'] }}</td>
                        <td class="break-words px-3 py-2 text-gray-950 dark:text-white">{{ Field::display($row['field'], $row['file']) }}</td>
                        <td class="break-words px-3 py-2 text-gray-950 dark:text-white">{{ Field::display($row['field'], $row['base']) }}</td>
                        <td class="px-3 py-2">
                            <span class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $tone }}">{{ $mark }} {{ $label }}</span>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $row['note'] }}</p>
                            @if (filled($row['accepted_note'] ?? null))
                                <p class="mt-1 text-xs italic text-gray-500 dark:text-gray-400">Nota: {{ $row['accepted_note'] }}</p>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($preview)
        @if ($error)
            <div class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-800 ring-1 ring-inset ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-200 dark:ring-rose-400/30">
                <strong>No se podrá guardar así:</strong> {{ $error }}
            </div>
        @elseif ($pending === [])
            <div class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-400/30">
                Con estos valores la ficha cuadra con el archivo en todos los datos de identidad.
            </div>
        @else
            <div class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-400/30">
                Seguirán distintos al archivo: <strong>{{ mb_strtolower(implode(', ', $pending)) }}</strong>. Si es a propósito (el archivo está mal), quedarán como diferencias aceptadas con su nota.
            </div>
        @endif

        @foreach ($warnings as $warning)
            <div class="rounded-lg bg-sky-50 px-3 py-2 text-sm text-sky-800 ring-1 ring-inset ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-200 dark:ring-sky-400/30">{{ $warning }}</div>
        @endforeach
    @endif
</div>
