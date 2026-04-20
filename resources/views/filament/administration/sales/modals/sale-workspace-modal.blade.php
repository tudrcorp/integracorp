<div class="space-y-4 px-1 py-1">
    <section class="rounded-3xl border border-slate-200/80 bg-white/90 p-5 shadow-sm backdrop-blur dark:border-white/10 dark:bg-slate-900/70">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1">
                <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Resumen de venta</p>
                <h3 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-slate-100">
                    Recibo #{{ $sale->invoice_number ?: 'N/A' }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ $sale->created_at?->format('d/m/Y H:i') ?: 'Sin fecha' }}
                </p>
            </div>
            <span @class([
                'rounded-full px-3 py-1 text-xs font-semibold ring-1',
                'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-900/30 dark:text-sky-300 dark:ring-sky-700/50' => ($sale->type ?? null) === 'AFILIACION INDIVIDUAL',
                'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:ring-emerald-700/50' => ($sale->type ?? null) === 'AFILIACION CORPORATIVA',
                'bg-slate-100 text-slate-700 ring-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700' => ! in_array(($sale->type ?? ''), ['AFILIACION INDIVIDUAL', 'AFILIACION CORPORATIVA'], true),
            ])>
                {{ $sale->type ?: 'SIN TIPO' }}
            </span>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200/80 bg-white/90 p-4 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
        <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Información principal</p>
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div><dt class="text-slate-500 dark:text-slate-400">Afiliación</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ $sale->affiliation_code ?: 'N/A' }}</dd></div>
            <div><dt class="text-slate-500 dark:text-slate-400">Afiliado</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ $sale->affiliate_full_name ?: 'N/A' }}</dd></div>
            <div><dt class="text-slate-500 dark:text-slate-400">CI/RIF</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ $sale->affiliate_ci_rif ?: 'N/A' }}</dd></div>
            <div><dt class="text-slate-500 dark:text-slate-400">Plan</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ data_get($sale, 'plan.description', 'N/A') }}</dd></div>
            <div><dt class="text-slate-500 dark:text-slate-400">Forma de pago</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ $sale->payment_method ?: 'N/A' }}</dd></div>
            <div><dt class="text-slate-500 dark:text-slate-400">Referencia</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ $sale->reference_payment ?: 'N/A' }}</dd></div>
            <div><dt class="text-slate-500 dark:text-slate-400">Monto total</dt><dd class="font-medium text-slate-900 dark:text-slate-100">US$ {{ number_format((float) ($sale->total_amount ?? 0), 2, ',', '.') }}</dd></div>
            <div><dt class="text-slate-500 dark:text-slate-400">Factura generada</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ $sale->invoice_generated ? '#'.$sale->invoice_generated : 'No generada' }}</dd></div>
        </dl>
    </section>

    <section class="rounded-2xl border border-slate-200/80 bg-white/90 p-4 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
        <p class="mb-1 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Acciones principales</p>
        <p class="text-sm text-slate-600 dark:text-slate-300">
            Usa los botones inferiores para gestionar esta venta: descargar recibo, regenerar PDF y generar factura.
        </p>
    </section>
</div>
