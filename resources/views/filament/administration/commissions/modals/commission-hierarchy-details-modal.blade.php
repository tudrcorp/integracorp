@php
    $sale = $commission->sale;
@endphp

<div class="space-y-4 px-1 py-1">
    <section class="rounded-3xl border border-slate-200/80 bg-white/90 p-5 shadow-sm backdrop-blur dark:border-white/10 dark:bg-slate-900/70">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1">
                <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Venta y afiliación</p>
                <h3 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-slate-100">
                    Venta #{{ $commission->code ?: 'N/A' }} · Afiliación {{ $commission->affiliation_code ?: 'N/A' }}
                </h3>
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Afiliado: <span class="font-medium text-slate-900 dark:text-slate-100">{{ $commission->affiliate_full_name ?: 'N/A' }}</span>
                </p>
            </div>
            <span class="rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700 ring-1 ring-sky-100 dark:border-sky-700/40 dark:bg-sky-900/20 dark:text-sky-300 dark:ring-sky-800/30">
                {{ $commission->payment_frequency ?: 'SIN FRECUENCIA' }}
            </span>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200/80 bg-white/90 p-4 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
        <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Contexto de la venta</p>
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div><dt class="text-slate-500 dark:text-slate-400">Plan</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ data_get($sale, 'plan.description', data_get($commission, 'plan.description', 'N/A')) }}</dd></div>
            <div><dt class="text-slate-500 dark:text-slate-400">Cobertura</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ data_get($sale, 'coverage.price', data_get($commission, 'coverage.price', 'N/A')) }}</dd></div>
            <div><dt class="text-slate-500 dark:text-slate-400">Método de pago</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ $commission->payment_method ?: 'N/A' }}</dd></div>
            <div><dt class="text-slate-500 dark:text-slate-400">Importe base</dt><dd class="font-medium text-slate-900 dark:text-slate-100">US$ {{ number_format((float) ($commission->amount ?? 0), 2, ',', '.') }}</dd></div>
            <div><dt class="text-slate-500 dark:text-slate-400">Pago registrado US$</dt><dd class="font-medium text-slate-900 dark:text-slate-100">US$ {{ number_format((float) ($commission->pay_amount_usd ?? 0), 2, ',', '.') }}</dd></div>
            <div><dt class="text-slate-500 dark:text-slate-400">Pago registrado VES</dt><dd class="font-medium text-slate-900 dark:text-slate-100">Bs. {{ number_format((float) ($commission->pay_amount_ves ?? 0), 2, ',', '.') }}</dd></div>
        </dl>
    </section>

    <section class="rounded-2xl border border-slate-200/80 bg-white/90 p-4 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
        <p class="mb-3 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Jerarquía de comisiones generadas</p>

        <div class="grid gap-3 lg:grid-cols-3">
            <article class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4 dark:border-white/10 dark:bg-slate-800/40">
                <div class="mb-2 flex items-center justify-between">
                    <h4 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Nivel Master</h4>
                    <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-[11px] font-semibold text-indigo-700 ring-1 ring-indigo-200 dark:bg-indigo-900/30 dark:text-indigo-300 dark:ring-indigo-700/40">
                        {{ number_format((float) ($commission->porcent_agency_master ?? 0), 2, ',', '.') }}%
                    </span>
                </div>
                <dl class="space-y-1.5 text-sm">
                    <div><dt class="text-slate-500 dark:text-slate-400">Agencia</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ $masterAgency->name_corporative ?? 'No aplica / sin agencia master' }}</dd></div>
                    <div><dt class="text-slate-500 dark:text-slate-400">Pago USD</dt><dd class="font-semibold text-emerald-700 dark:text-emerald-300">US$ {{ number_format((float) ($commission->commission_agency_master_usd ?? 0), 2, ',', '.') }}</dd></div>
                    <div><dt class="text-slate-500 dark:text-slate-400">Pago VES</dt><dd class="font-semibold text-emerald-700 dark:text-emerald-300">Bs. {{ number_format((float) ($commission->commission_agency_master_ves ?? 0), 2, ',', '.') }}</dd></div>
                </dl>
            </article>

            <article class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4 dark:border-white/10 dark:bg-slate-800/40">
                <div class="mb-2 flex items-center justify-between">
                    <h4 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Nivel General</h4>
                    <span class="rounded-full bg-sky-50 px-2.5 py-1 text-[11px] font-semibold text-sky-700 ring-1 ring-sky-200 dark:bg-sky-900/30 dark:text-sky-300 dark:ring-sky-700/40">
                        {{ number_format((float) ($commission->porcent_agency_general ?? 0), 2, ',', '.') }}%
                    </span>
                </div>
                <dl class="space-y-1.5 text-sm">
                    <div><dt class="text-slate-500 dark:text-slate-400">Agencia</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ data_get($commission, 'agency.name_corporative', 'N/A') }}</dd></div>
                    <div><dt class="text-slate-500 dark:text-slate-400">Código agencia</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ $commission->code_agency ?: 'N/A' }}</dd></div>
                    <div><dt class="text-slate-500 dark:text-slate-400">Pago USD</dt><dd class="font-semibold text-emerald-700 dark:text-emerald-300">US$ {{ number_format((float) ($commission->commission_agency_general_usd ?? 0), 2, ',', '.') }}</dd></div>
                    <div><dt class="text-slate-500 dark:text-slate-400">Pago VES</dt><dd class="font-semibold text-emerald-700 dark:text-emerald-300">Bs. {{ number_format((float) ($commission->commission_agency_general_ves ?? 0), 2, ',', '.') }}</dd></div>
                </dl>
            </article>

            <article class="rounded-2xl border border-slate-200/80 bg-slate-50/80 p-4 dark:border-white/10 dark:bg-slate-800/40">
                <div class="mb-2 flex items-center justify-between">
                    <h4 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Nivel Agente</h4>
                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-700 ring-1 ring-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:ring-amber-700/40">
                        {{ number_format((float) ($commission->porcent_agente ?? 0), 2, ',', '.') }}%
                    </span>
                </div>
                <dl class="space-y-1.5 text-sm">
                    <div><dt class="text-slate-500 dark:text-slate-400">Agente</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ data_get($commission, 'agent.name', 'N/A') }}</dd></div>
                    <div><dt class="text-slate-500 dark:text-slate-400">Pago USD</dt><dd class="font-semibold text-emerald-700 dark:text-emerald-300">US$ {{ number_format((float) ($commission->commission_agent_usd ?? 0), 2, ',', '.') }}</dd></div>
                    <div><dt class="text-slate-500 dark:text-slate-400">Pago VES</dt><dd class="font-semibold text-emerald-700 dark:text-emerald-300">Bs. {{ number_format((float) ($commission->commission_agent_ves ?? 0), 2, ',', '.') }}</dd></div>
                </dl>
            </article>
        </div>
    </section>
</div>
