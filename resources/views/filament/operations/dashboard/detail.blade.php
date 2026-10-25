@php
    use App\Support\Operations\Dashboard\OperationsDashboardChartJs;

    /** @var list<array{label: string, key: string}> $columns */
@endphp

<div class="ops-dashboard-detail">
    <x-filament::modal
        :id="$this->modalId()"
        width="7xl"
        :heading="$title ?? 'Detalle'"
        :description="$title ? number_format($total, 0, ',', '.').' '.$noun.' · '.$periodLabel.($total > count($rows) ? ' · se muestran los '.count($rows).' más recientes' : '') : null"
        :close-by-clicking-away="true"
    >
        <div wire:loading.flex wire:target="openDetail" class="items-center justify-center gap-2 py-16 text-sm text-gray-500 dark:text-gray-400">
            <x-filament::loading-indicator class="size-5" />
            Cargando {{ $noun }}…
        </div>

        <div wire:loading.remove wire:target="openDetail">
            @if ($rows === [])
                <div class="flex flex-col items-center justify-center gap-2 py-16 text-center">
                    <x-filament::icon icon="heroicon-o-magnifying-glass" class="size-8 text-gray-300 dark:text-gray-600" />
                    <p class="text-sm text-gray-500 dark:text-gray-400">No hay {{ $noun }} para este corte en el período.</p>
                </div>
            @else
                <div class="max-h-[65vh] overflow-auto rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                    <table class="w-full min-w-[900px] text-start text-sm">
                        <thead class="sticky top-0 z-10 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                            <tr>
                                @foreach ($columns as $column)
                                    <th class="px-3 py-2 text-start font-medium">{{ $column['label'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-950/5 dark:divide-white/5">
                            @foreach ($rows as $row)
                                <tr wire:key="ops-detail-{{ $row['id'] }}" class="transition hover:bg-gray-50 dark:hover:bg-white/5">
                                    @foreach ($columns as $column)
                                        @php($key = $column['key'])
                                        @switch($key)
                                            @case('date')
                                                <td class="whitespace-nowrap px-3 py-2 tabular-nums text-gray-700 dark:text-gray-200">
                                                    {{ $row['date'] ?? '—' }}
                                                    @if (! empty($row['time']))
                                                        <span class="text-xs text-gray-400">{{ $row['time'] }}</span>
                                                    @endif
                                                </td>
                                                @break
                                            @case('case_code')
                                                @php($url = $canOpenCases ? $this->caseUrl($row['case_id'] ?? null) : null)
                                                <td class="whitespace-nowrap px-3 py-2">
                                                    @if ($url)
                                                        <a href="{{ $url }}" wire:navigate class="font-medium text-primary-600 hover:underline dark:text-primary-400">{{ $row['case_code'] ?? '#'.$row['case_id'] }}</a>
                                                    @else
                                                        <span class="text-gray-700 dark:text-gray-200">{{ $row['case_code'] ?? '—' }}</span>
                                                    @endif
                                                </td>
                                                @break
                                            @case('status')
                                                <td class="whitespace-nowrap px-3 py-2">
                                                    <span class="inline-flex items-center gap-1.5 text-gray-700 dark:text-gray-200">
                                                        <span class="size-2 rounded-full" style="background: var(--ops-{{ OperationsDashboardChartJs::statusRole((string) ($row['status'] ?? '')) }})"></span>
                                                        {{ $row['status'] ?? 'Sin estatus' }}
                                                    </span>
                                                </td>
                                                @break
                                            @case('patient')
                                                <td class="max-w-[14rem] truncate px-3 py-2 text-gray-950 dark:text-white" title="{{ $row['patient'] ?? '' }}">{{ $row['patient'] ?? '—' }}</td>
                                                @break
                                            @default
                                                @php($cell = $row[$key] ?? null)
                                                <td class="max-w-[16rem] truncate px-3 py-2 text-gray-600 dark:text-gray-300" title="{{ is_scalar($cell) ? $cell : '' }}">{{ filled($cell) ? $cell : '—' }}</td>
                                        @endswitch
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </x-filament::modal>
</div>
