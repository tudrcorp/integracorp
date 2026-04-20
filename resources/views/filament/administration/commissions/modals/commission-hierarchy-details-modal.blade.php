@php
    $sale = $commission->sale;
    $hierarchyRows = [
        [
            'level' => 'Nivel Master',
            'beneficiary' => $masterAgency->name_corporative ?? 'No aplica / sin agencia master',
            'percentage' => (float) ($commission->porcent_agency_master ?? 0),
            'usd' => (float) ($commission->commission_agency_master_usd ?? 0),
            'ves' => (float) ($commission->commission_agency_master_ves ?? 0),
        ],
        [
            'level' => 'Nivel General',
            'beneficiary' => data_get($commission, 'agency.name_corporative', 'N/A'),
            'percentage' => (float) ($commission->porcent_agency_general ?? 0),
            'usd' => (float) ($commission->commission_agency_general_usd ?? 0),
            'ves' => (float) ($commission->commission_agency_general_ves ?? 0),
        ],
        [
            'level' => 'Nivel Agente',
            'beneficiary' => data_get($commission, 'agent.name', 'N/A'),
            'percentage' => (float) ($commission->porcent_agente ?? 0),
            'usd' => (float) ($commission->commission_agent_usd ?? 0),
            'ves' => (float) ($commission->commission_agent_ves ?? 0),
        ],
    ];

    $totalHierarchyUsd = collect($hierarchyRows)->sum('usd');
    $totalHierarchyVes = collect($hierarchyRows)->sum('ves');
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

        <div class="mb-3 grid gap-2 sm:grid-cols-2">
            <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/80 px-3 py-2 dark:border-emerald-700/40 dark:bg-emerald-900/20">
                <p class="text-[11px] uppercase tracking-[0.1em] text-emerald-700 dark:text-emerald-300">Total jerarquía USD</p>
                <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-200">US$ {{ number_format((float) $totalHierarchyUsd, 2, ',', '.') }}</p>
            </div>
            <div class="rounded-xl border border-sky-200/80 bg-sky-50/80 px-3 py-2 dark:border-sky-700/40 dark:bg-sky-900/20">
                <p class="text-[11px] uppercase tracking-[0.1em] text-sky-700 dark:text-sky-300">Total jerarquía VES</p>
                <p class="text-sm font-semibold text-sky-800 dark:text-sky-200">Bs. {{ number_format((float) $totalHierarchyVes, 2, ',', '.') }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white/95 dark:border-white/10 dark:bg-slate-900/40">
            <div class="hidden grid-cols-12 gap-2 border-b border-slate-200/80 bg-slate-50/90 px-3 py-2 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-600 dark:border-white/10 dark:bg-slate-800/50 dark:text-slate-300 md:grid">
                <div class="col-span-3">Nivel</div>
                <div class="col-span-3">Beneficiario</div>
                <div class="col-span-2 text-right">%</div>
                <div class="col-span-2 text-right">USD</div>
                <div class="col-span-2 text-right">VES</div>
            </div>

            @foreach ($hierarchyRows as $row)
                <div class="grid grid-cols-1 gap-2 border-b border-slate-200/70 px-3 py-3 text-sm last:border-b-0 dark:border-white/10 md:grid-cols-12 md:items-center">
                    <div class="md:col-span-3">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-500 dark:text-slate-400 md:hidden">Nivel</p>
                        <p class="font-semibold text-slate-900 dark:text-slate-100">{{ $row['level'] }}</p>
                    </div>
                    <div class="md:col-span-3">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-500 dark:text-slate-400 md:hidden">Beneficiario</p>
                        <p class="break-words text-slate-700 dark:text-slate-300">{{ $row['beneficiary'] }}</p>
                    </div>
                    <div class="md:col-span-2 md:text-right">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-500 dark:text-slate-400 md:hidden">Porcentaje</p>
                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700 md:ml-auto">
                            {{ number_format((float) $row['percentage'], 2, ',', '.') }}%
                        </span>
                    </div>
                    <div class="md:col-span-2 md:text-right">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-500 dark:text-slate-400 md:hidden">USD</p>
                        <p class="font-semibold text-emerald-700 dark:text-emerald-300">US$ {{ number_format((float) $row['usd'], 2, ',', '.') }}</p>
                    </div>
                    <div class="md:col-span-2 md:text-right">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-500 dark:text-slate-400 md:hidden">VES</p>
                        <p class="font-semibold text-emerald-700 dark:text-emerald-300">Bs. {{ number_format((float) $row['ves'], 2, ',', '.') }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
</div>
