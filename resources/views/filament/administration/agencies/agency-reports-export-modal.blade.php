@props([
    /** @var array<int, array{key: string, label: string, description: string, icon: string, csvAction: string}> $reports */
    'reports' => [],
    'action' => null,
])

<div class="fi-scoped -mx-1 space-y-5">
    <div class="rounded-2xl border border-amber-500/25 bg-amber-500/10 px-4 py-3 text-sm text-amber-950 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-100">
        <p class="font-semibold">Descarga inmediata en CSV</p>
        <p class="mt-1 text-xs leading-relaxed text-amber-900/90 dark:text-amber-50/90">
            Pulsa <span class="font-medium">Descargar CSV</span> en cada tarjeta para obtener el archivo.
            Los datos respetan el mismo alcance que ves en la tabla (por ejemplo, si eres account manager, solo tus agencias).
        </p>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        @foreach ($reports as $report)
            <div
                class="flex flex-col overflow-hidden rounded-2xl border border-gray-200/80 bg-white/90 shadow-sm ring-1 ring-black/5 dark:border-white/10 dark:bg-gray-900/80 dark:ring-white/10"
            >
                <div class="flex items-start gap-3 border-b border-gray-100 px-4 py-3 dark:border-white/10">
                    <span
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-500/15 text-primary-700 dark:bg-primary-400/15 dark:text-primary-200"
                        aria-hidden="true"
                    >
                        @switch ($report['icon'])
                            @case ('percent')
                                <x-heroicon-o-calculator class="h-5 w-5" />
                                @break
                            @case ('hierarchy')
                                <x-heroicon-o-squares-2x2 class="h-5 w-5" />
                                @break
                            @case ('map')
                                <x-heroicon-o-map-pin class="h-5 w-5" />
                                @break
                            @case ('tag')
                                <x-heroicon-o-tag class="h-5 w-5" />
                                @break
                            @case ('status')
                                <x-heroicon-o-signal class="h-5 w-5" />
                                @break
                            @default
                                <x-heroicon-o-document-chart-bar class="h-5 w-5" />
                        @endswitch
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold leading-snug text-gray-900 dark:text-gray-50">
                            {{ $report['label'] }}
                        </p>
                        <p class="mt-1 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                            {{ $report['description'] }}
                        </p>
                    </div>
                </div>
                <div class="px-4 py-3">
                    @if ($action !== null)
                        <div class="[&_.fi-btn]:w-full [&_.fi-btn]:justify-center">
                            {{ $action->getModalAction($report['csvAction']) }}
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <p class="text-center text-[11px] text-gray-400 dark:text-gray-500">
        Los archivos CSV incluyen codificación UTF-8 con BOM para abrir correctamente en Excel.
    </p>
</div>
