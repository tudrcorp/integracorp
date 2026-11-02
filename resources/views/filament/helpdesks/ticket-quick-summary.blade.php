@props([
    'priority',
    'status',
    'reporter',
    'createdAt',
    'createdAtRelative',
    'summary',
])

@php
    $priorityTone = match ($priority) {
        'ALTA' => 'bg-rose-500',
        'BAJA' => 'bg-emerald-500',
        default => 'bg-amber-500',
    };
    $statusTone = match ($status) {
        'EN PROCESO' => 'bg-sky-50 text-sky-700 ring-sky-600/20 dark:bg-sky-400/10 dark:text-sky-300 dark:ring-sky-400/25',
        'TERMINADO' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-300 dark:ring-emerald-400/25',
        'CANCELADO' => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-400/10 dark:text-rose-300 dark:ring-rose-400/25',
        default => 'bg-gray-50 text-gray-700 ring-gray-500/20 dark:bg-white/5 dark:text-gray-300 dark:ring-white/15',
    };
    $label = 'text-xs font-medium text-gray-500 dark:text-gray-400';
@endphp

<div class="flex min-w-0 max-w-[26rem] flex-col gap-3 text-sm leading-relaxed text-gray-700 dark:text-gray-300">
    <div class="flex flex-wrap items-center gap-2">
        <span class="inline-flex items-center gap-1.5 rounded-md bg-gray-50 px-2 py-0.5 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-500/20 dark:bg-white/5 dark:text-gray-200 dark:ring-white/15">
            <span class="size-1.5 rounded-full {{ $priorityTone }}" aria-hidden="true"></span>
            Prioridad {{ mb_strtolower($priority) }}
        </span>
        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $statusTone }}">
            {{ \App\Support\HelpdeskTicketQuickSummary::sentenceCase($status) }}
        </span>
    </div>

    <p class="text-xs text-gray-500 dark:text-gray-400">
        <span class="font-medium text-gray-700 dark:text-gray-200">{{ $reporter ?? 'Sin registrar' }}</span>
        · <span title="{{ $createdAt }}">{{ $createdAtRelative ?? $createdAt }}</span>
    </p>

    @if ($summary['kind'] === 'error_report')
        <div class="flex flex-col gap-2.5 border-t border-gray-200 pt-3 dark:border-white/10">
            <p class="text-xs font-semibold text-rose-600 dark:text-rose-400">
                Reporte automático de error{{ $summary['error_code'] ? ' '.$summary['error_code'] : '' }}
            </p>

            @if ($summary['summary'])
                <p class="text-gray-900 dark:text-gray-100">{{ $summary['summary'] }}</p>
            @endif

            <div class="flex flex-col gap-0.5">
                <span class="{{ $label }}">Comentario del usuario</span>
                <p class="{{ $summary['note'] ? '' : 'italic text-gray-500 dark:text-gray-400' }}">{{ $summary['note'] ?? 'No agregó comentarios.' }}</p>
            </div>

            @if ($summary['cause'])
                <div class="flex flex-col gap-0.5">
                    <span class="{{ $label }}">Causa técnica</span>
                    <p class="break-words rounded-md bg-gray-50 px-2 py-1.5 font-mono text-xs leading-snug text-gray-600 dark:bg-white/5 dark:text-gray-400">{{ $summary['cause'] }}</p>
                </div>
            @endif
        </div>
    @else
        <div class="flex flex-col gap-0.5 border-t border-gray-200 pt-3 dark:border-white/10">
            <span class="{{ $label }}">Descripción</span>
            <p class="whitespace-pre-line break-words {{ $summary['text'] ? 'text-gray-900 dark:text-gray-100' : 'italic text-gray-500 dark:text-gray-400' }}">{{ $summary['text'] ?? 'Sin descripción registrada.' }}</p>
        </div>
    @endif
</div>
