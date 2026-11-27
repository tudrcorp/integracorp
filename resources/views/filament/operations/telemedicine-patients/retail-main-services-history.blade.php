{{-- Historial de los servicios principales RETAIL: cada alta, reactivación o baja con su motivo. --}}
@php
    $colors = ['agregado' => 'success', 'reactivado' => 'info', 'quitado' => 'danger'];
@endphp
@if ($rows === [])
    <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">Todavía no hay cambios. Cuando alguien agregue o quite un servicio, aparecerá aquí con su motivo.</p>
@else
    <div class="overflow-x-auto rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
        <table style="min-width: 40rem;" class="w-full divide-y divide-gray-200 text-start text-sm dark:divide-white/5">
            <thead class="bg-gray-50 dark:bg-white/5">
                <tr>
                    <th class="px-3 py-2 text-start font-semibold text-gray-950 dark:text-white">Fecha</th>
                    <th class="px-3 py-2 text-start font-semibold text-gray-950 dark:text-white">Usuario</th>
                    <th class="px-3 py-2 text-start font-semibold text-gray-950 dark:text-white">Acción</th>
                    <th class="px-3 py-2 text-start font-semibold text-gray-950 dark:text-white">Servicio</th>
                    <th class="px-3 py-2 text-start font-semibold text-gray-950 dark:text-white">Motivo</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                @foreach ($rows as $row)
                    <tr class="align-top">
                        <td class="whitespace-nowrap px-3 py-2 text-gray-600 dark:text-gray-300">{{ $row['date'] }}</td>
                        <td class="px-3 py-2 text-gray-950 dark:text-white">{{ $row['user'] }}</td>
                        <td class="px-3 py-2">
                            <x-filament::badge :color="$colors[$row['action_key']] ?? 'gray'">{{ $row['action'] }}</x-filament::badge>
                        </td>
                        <td class="px-3 py-2 font-medium text-gray-950 dark:text-white">{{ $row['service'] }}</td>
                        <td class="px-3 py-2 text-gray-700 dark:text-gray-300" style="overflow-wrap: anywhere;">{{ $row['note'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
