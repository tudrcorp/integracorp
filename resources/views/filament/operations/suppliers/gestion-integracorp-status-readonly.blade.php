@php
    $enabled = (bool) ($supplier->gestion_integracorp ?? false);
@endphp

<div class="space-y-4">
    @include('filament.operations.suppliers.partials.integracorp-modules-panel', [
        'enabled' => $enabled,
    ])

    <div
        class="rounded-2xl border border-slate-200/90 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5"
    >
        <div class="flex items-center justify-between gap-4">
            <span class="min-w-0">
                <span class="block text-sm font-semibold text-slate-900 dark:text-white">
                    Habilitar gestión en Integracorp
                </span>
                <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">
                    Estado de aceptación de funciones para el proveedor.
                </span>
            </span>
            <input
                type="checkbox"
                disabled
                @checked($enabled)
                class="fi-checkbox-input size-5 shrink-0 cursor-not-allowed rounded border-gray-300 text-primary-600 opacity-100 shadow-sm dark:border-white/20 dark:bg-white/5"
            />
        </div>
        <p @class([
            'mt-3 rounded-xl px-3 py-2 text-xs font-medium',
            'bg-emerald-50 text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-100' => $enabled,
            'bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300' => ! $enabled,
        ])>
            @if ($enabled)
                El proveedor aceptó la gestión en Integracorp. Tiene acceso a telemedicina, servicios médicos y órdenes de servicio.
            @else
                El proveedor no tiene habilitada la gestión en Integracorp. Los módulos de Operaciones permanecen inactivos.
            @endif
        </p>
    </div>
</div>
