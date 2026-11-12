@php
    $preview = [
        'blockers' => [],
        'warnings' => (array) ($adjustment->effects['warnings'] ?? []),
        'lines' => (array) $adjustment->lines,
        'affiliation' => $adjustment->effects['affiliation'] ?? null,
    ];
@endphp
<div class="space-y-3 text-sm">
    <dl class="grid grid-cols-1 gap-2 sm:grid-cols-3">
        <div>
            <dt class="text-xs text-gray-500 dark:text-gray-400">Registrado</dt>
            <dd class="text-gray-950 dark:text-white">{{ $adjustment->created_at?->format('d/m/Y h:i A') }} · {{ $adjustment->created_by_name }}</dd>
        </div>
        <div class="sm:col-span-2">
            <dt class="text-xs text-gray-500 dark:text-gray-400">Motivo</dt>
            <dd class="text-gray-950 dark:text-white">{{ $adjustment->reason }}</dd>
        </div>
        @if ($adjustment->reverted_at !== null)
            <div class="sm:col-span-3">
                <dt class="text-xs text-gray-500 dark:text-gray-400">Revertido</dt>
                <dd class="text-gray-950 dark:text-white">{{ $adjustment->reverted_at->format('d/m/Y h:i A') }} · {{ $adjustment->reverted_by_name }} — {{ $adjustment->revert_reason }}</dd>
            </div>
        @endif
    </dl>

    @include('filament.shared.quotes.value-adjustment-preview', ['preview' => $preview])
</div>
