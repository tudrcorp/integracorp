<div class="space-y-4">
    <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="text-sm text-gray-600 dark:text-gray-300">
            Haz click en otro mes del gráfico para cambiar el detalle.
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Colaborador</th>
                        @foreach ($statuses as $status)
                            <th class="px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-200">{{ $status }}</th>
                        @endforeach
                        <th class="px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-200">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @forelse ($rows as $row)
                        <tr class="hover:bg-gray-50/60 dark:hover:bg-white/5">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                {{ $row['colaborador'] }}
                            </td>
                            @foreach ($statuses as $status)
                                <td class="px-4 py-3 text-center text-gray-700 dark:text-gray-200">
                                    {{ $row['totals'][$status] ?? 0 }}
                                </td>
                            @endforeach
                            <td class="px-4 py-3 text-center font-semibold text-gray-900 dark:text-white">
                                {{ $row['total'] ?? 0 }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-4 py-4 text-gray-600 dark:text-gray-300" colspan="{{ count($statuses) + 2 }}">
                                No hay tickets para este mes.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

