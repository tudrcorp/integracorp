<div class="space-y-4">
    <div
        x-data="{ enabled: @js((bool) $supplier->gestion_integracorp) }"
        x-effect="enabled = $wire.gestionIntegracorp"
        class="space-y-4"
    >
        <div x-show="enabled" x-cloak>
            @include('filament.operations.suppliers.partials.integracorp-modules-panel', [
                'enabled' => true,
            ])
        </div>
        <div x-show="! enabled" x-cloak>
            @include('filament.operations.suppliers.partials.integracorp-modules-panel', [
                'enabled' => false,
            ])
        </div>

        <div
            class="rounded-2xl border border-slate-200/90 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5"
        >
            <label class="flex cursor-pointer items-center justify-between gap-4">
                <span class="min-w-0">
                    <span class="block text-sm font-semibold text-slate-900 dark:text-white">
                        Habilitar gestión en Integracorp
                    </span>
                    <span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">
                        El cambio se guarda de inmediato. Solo rol SUPERADMIN.
                    </span>
                </span>
                <input
                    type="checkbox"
                    wire:model.live="gestionIntegracorp"
                    class="fi-checkbox-input size-5 shrink-0 rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-white/20 dark:bg-white/5 dark:checked:bg-primary-500 dark:focus:ring-primary-400"
                />
            </label>
            <p
                x-show="enabled"
                x-cloak
                class="mt-3 rounded-xl bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-100"
            >
                Funciones aceptadas: telemedicina, servicios médicos y órdenes de servicio.
            </p>
            <p
                x-show="! enabled"
                x-cloak
                class="mt-3 rounded-xl bg-slate-100 px-3 py-2 text-xs font-medium text-slate-600 dark:bg-white/5 dark:text-slate-300"
            >
                Gestión deshabilitada. El proveedor no tiene acceso a los módulos de Operaciones.
            </p>
        </div>
    </div>

    <div
        x-data
        x-show="$wire.gestionIntegracorp"
        x-cloak
        class="space-y-3"
    >
        <livewire:operations.supplier-integracorp-portal-users-panel
            :supplier="$supplier"
            :key="'supplier-portal-users-'.$supplier->getKey()"
        />
    </div>
</div>
