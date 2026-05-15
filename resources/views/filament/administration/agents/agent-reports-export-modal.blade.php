@props([
    /** @var array<int, array{key: string, label: string, description: string, icon: string, csvUrl: string, xlsxUrl: string}> $reports */
    'reports' => [],
])

<div class="fi-scoped -mx-1 space-y-5">
    <div class="rounded-2xl border border-amber-500/25 bg-amber-500/10 px-4 py-3 text-sm text-amber-950 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-100">
        <p class="font-semibold">Descarga inmediata</p>
        <p class="mt-1 text-xs leading-relaxed text-amber-900/90 dark:text-amber-50/90">
            Elige <span class="font-medium">CSV</span> para abrir en hojas de cálculo o BI, o
            <span class="font-medium">Excel (.xlsx)</span> para conservar formato en Microsoft Excel o LibreOffice.
            Los datos respetan el mismo alcance que ves en la tabla (por ejemplo, si eres account manager, solo tus agentes).
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
                            @case ('map')
                                <x-heroicon-o-map-pin class="h-5 w-5" />
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
                <div class="flex flex-wrap gap-2 px-4 py-3">
                    <a
                        href="{{ $report['csvUrl'] }}"
                        class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-800 shadow-sm transition hover:border-primary-400/60 hover:bg-primary-50/80 hover:text-primary-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-white/15 dark:bg-gray-950 dark:text-gray-100 dark:hover:border-primary-400/40 dark:hover:bg-primary-500/10"
                    >
                        <x-heroicon-o-arrow-down-tray class="h-4 w-4 shrink-0" />
                        CSV
                    </a>
                    <a
                        href="{{ $report['xlsxUrl'] }}"
                        class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-primary-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-primary-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-400 focus-visible:ring-offset-2 dark:ring-offset-gray-900"
                    >
                        <x-heroicon-o-document-text class="h-4 w-4 shrink-0" />
                        Excel
                    </a>
                </div>
            </div>
        @endforeach
    </div>

    <p class="text-center text-[11px] text-gray-400 dark:text-gray-500">
        Formato heredado <span class="font-medium">.xls</span> no está disponible; use Excel (.xlsx) o CSV.
    </p>
</div>
