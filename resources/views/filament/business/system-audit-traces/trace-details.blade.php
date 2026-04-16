<div class="space-y-4">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-slate-200/80 bg-white p-3 sm:col-span-2 dark:border-white/10 dark:bg-slate-900/70">
            <p class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Acción</p>
            <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">
                {{ \Illuminate\Support\Str::of($record->action)->replace('_', ' ')->lower()->title() }}
            </p>
            <p class="mt-1 break-all font-mono text-[11px] text-slate-500 dark:text-slate-400">{{ $record->action }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-3 dark:border-white/10 dark:bg-slate-900/70">
            <p class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Trace ID</p>
            <p class="mt-1 break-all font-mono text-xs text-slate-800 dark:text-slate-200">{{ $payload['trace_id'] ?? 'N/A' }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-3 dark:border-white/10 dark:bg-slate-900/70">
            <p class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Usuario</p>
            <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $record->user?->name ?? 'Sistema' }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white p-3 dark:border-white/10 dark:bg-slate-900/70">
            <p class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Fecha</p>
            <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $record->created_at?->format('d/m/Y H:i:s') }}</p>
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/70 p-3 dark:border-white/10 dark:bg-slate-800/40">
            <p class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Ruta</p>
            <p class="mt-1 break-all text-xs text-slate-800 dark:text-slate-200">{{ $record->route }}</p>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/70 p-3 dark:border-white/10 dark:bg-slate-800/40">
            <p class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400">IP y Método</p>
            <p class="mt-1 text-xs text-slate-800 dark:text-slate-200">{{ $record->ip }} · {{ $record->method }}</p>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200/80 bg-white p-3 dark:border-white/10 dark:bg-slate-900/70">
        <p class="text-[11px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Detalle de auditoría (JSON)</p>
        <pre class="mt-2 max-h-[22rem] overflow-auto rounded-xl bg-slate-900 p-3 text-[11px] leading-relaxed text-slate-100">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
    </div>
</div>

