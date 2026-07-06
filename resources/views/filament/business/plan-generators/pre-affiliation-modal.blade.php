@php
    /** @var \App\Models\PlanGenerator $plan */
    $planName = (string) ($plan->name ?? 'Sin nombre');
    $controlNumber = filled($plan->control_number) ? (string) $plan->control_number : '—';
    $clientData = filled($plan->client_data) ? (string) $plan->client_data : '—';
    $agentName = filled($plan->agent_name) ? (string) $plan->agent_name : '—';
@endphp

<div
    class="pg-pre-affiliation-modal relative space-y-5"
    x-data="{
        selected: null,
        messageFor(option) {
            return {
                individual: 'Cargando afiliación individual y tarifas del plan…',
                corporate: 'Cargando afiliación corporativa y columnas del plan…',
                new_business: 'Abriendo registro de empresa en Nuevos Negocios…',
            }[option] ?? 'Preparando pre-afiliación…';
        },
    }"
    x-on:keydown.escape.window="if (! selected) { return; }">
    <div
        x-show="selected"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        class="absolute inset-0 z-30 flex items-center justify-center rounded-[1.25rem] bg-slate-950/80 backdrop-blur-[3px]"
        role="status"
        aria-live="polite"
        aria-busy="true">
        <div
            class="mx-4 w-full max-w-sm animate-pulse rounded-[1.5rem] border border-white/10 bg-white px-6 py-7 text-center shadow-[0_24px_60px_-20px_rgba(0,0,0,0.55)] dark:bg-slate-900">
            <div class="mx-auto mb-4 flex h-[4.5rem] w-[4.5rem] items-center justify-center rounded-full bg-gradient-to-br from-emerald-400 to-emerald-600 shadow-[0_12px_32px_-8px_rgba(16,185,129,0.65)]">
                <svg
                    class="h-10 w-10 animate-spin text-white"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    aria-hidden="true">
                    <circle class="opacity-30" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                    <path class="opacity-95" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            <p class="text-lg font-bold tracking-tight text-slate-900 dark:text-white">
                Preparando pre-afiliación
            </p>
            <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400" x-text="messageFor(selected)"></p>
            <div class="mt-5 flex items-center justify-center gap-1.5">
                <span class="h-2 w-2 animate-bounce rounded-full bg-emerald-500 [animation-delay:-0.2s]"></span>
                <span class="h-2 w-2 animate-bounce rounded-full bg-emerald-500 [animation-delay:-0.1s]"></span>
                <span class="h-2 w-2 animate-bounce rounded-full bg-emerald-500"></span>
            </div>
        </div>
    </div>

    <div
        class="overflow-hidden rounded-[1.35rem] border border-amber-200/80 bg-gradient-to-br from-amber-50 via-white to-orange-50/60 shadow-[0_12px_40px_-16px_rgba(217,119,6,0.35)] ring-1 ring-amber-100/80 transition duration-200 dark:border-amber-500/20 dark:from-amber-950/40 dark:via-slate-950 dark:to-orange-950/20 dark:ring-amber-500/10"
        :class="selected ? 'pointer-events-none opacity-40 blur-[1px]' : ''">
        <div class="border-b border-amber-200/70 px-5 py-4 dark:border-amber-500/15">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-[0.65rem] font-bold uppercase tracking-[0.18em] text-amber-700 dark:text-amber-300">
                        Cotización a pre-aprobar
                    </p>
                    <h3 class="mt-1 truncate text-lg font-bold tracking-tight text-slate-900 dark:text-white">
                        {{ $planName }}
                    </h3>
                </div>
                <span
                    class="inline-flex shrink-0 items-center rounded-full bg-amber-500 px-3 py-1 text-[0.65rem] font-bold uppercase tracking-wide text-white shadow-[0_8px_20px_rgba(217,119,6,0.35)]">
                    PRE-APROBADO
                </span>
            </div>
        </div>

        <dl class="grid gap-3 px-5 py-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-white/70 bg-white/70 px-3.5 py-3 dark:border-white/10 dark:bg-white/5">
                <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Nro. control</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $controlNumber }}</dd>
            </div>
            <div class="rounded-2xl border border-white/70 bg-white/70 px-3.5 py-3 dark:border-white/10 dark:bg-white/5">
                <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Cliente</dt>
                <dd class="mt-1 line-clamp-2 text-sm font-semibold text-slate-900 dark:text-white">{{ $clientData }}</dd>
            </div>
            <div class="rounded-2xl border border-white/70 bg-white/70 px-3.5 py-3 dark:border-white/10 dark:bg-white/5">
                <dt class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Agente</dt>
                <dd class="mt-1 line-clamp-2 text-sm font-semibold text-slate-900 dark:text-white">{{ $agentName }}</dd>
            </div>
        </dl>
    </div>

    <div
        class="flex gap-3 rounded-2xl border border-slate-200/80 bg-slate-50/90 px-4 py-3.5 text-sm leading-relaxed text-slate-600 transition duration-200 dark:border-white/10 dark:bg-white/5 dark:text-slate-300"
        :class="selected ? 'pointer-events-none opacity-40' : ''">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
        </svg>
        <p>
            Elija el destino de la pre-afiliación. El plan <strong class="font-semibold text-slate-800 dark:text-white">permanecerá en estatus PRE-APROBADO</strong>
            hasta completar el proceso correspondiente.
        </p>
    </div>

    <div class="space-y-3" :class="selected ? 'pointer-events-none' : ''">
        <p class="text-xs font-bold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">
            Seleccione una opción
        </p>

        <button
            type="button"
            data-pre-affiliation-option
            x-on:click="selected = 'individual'"
            wire:click="approveQuote('individual')"
            wire:loading.attr="disabled"
            wire:target="approveQuote"
            :disabled="selected && selected !== 'individual'"
            :class="selected && selected !== 'individual' ? 'opacity-35' : ''"
            class="group relative flex w-full items-center gap-4 overflow-hidden rounded-[1.25rem] border border-emerald-200/80 bg-gradient-to-r from-emerald-50 via-white to-white px-4 py-4 text-left shadow-[0_10px_30px_-18px_rgba(16,185,129,0.55)] transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-[0_16px_36px_-16px_rgba(16,185,129,0.45)] disabled:cursor-wait dark:border-emerald-500/20 dark:from-emerald-950/30 dark:via-slate-950 dark:to-slate-950 dark:hover:border-emerald-500/35">
            <span
                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-500 text-white shadow-[0_10px_24px_-10px_rgba(16,185,129,0.8)]">
                <svg
                    x-show="selected !== 'individual'"
                    class="h-6 w-6"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.8"
                    stroke="currentColor"
                    aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                </svg>
                <svg
                    x-show="selected === 'individual'"
                    x-cloak
                    class="h-7 w-7 animate-spin"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    aria-hidden="true">
                    <circle class="opacity-30" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                    <path class="opacity-95" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-bold text-slate-900 dark:text-white">
                    <span x-show="selected !== 'individual'">Pre-afiliar Individual</span>
                    <span x-show="selected === 'individual'" x-cloak>Redirigiendo…</span>
                </span>
                <span class="mt-0.5 block text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                    Abre el formulario de afiliación individual con las tarifas del plan en sesión.
                </span>
            </span>
            <svg
                x-show="selected !== 'individual'"
                class="h-5 w-5 shrink-0 text-emerald-500 transition group-hover:translate-x-0.5"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="2"
                stroke="currentColor"
                aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
        </button>

        <button
            type="button"
            data-pre-affiliation-option
            x-on:click="selected = 'corporate'"
            wire:click="approveQuote('corporate')"
            wire:loading.attr="disabled"
            wire:target="approveQuote"
            :disabled="selected && selected !== 'corporate'"
            :class="selected && selected !== 'corporate' ? 'opacity-35' : ''"
            class="group relative flex w-full items-center gap-4 overflow-hidden rounded-[1.25rem] border border-sky-200/80 bg-gradient-to-r from-sky-50 via-white to-white px-4 py-4 text-left shadow-[0_10px_30px_-18px_rgba(14,165,233,0.45)] transition hover:-translate-y-0.5 hover:border-sky-300 hover:shadow-[0_16px_36px_-16px_rgba(14,165,233,0.4)] disabled:cursor-wait dark:border-sky-500/20 dark:from-sky-950/30 dark:via-slate-950 dark:to-slate-950 dark:hover:border-sky-500/35">
            <span
                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-sky-500 text-white shadow-[0_10px_24px_-10px_rgba(14,165,233,0.75)]">
                <svg
                    x-show="selected !== 'corporate'"
                    class="h-6 w-6"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.8"
                    stroke="currentColor"
                    aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                </svg>
                <svg
                    x-show="selected === 'corporate'"
                    x-cloak
                    class="h-7 w-7 animate-spin"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    aria-hidden="true">
                    <circle class="opacity-30" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                    <path class="opacity-95" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-bold text-slate-900 dark:text-white">
                    <span x-show="selected !== 'corporate'">Pre-afiliar Grupo Corporativo</span>
                    <span x-show="selected === 'corporate'" x-cloak>Redirigiendo…</span>
                </span>
                <span class="mt-0.5 block text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                    Redirige a afiliación corporativa con columnas y tarifas del plan cargadas.
                </span>
            </span>
            <svg
                x-show="selected !== 'corporate'"
                class="h-5 w-5 shrink-0 text-sky-500 transition group-hover:translate-x-0.5"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="2"
                stroke="currentColor"
                aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
        </button>

        <button
            type="button"
            data-pre-affiliation-option
            x-on:click="selected = 'new_business'"
            wire:click="approveQuote('new_business')"
            wire:loading.attr="disabled"
            wire:target="approveQuote"
            :disabled="selected && selected !== 'new_business'"
            :class="selected && selected !== 'new_business' ? 'opacity-35' : ''"
            class="group relative flex w-full items-center gap-4 overflow-hidden rounded-[1.25rem] border border-orange-200/80 bg-gradient-to-r from-orange-50 via-white to-white px-4 py-4 text-left shadow-[0_10px_30px_-18px_rgba(249,115,22,0.4)] transition hover:-translate-y-0.5 hover:border-orange-300 hover:shadow-[0_16px_36px_-16px_rgba(249,115,22,0.35)] disabled:cursor-wait dark:border-orange-500/20 dark:from-orange-950/30 dark:via-slate-950 dark:to-slate-950 dark:hover:border-orange-500/35">
            <span
                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-orange-500 text-white shadow-[0_10px_24px_-10px_rgba(249,115,22,0.75)]">
                <svg
                    x-show="selected !== 'new_business'"
                    class="h-6 w-6"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.8"
                    stroke="currentColor"
                    aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
                </svg>
                <svg
                    x-show="selected === 'new_business'"
                    x-cloak
                    class="h-7 w-7 animate-spin"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    aria-hidden="true">
                    <circle class="opacity-30" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                    <path class="opacity-95" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-bold text-slate-900 dark:text-white">
                    <span x-show="selected !== 'new_business'">Pre-afiliar Nuevo Negocio</span>
                    <span x-show="selected === 'new_business'" x-cloak>Redirigiendo…</span>
                </span>
                <span class="mt-0.5 block text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                    Continúa con el registro de empresa en Nuevos Negocios.
                </span>
            </span>
            <svg
                x-show="selected !== 'new_business'"
                class="h-5 w-5 shrink-0 text-orange-500 transition group-hover:translate-x-0.5"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="2"
                stroke="currentColor"
                aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
            </svg>
        </button>
    </div>
</div>
