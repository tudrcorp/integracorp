@php
    $actionLabel = \Illuminate\Support\Str::of((string) $record->action)->replace('_', ' ')->lower()->title();
    $isFailed = str_contains((string) $record->action, 'FAILED');
    $method = strtoupper((string) $record->method);
@endphp

<div class="space-y-5">
    <div class="rounded-3xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1.5">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Acción</p>
                <h3 class="text-lg font-semibold leading-tight text-slate-900 dark:text-slate-100">{{ $actionLabel }}</h3>
                <p class="break-all font-mono text-xs text-slate-500 dark:text-slate-400">{{ $record->action }}</p>
            </div>

            <span @class([
                'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold',
                'bg-rose-50 text-rose-700 ring-1 ring-rose-200 dark:bg-rose-900/30 dark:text-rose-300 dark:ring-rose-700/40' => $isFailed,
                'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:ring-emerald-700/40' => ! $isFailed,
            ])>
                {{ $isFailed ? 'Evento Fallido' : 'Evento Exitoso' }}
            </span>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 dark:border-white/10 dark:bg-slate-900/70">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Trace ID</p>
            <p class="mt-2 break-all font-mono text-sm leading-relaxed text-slate-800 dark:text-slate-100">{{ $payload['trace_id'] ?? 'N/A' }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 dark:border-white/10 dark:bg-slate-900/70">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Usuario</p>
            <p class="mt-2 text-sm font-medium text-slate-900 dark:text-slate-100">{{ $record->user?->name ?? 'Sistema' }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 dark:border-white/10 dark:bg-slate-900/70">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Fecha</p>
            <p class="mt-2 text-sm font-medium text-slate-900 dark:text-slate-100">{{ $record->created_at?->format('d/m/Y H:i:s') }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-4 dark:border-white/10 dark:bg-slate-900/70">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Método e IP</p>
            <p class="mt-2 text-sm font-medium text-slate-900 dark:text-slate-100">{{ $method }} · {{ $record->ip }}</p>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200/80 bg-slate-50/70 p-4 dark:border-white/10 dark:bg-slate-800/40">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Ruta</p>
        <p class="mt-2 break-all font-mono text-sm leading-relaxed text-slate-800 dark:text-slate-100">{{ $record->route }}</p>
    </div>

    <div class="rounded-2xl border border-slate-200/80 bg-white p-4 dark:border-white/10 dark:bg-slate-900/70">
        <div class="flex items-center justify-between gap-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Detalle de auditoría (JSON)</p>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">Scroll para ver contenido completo</p>
        </div>
        <pre class="mt-3 max-h-[26rem] overflow-auto rounded-xl bg-slate-950 p-4 text-xs leading-6 text-slate-100 ring-1 ring-slate-800">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
    </div>
</div>

