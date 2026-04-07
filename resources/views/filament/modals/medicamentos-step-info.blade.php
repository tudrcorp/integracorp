{{--
  Modal informativa paso Medicamentos — UI tipo iOS; padding generoso; dark con buen contraste.
--}}
<div class="medicamentos-step-info-ios -mx-1 -mt-1 space-y-0 text-left">
    {{-- Cabecera tipo sheet iOS --}}
    <div class="rounded-t-2xl border-b border-gray-100 bg-gradient-to-b from-slate-50 to-white px-6 pt-6 pb-5 dark:border-zinc-700/80 dark:from-zinc-800 dark:to-zinc-900" style="box-shadow: inset 0 1px 0 rgba(255,255,255,0.6);">
        <div class="flex items-start gap-4">
            <div
                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-white shadow-lg ring-1 ring-black/5 dark:ring-sky-400/30"
                style="background: linear-gradient(180deg, #0ea5e9 0%, #0284c7 100%); box-shadow: 0 4px 14px rgba(2, 132, 199, 0.35), 0 1px 0 rgba(255,255,255,0.2) inset;"
            >
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 0 1-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 0 1 4.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0 1 12 15a9.065 9.065 0 0 0-6.23-.693L5 14.5m14.8.8 1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0 1 12 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5" />
                </svg>
            </div>
            <div class="min-w-0 flex-1 pt-1">
                <p class="text-[0.8125rem] font-semibold uppercase tracking-wider text-sky-600 dark:text-sky-300">
                    Antes de continuar
                </p>
                <h3 class="mt-1 text-lg font-semibold leading-tight tracking-tight text-gray-900 dark:text-zinc-50">
                    Medicamentos e indicaciones
                </h3>
            </div>
        </div>
    </div>

    {{-- Cuerpo: más padding y dark con más contraste; pt generoso para separar del header --}}
    <div class="space-y-3 bg-white px-6 pb-6 pt-6 mt-1 dark:bg-zinc-900">
        <div
            class="rounded-2xl bg-slate-50/80 p-5 shadow-sm ring-1 ring-gray-200/90 dark:bg-zinc-800 dark:ring-amber-500/15"
            style="box-shadow: 0 1px 2px rgba(0,0,0,0.04), 0 4px 12px rgba(0,0,0,0.06);"
        >
            <ul class="space-y-4 text-[0.9375rem] leading-relaxed text-gray-800 dark:text-zinc-200">
                <li class="flex gap-4">
                    <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-700 ring-1 ring-emerald-500/25 dark:bg-emerald-500/25 dark:text-emerald-300 dark:ring-emerald-400/40">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    </span>
                    <span><strong class="font-semibold text-gray-900 dark:text-white">Inventario TDC:</strong> puede elegir el medicamento en el primer campo si está en el inventario.</span>
                </li>
                <li class="flex gap-4">
                    <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-amber-500/20 text-amber-800 ring-1 ring-amber-500/30 dark:bg-amber-500/20 dark:text-amber-200 dark:ring-amber-400/50">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" /></svg>
                    </span>
                    <span><strong class="font-semibold text-gray-900 dark:text-white">No está en inventario:</strong> escriba el nombre del medicamento en el campo de texto.</span>
                </li>
            </ul>
        </div>

        {{-- Aviso: dark con borde y fondo más visibles --}}
        <div
            class="flex gap-4 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 dark:border-amber-400/35 dark:bg-amber-950/50 dark:ring-1 dark:ring-amber-500/20"
            style="box-shadow: 0 1px 0 rgba(255,255,255,0.5) inset;"
        >
            <span class="shrink-0 text-amber-600 dark:text-amber-300" aria-hidden="true">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </span>
            <p class="text-[0.8125rem] font-medium leading-snug text-amber-950 dark:text-amber-50">
                No complete <strong class="font-semibold">ambos</strong> campos a la vez. Use <strong class="font-semibold">solo inventario</strong> o <strong class="font-semibold">solo nombre manual</strong>.
            </p>
        </div>
    </div>
</div>

<style>
    .fi-modal-window:has(.medicamentos-step-info-ios) .fi-modal-header {
        display: none;
    }
    .fi-modal-window:has(.medicamentos-step-info-ios) .fi-modal-content {
        padding: 0;
        padding-bottom: 1.25rem;
    }
    .fi-modal-window:has(.medicamentos-step-info-ios) .fi-modal-footer {
        padding-left: 1.5rem;
        padding-right: 1.5rem;
        padding-bottom: 1.25rem;
    }
    .dark .fi-modal-window.medicamentos-step-info-modal-window {
        box-shadow:
            0 25px 50px -12px rgba(0, 0, 0, 0.5),
            0 0 0 1px rgba(255, 255, 255, 0.06),
            inset 0 1px 0 rgba(255, 255, 255, 0.04) !important;
    }
    .fi-modal-footer-actions .fi-btn,
    [data-slot="modal"] .fi-modal-footer .fi-btn {
        border-radius: 0.875rem;
        font-weight: 600;
        letter-spacing: -0.01em;
        transition: transform 0.15s ease, box-shadow 0.2s ease;
    }
    .fi-modal-footer-actions .fi-btn:active,
    [data-slot="modal"] .fi-modal-footer .fi-btn:active {
        transform: scale(0.98);
    }
</style>
