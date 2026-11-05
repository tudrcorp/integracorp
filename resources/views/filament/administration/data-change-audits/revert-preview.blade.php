@use('App\Support\Audit\DataChangeAuditPresenter', 'P')
@php
    /**
     * Vista previa de una reversión: qué campos vuelven a qué valor, si algo lo impide y qué
     * documentos relacionados NO se recalculan.
     *
     * @var array{revertible: bool, blocker: ?string, mode: ?string, fields: list<array{field: string, label: string, current: ?string, restore: ?string, conflict: bool, critical: bool}>, conflicts: list<string>, dependents: list<string>} $preview
     */
@endphp

<div class="flex flex-col gap-3 text-sm">
    @if (! $preview['revertible'])
        <div class="rounded-xl bg-danger-50 px-4 py-3 text-danger-800 dark:bg-danger-500/10 dark:text-danger-300">
            <p class="font-semibold">No se puede revertir</p>
            <p>{{ $preview['blocker'] }}</p>
            @foreach ($preview['conflicts'] as $conflict)
                <p class="mt-1 text-xs">• {{ $conflict }}</p>
            @endforeach
        </div>
    @elseif ($preview['mode'] === 'restore')
        <p class="rounded-xl bg-gray-50 px-4 py-3 text-gray-800 dark:bg-white/5 dark:text-gray-200">El registro eliminado se restaurará tal como estaba.</p>
    @else
        <table class="w-full table-fixed text-left">
            <thead>
                <tr class="text-xs text-gray-500 dark:text-gray-400">
                    <th class="w-1/3 py-1 pr-2 font-medium">Campo</th>
                    <th class="w-1/3 py-1 pr-2 font-medium">Valor actual</th>
                    <th class="py-1 font-medium">Volverá a</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-950/5 dark:divide-white/10">
                @foreach ($preview['fields'] as $field)
                    <tr class="align-top">
                        <td class="py-1.5 pr-2 font-medium text-gray-950 dark:text-white">{{ $field['label'] }}</td>
                        <td class="break-words py-1.5 pr-2 text-gray-600 dark:text-gray-300">{{ P::value($field['field'], $field['current']) }}</td>
                        <td class="break-words py-1.5 font-semibold text-gray-950 dark:text-white">{{ P::value($field['field'], $field['restore']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($preview['dependents'] !== [])
        <div class="rounded-xl bg-amber-50 px-4 py-3 text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
            <p class="font-semibold">La reversión no recalcula lo ya generado</p>
            @foreach ($preview['dependents'] as $line)
                <p class="mt-1 text-xs">• {{ $line }}</p>
            @endforeach
            <p class="mt-1 text-xs">Revíselos después y corríjalos desde su módulo si corresponde.</p>
        </div>
    @endif
</div>
