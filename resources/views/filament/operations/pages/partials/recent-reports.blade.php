@php
    $reports = $this->recentReports;
    $pending = $this->pendingReports;
@endphp

<section
    class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
    @if ($pending !== []) wire:poll.5s.visible="checkPendingReports" @endif
>
    <header class="mb-3 flex items-center justify-between gap-2">
        <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Mis reportes recientes</h2>
        <span class="text-xs text-gray-500 dark:text-gray-400">Se conservan 7 días</span>
    </header>

    @if ($pending !== [])
        <div class="mb-3 flex items-center gap-2 rounded-lg bg-primary-50 px-3 py-2 text-sm text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
            <x-filament::loading-indicator class="size-4" />
            <span>
                {{ count($pending) === 1 ? 'Generando 1 reporte…' : 'Generando '.count($pending).' reportes…' }}
                Puede seguir trabajando.
            </span>
        </div>
    @endif

    @if ($reports === [])
        <p class="text-sm text-gray-500 dark:text-gray-400">Aún no ha generado reportes. Los que genere aparecerán aquí.</p>
    @else
        <ul class="divide-y divide-gray-100 dark:divide-white/5">
            @foreach ($reports as $report)
                <li wire:key="recent-report-{{ $report['name'] }}" class="flex items-center justify-between gap-3 py-2">
                    <span class="flex min-w-0 items-center gap-3">
                        <x-filament::icon
                            :icon="$report['format']?->icon() ?? 'heroicon-o-document'"
                            class="size-5 shrink-0 text-gray-400"
                        />
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium text-gray-950 dark:text-white">
                                {{ $report['label'] }}
                                <span class="text-gray-500 dark:text-gray-400">· {{ $report['format']?->label() }}</span>
                            </span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400">
                                {{ $report['created_at']->timezone(config('app.timezone'))->format('d/m/Y h:i A') }} · {{ $report['size'] }}
                            </span>
                        </span>
                    </span>
                    <x-filament::button
                        tag="a"
                        :href="$this->downloadUrl($report['name'])"
                        size="sm"
                        color="gray"
                        icon="heroicon-m-arrow-down-tray"
                    >
                        Descargar
                    </x-filament::button>
                </li>
            @endforeach
        </ul>
    @endif
</section>
