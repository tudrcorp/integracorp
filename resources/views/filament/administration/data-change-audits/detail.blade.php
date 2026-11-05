@use('App\Models\DataChangeAudit')
@use('App\Support\Audit\DataChangeAuditPresenter', 'P')
@use('App\Support\Audit\DataChangeAuditRegistry', 'R')
@php
    /**
     * Detalle de un movimiento de la auditoría de cambios: qué cambió (antes → después), desde
     * dónde, qué más cambió en la misma operación, qué hacía el usuario en ese momento y la
     * historia del registro.
     *
     * @var \App\Models\DataChangeAudit $audit
     */

    $changes = (array) $audit->changes;
    $isSnapshot = in_array($audit->event, [DataChangeAudit::EVENT_CREATED, DataChangeAudit::EVENT_DELETED], true);
    $siblings = DataChangeAudit::query()
        ->where('batch_id', $audit->batch_id)
        ->whereKeyNot($audit->getKey())
        ->orderBy('id')
        ->limit(30)
        ->get(['id', 'entity', 'entity_id', 'entity_label', 'event', 'fields', 'changes']);
    $siblingsTotal = DataChangeAudit::query()->where('batch_id', $audit->batch_id)->count() - 1;
    $history = DataChangeAudit::query()
        ->where('entity', $audit->entity)
        ->where('entity_id', $audit->entity_id)
        ->orderByDesc('id')
        ->limit(15)
        ->get(['id', 'event', 'changes', 'user_name', 'created_at', 'reverted_by_audit_id']);
    $activity = P::nearbyActivity($audit);
    $historyUrl = \App\Filament\Administration\Resources\DataChangeAudits\DataChangeAuditResource::getUrl('index', ['filters' => ['record' => ['entity' => $audit->entity, 'id' => $audit->entity_id]]]);
    $batchUrl = \App\Filament\Administration\Resources\DataChangeAudits\DataChangeAuditResource::getUrl('index', ['filters' => ['batch' => ['id' => $audit->batch_id]]]);
    $box = 'rounded-xl bg-white p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10';
    $heading = 'mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
@endphp

<div class="flex flex-col gap-4 text-sm">
    {{-- Quién, cuándo, desde dónde. --}}
    <div class="{{ $box }}">
        <dl class="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2">
            <div><dt class="text-xs text-gray-500 dark:text-gray-400">Usuario</dt><dd class="font-medium text-gray-950 dark:text-white">{{ $audit->user_name ?: 'Sistema (sin usuario)' }}</dd></div>
            <div><dt class="text-xs text-gray-500 dark:text-gray-400">Fecha y hora</dt><dd class="font-medium text-gray-950 dark:text-white">{{ $audit->created_at?->format('d/m/Y h:i:s A') }}</dd></div>
            <div><dt class="text-xs text-gray-500 dark:text-gray-400">Origen</dt><dd class="text-gray-950 dark:text-white">{{ P::origin($audit) }}</dd></div>
            <div><dt class="text-xs text-gray-500 dark:text-gray-400">Registro</dt><dd class="text-gray-950 dark:text-white">{{ $audit->entityLabel() }} · {{ $audit->entity_label ?: '#'.$audit->entity_id }}@if ($audit->parent_label) <span class="text-gray-500 dark:text-gray-400">({{ $audit->parent_label }})</span>@endif <span class="text-gray-400">#{{ $audit->entity_id }}</span></dd></div>
            @if ($audit->ip)
                <div><dt class="text-xs text-gray-500 dark:text-gray-400">IP</dt><dd class="text-gray-950 dark:text-white">{{ $audit->ip }}</dd></div>
            @endif
            @if ($audit->user_agent)
                <div><dt class="text-xs text-gray-500 dark:text-gray-400">Navegador</dt><dd class="truncate text-gray-600 dark:text-gray-300" title="{{ $audit->user_agent }}">{{ $audit->user_agent }}</dd></div>
            @endif
        </dl>

        @if ($audit->reverts_audit_id)
            <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">Revierte el movimiento #{{ $audit->reverts_audit_id }}. Razón: «{{ $audit->revert_reason }}»</p>
        @endif

        @if ($audit->reverted_by_audit_id)
            <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">Este cambio fue revertido después por el movimiento #{{ $audit->reverted_by_audit_id }}.</p>
        @endif
    </div>

    {{-- Qué cambió. --}}
    <div class="{{ $box }}">
        <p class="{{ $heading }}">{{ $isSnapshot ? ($audit->event === DataChangeAudit::EVENT_CREATED ? 'Datos con los que se creó' : 'Datos que tenía al eliminarse') : 'Qué cambió' }} ({{ count($changes) }})</p>
        <table class="w-full table-fixed text-left">
            <thead>
                <tr class="text-xs text-gray-500 dark:text-gray-400">
                    <th class="w-1/4 py-1 pr-2 font-medium">Campo</th>
                    @unless ($isSnapshot)<th class="w-1/3 py-1 pr-2 font-medium">Antes</th>@endunless
                    <th class="py-1 font-medium">{{ $isSnapshot ? 'Valor' : 'Después' }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-950/5 dark:divide-white/10">
                @foreach ($changes as $field => $change)
                    @php
                        $critical = R::isCritical((string) $audit->entity, (string) $field);
                    @endphp
                    <tr class="align-top">
                        <td class="py-1.5 pr-2">
                            <span class="font-medium text-gray-950 dark:text-white">{{ R::fieldLabel((string) $field) }}</span>
                            @if ($critical)<span class="ml-1 rounded bg-amber-100 px-1 text-[10px] font-semibold uppercase text-amber-800 dark:bg-amber-500/20 dark:text-amber-300" title="Este campo interviene en cálculos de Administración">cálculo</span>@endif
                            <span class="block text-[11px] text-gray-400">{{ $field }}</span>
                        </td>
                        @unless ($isSnapshot)
                            <td class="break-words py-1.5 pr-2 text-gray-600 line-through decoration-gray-300 dark:text-gray-300">{{ P::value((string) $field, $change['old'] ?? null) }}</td>
                        @endunless
                        <td class="break-words py-1.5 font-medium text-gray-950 dark:text-white">{{ P::value((string) $field, $isSnapshot && $audit->event === DataChangeAudit::EVENT_DELETED ? ($change['old'] ?? $change['new'] ?? null) : ($change['new'] ?? null)) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Qué hacía el usuario en ese momento. --}}
    @if ($activity !== [])
        <div class="{{ $box }}">
            <p class="{{ $heading }}">Qué hacía el usuario en ese momento</p>
            <ol class="space-y-1">
                @foreach ($activity as $event)
                    <li class="flex gap-3"><span class="w-24 shrink-0 text-xs text-gray-500 dark:text-gray-400" style="font-variant-numeric: tabular-nums;">{{ $event['at'] }}</span><span class="text-gray-950 dark:text-white">{{ $event['label'] }}</span></li>
                @endforeach
            </ol>
        </div>
    @endif

    {{-- Lo demás que cambió en el mismo clic. --}}
    @if ($siblingsTotal > 0)
        <div class="{{ $box }}">
            <div class="mb-2 flex items-center justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">En la misma operación ({{ $siblingsTotal }} {{ $siblingsTotal === 1 ? 'cambio más' : 'cambios más' }})</p>
                <a href="{{ $batchUrl }}" class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400">Ver todos</a>
            </div>
            <ul class="space-y-1">
                @foreach ($siblings as $sibling)
                    <li class="text-gray-950 dark:text-white">{{ $sibling->eventLabel() }} · {{ $sibling->entityLabel() }} {{ $sibling->entity_label ?: '#'.$sibling->entity_id }} <span class="text-gray-500 dark:text-gray-400">— {{ P::fieldsSummary((array) $sibling->changes) }}</span></li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Historia del registro. --}}
    <div class="{{ $box }}">
        <div class="mb-2 flex items-center justify-between gap-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Historia de este registro</p>
            <a href="{{ $historyUrl }}" class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400">Ver historia completa</a>
        </div>
        <ol class="space-y-1.5">
            @foreach ($history as $item)
                <li class="flex gap-3 {{ $item->getKey() === $audit->getKey() ? 'font-semibold' : '' }}">
                    <span class="w-36 shrink-0 text-xs text-gray-500 dark:text-gray-400" style="font-variant-numeric: tabular-nums;">{{ $item->created_at?->format('d/m/Y h:i A') }}</span>
                    <span class="text-gray-950 dark:text-white">{{ $item->eventLabel() }} por {{ $item->user_name ?: 'Sistema' }} <span class="font-normal text-gray-500 dark:text-gray-400">— {{ P::fieldsSummary((array) $item->changes) }}{{ $item->reverted_by_audit_id ? ' · revertido' : '' }}</span></span>
                </li>
            @endforeach
        </ol>
    </div>
</div>
