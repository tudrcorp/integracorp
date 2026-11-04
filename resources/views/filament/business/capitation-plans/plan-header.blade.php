{{-- Cabecera de la ficha del plan. Variables con prefijo `plan` para no chocar con las reservadas del componente. --}}
<div class="overflow-hidden rounded-3xl border border-gray-200/80 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
    <div class="h-1.5 w-full {{ $planIsActive ? 'bg-linear-to-r from-sky-600 via-cyan-500 to-emerald-500' : 'bg-gray-300 dark:bg-gray-700' }}"></div>

    <div class="grid gap-6 p-5 md:grid-cols-[minmax(0,1fr)_auto] md:items-start md:p-6">
        <div class="min-w-0 space-y-3">
            <div class="flex flex-wrap items-center gap-2">
                <span @class([
                    'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold',
                    'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300' => $planIsActive,
                    'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300' => ! $planIsActive,
                ])>
                    <span @class(['h-1.5 w-1.5 rounded-full', 'bg-emerald-500' => $planIsActive, 'bg-gray-400' => ! $planIsActive])></span>
                    {{ $planIsActive ? 'Activo' : 'Inactivo' }}
                </span>
                <span @class([
                    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                    'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-300' => $planIsExpired,
                    'bg-sky-50 text-sky-800 dark:bg-sky-500/10 dark:text-sky-300' => ! $planIsExpired,
                ])>
                    {{ $planIsExpired ? 'Vencido · ' : '' }}{{ $planValidity }}
                </span>
            </div>

            <h2 class="text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">{{ $planName }}</h2>

            <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-gray-600 dark:text-gray-400">
                <x-filament::icon icon="heroicon-o-building-office-2" class="h-4 w-4 text-gray-400" />
                <span class="font-medium text-gray-800 dark:text-gray-200">{{ $planProviderName }}</span>
                <span class="text-gray-300 dark:text-gray-600">·</span>
                <span>{{ $planProviderType }}</span>
                @if (filled($planProviderRif))
                    <span class="text-gray-300 dark:text-gray-600">·</span>
                    <span>{{ $planProviderRif }}</span>
                @endif
            </div>
        </div>

        <div class="rounded-2xl bg-gray-50 px-5 py-4 text-left ring-1 ring-gray-200/80 md:min-w-56 md:text-right dark:bg-white/5 dark:ring-white/10">
            <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Costo por afiliado</p>
            <p class="mt-1 text-3xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ $planCost }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $planFrequency }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 divide-y divide-gray-200/80 border-t border-gray-200/80 sm:grid-cols-3 sm:divide-x sm:divide-y-0 dark:divide-white/10 dark:border-white/10">
        <div class="flex items-center gap-3 px-5 py-3">
            <x-filament::icon icon="heroicon-o-sparkles" class="h-5 w-5 text-sky-500" />
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400">Beneficios</p>
                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $planBenefitsCount }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 px-5 py-3">
            <x-filament::icon icon="heroicon-o-paper-clip" class="h-5 w-5 text-sky-500" />
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400">Documentos de respaldo</p>
                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $planDocumentsCount === 0 ? 'Ninguno' : $planDocumentsCount }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 px-5 py-3">
            <x-filament::icon icon="heroicon-o-clock" class="h-5 w-5 text-sky-500" />
            <div class="min-w-0">
                <p class="text-xs text-gray-500 dark:text-gray-400">Última actualización</p>
                <p class="truncate text-sm font-semibold text-gray-900 dark:text-white" title="{{ $planUpdatedBy }}">{{ $planUpdatedBy }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $planUpdatedAt }}</p>
            </div>
        </div>
    </div>
</div>
