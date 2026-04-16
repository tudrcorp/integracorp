@php
    $agency = $agency ?? null;
@endphp

<div class="space-y-4 px-0.5 py-1">
    @if ($agency)
        <div class="overflow-hidden rounded-[1.25rem] border border-gray-200/80 bg-gray-50/90 shadow-inner ring-1 ring-black/[0.03] dark:border-white/10 dark:bg-white/[0.06] dark:ring-white/[0.05]">
            <div class="flex items-center justify-between gap-3 border-b border-gray-200/70 px-4 py-3.5 dark:border-white/10">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-white shadow-sm ring-1 ring-black/5 dark:bg-gray-800 dark:ring-white/10">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 text-sky-600 dark:text-sky-400" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15a.75.75 0 0 1 .75.75V21H3.75V3.75A.75.75 0 0 1 4.5 3ZM9 9.75h6M9 13.5h6M9 17.25h6" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-[0.65rem] font-semibold uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">Agencia comercial</p>
                        <p class="truncate text-sm font-semibold tracking-tight text-gray-900 dark:text-white">{{ $agency->name_corporative ?: 'Sin nombre' }}</p>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Codigo: {{ $agency->code ?: 'N/A' }}</p>
                    </div>
                </div>
                <span class="shrink-0 rounded-full px-3 py-1.5 text-xs font-bold uppercase tracking-wide ring-1 {{ ($agency->status ?? null) === 'ACTIVO' ? 'bg-emerald-500/15 text-emerald-700 ring-emerald-500/25 dark:bg-emerald-400/15 dark:text-emerald-200 dark:ring-emerald-400/30' : 'bg-amber-500/15 text-amber-700 ring-amber-500/25 dark:bg-amber-400/15 dark:text-amber-200 dark:ring-amber-400/30' }}">
                    {{ $agency->status ?: 'SIN ESTATUS' }}
                </span>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <section class="rounded-[1.1rem] border border-gray-200/80 bg-white/90 p-4 shadow-sm dark:border-white/10 dark:bg-white/[0.04]">
                <p class="mb-2 text-[0.65rem] font-semibold uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">Identificacion</p>
                <dl class="space-y-2 text-sm">
                    <div class="flex items-start justify-between gap-2">
                        <dt class="font-medium text-gray-600 dark:text-gray-300">RIF</dt>
                        <dd class="text-right text-gray-900 dark:text-white">{{ $agency->rif ?: 'N/A' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-2">
                        <dt class="font-medium text-gray-600 dark:text-gray-300">Representante</dt>
                        <dd class="text-right text-gray-900 dark:text-white">{{ $agency->name_representative ?: 'N/A' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-2">
                        <dt class="font-medium text-gray-600 dark:text-gray-300">Codigo de agencia</dt>
                        <dd class="text-right text-gray-900 dark:text-white">{{ $agency->code_agency ?: 'N/A' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-[1.1rem] border border-gray-200/80 bg-white/90 p-4 shadow-sm dark:border-white/10 dark:bg-white/[0.04]">
                <p class="mb-2 text-[0.65rem] font-semibold uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">Contacto</p>
                <dl class="space-y-2 text-sm">
                    <div class="flex items-start justify-between gap-2">
                        <dt class="font-medium text-gray-600 dark:text-gray-300">Telefono</dt>
                        <dd class="text-right text-gray-900 dark:text-white">{{ $agency->phone ?: 'N/A' }}</dd>
                    </div>
                    <div class="flex items-start justify-between gap-2">
                        <dt class="font-medium text-gray-600 dark:text-gray-300">Email</dt>
                        <dd class="text-right text-gray-900 dark:text-white break-all">
                            @if (filled($agency->email))
                                <button
                                    type="button"
                                    x-data="{ copied: false }"
                                    x-on:click="
                                        navigator.clipboard?.writeText(@js($agency->email));
                                        copied = true;
                                        setTimeout(() => copied = false, 1500);
                                    "
                                    class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 font-medium text-sky-700 underline decoration-dotted underline-offset-2 transition hover:text-sky-600 dark:text-sky-300 dark:hover:text-sky-200"
                                    title="Copiar correo"
                                >
                                    <span>{{ $agency->email }}</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-3.5">
                                        <path d="M15.5 3h-7A2.5 2.5 0 0 0 6 5.5v7A2.5 2.5 0 0 0 8.5 15h7a2.5 2.5 0 0 0 2.5-2.5v-7A2.5 2.5 0 0 0 15.5 3Z" />
                                        <path d="M3.5 5A2.5 2.5 0 0 0 1 7.5v7A2.5 2.5 0 0 0 3.5 17H11a2.5 2.5 0 0 0 2.45-2h-5A4.5 4.5 0 0 1 4 10.5v-5A2.5 2.5 0 0 0 3.5 5Z" />
                                    </svg>
                                    <span x-show="copied" x-cloak class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-300">Copiado</span>
                                </button>
                            @else
                                <span>N/A</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex items-start justify-between gap-2">
                        <dt class="font-medium text-gray-600 dark:text-gray-300">Instagram</dt>
                        <dd class="text-right text-gray-900 dark:text-white">{{ $agency->user_instagram ?: 'N/A' }}</dd>
                    </div>
                </dl>
            </section>
        </div>

        <section class="rounded-[1.1rem] border border-gray-200/80 bg-white/90 p-4 shadow-sm dark:border-white/10 dark:bg-white/[0.04]">
            <p class="mb-2 text-[0.65rem] font-semibold uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">Ubicacion</p>
            <dl class="grid gap-2 text-sm sm:grid-cols-2">
                <div class="flex items-start justify-between gap-2 sm:block">
                    <dt class="font-medium text-gray-600 dark:text-gray-300">Pais</dt>
                    <dd class="text-gray-900 dark:text-white">{{ data_get($agency, 'country.name', 'N/A') }}</dd>
                </div>
                <div class="flex items-start justify-between gap-2 sm:block">
                    <dt class="font-medium text-gray-600 dark:text-gray-300">Estado</dt>
                    <dd class="text-gray-900 dark:text-white">{{ data_get($agency, 'state.name', 'N/A') }}</dd>
                </div>
                <div class="flex items-start justify-between gap-2 sm:block">
                    <dt class="font-medium text-gray-600 dark:text-gray-300">Ciudad</dt>
                    <dd class="text-gray-900 dark:text-white">{{ data_get($agency, 'city.name', 'N/A') }}</dd>
                </div>
                <div class="flex items-start justify-between gap-2 sm:block">
                    <dt class="font-medium text-gray-600 dark:text-gray-300">Direccion</dt>
                    <dd class="text-gray-900 dark:text-white">{{ $agency->address ?: 'N/A' }}</dd>
                </div>
            </dl>
        </section>
    @else
        <div class="rounded-[1.25rem] border border-dashed border-gray-300/90 bg-white/60 px-4 py-10 text-center text-sm text-gray-500 dark:border-white/15 dark:bg-white/[0.04] dark:text-gray-400">
            <p class="font-medium text-gray-600 dark:text-gray-300">Sin datos de agencia</p>
            <p class="mt-1 text-xs">Esta venta no tiene una agencia relacionada para mostrar.</p>
        </div>
    @endif

    <p class="text-center text-[0.7rem] text-gray-400 dark:text-gray-500">
        Venta #{{ $record->id }} · Recibo {{ $record->invoice_number }}
    </p>
</div>
