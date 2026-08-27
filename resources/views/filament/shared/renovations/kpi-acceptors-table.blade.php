@php
    /** @var list<App\Support\Renovations\RenovationKpiAcceptorRow> $acceptors */
    /** @var string $unitLabel */
@endphp

<div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
    <table class="w-full text-sm">
        <caption class="sr-only">Renovaciones aceptadas por empleado en el mes</caption>
        <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
            <tr>
                <th class="px-4 py-2.5">Empleado</th>
                <th class="px-4 py-2.5 text-right">{{ $unitLabel }}</th>
                <th class="px-4 py-2.5 text-right">Prima retenida</th>
                <th class="px-4 py-2.5 text-right">Anticipación media</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
            @foreach ($acceptors as $row)
                <tr>
                    <td class="px-4 py-2.5 font-medium text-gray-950 dark:text-white">{{ $row->acceptedBy }}</td>
                    <td class="px-4 py-2.5 text-right tabular-nums text-gray-700 dark:text-gray-200">{{ number_format($row->acceptedCount, 0, ',', '.') }}</td>
                    <td class="px-4 py-2.5 text-right tabular-nums text-gray-700 dark:text-gray-200">US$ {{ number_format($row->retainedPremium, 2, ',', '.') }}</td>
                    <td class="px-4 py-2.5 text-right tabular-nums text-gray-700 dark:text-gray-200">
                        @if ($row->avgAnticipationDays === null)
                            —
                        @else
                            {{ (int) round($row->avgAnticipationDays) }} días
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
