<div class="space-y-4 px-1 py-1">
    <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-5 shadow-sm backdrop-blur dark:border-white/10 dark:bg-slate-900/70">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1">
                <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Agencia</p>
                <h3 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-slate-100">{{ $agency->name_corporative ?: 'Sin nombre' }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $agency->code ?: 'Sin código' }}</p>
            </div>
            <span @class([
                'rounded-full px-3 py-1 text-xs font-semibold ring-1',
                'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:ring-emerald-700/50' => ($agency->status ?? null) === 'ACTIVO',
                'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:ring-amber-700/50' => ($agency->status ?? null) !== 'ACTIVO',
            ])>
                {{ $agency->status ?: 'SIN ESTATUS' }}
            </span>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-2xl border border-slate-200/80 bg-white/90 p-4 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
            <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Identificación</p>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">RIF</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ $agency->rif ?: 'N/A' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Tipo</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ data_get($agency, 'typeAgency.definition', 'N/A') }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Responsable</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ $agency->name_representative ?: 'N/A' }}</dd></div>
            </dl>
        </div>
        <div class="rounded-2xl border border-slate-200/80 bg-white/90 p-4 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
            <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Contacto</p>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Teléfono</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ $agency->phone ?: 'N/A' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Correo</dt><dd class="font-medium text-slate-900 dark:text-slate-100 break-all">{{ $agency->email ?: 'N/A' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Instagram</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ $agency->user_instagram ?: 'N/A' }}</dd></div>
            </dl>
        </div>
    </div>

    <div class="rounded-2xl border border-slate-200/80 bg-white/90 p-4 shadow-sm dark:border-white/10 dark:bg-slate-900/70">
        <p class="mb-2 text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">Ubicación y operación</p>
        <dl class="grid gap-2 text-sm md:grid-cols-2">
            <div><dt class="text-slate-500 dark:text-slate-400">Propietario</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ $agency->owner_code ?: 'N/A' }}</dd></div>
            <div><dt class="text-slate-500 dark:text-slate-400">Dirección</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ $agency->address ?: 'N/A' }}</dd></div>
            <div><dt class="text-slate-500 dark:text-slate-400">País</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ data_get($agency, 'country.name', 'N/A') }}</dd></div>
            <div><dt class="text-slate-500 dark:text-slate-400">Ciudad</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{{ data_get($agency, 'city.name', 'N/A') }}</dd></div>
        </dl>
    </div>
</div>
