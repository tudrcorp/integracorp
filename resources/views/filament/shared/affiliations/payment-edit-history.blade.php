@php
    use App\Support\Affiliations\PendingPaymentEdition;

    /** @var \Illuminate\Support\Collection<int, \App\Models\PaymentEditLog> $logs */
@endphp

<div class="space-y-3">
    @forelse ($logs as $log)
        <div wire:key="payment-edit-log-{{ $log->id }}" class="rounded-lg bg-gray-50 px-3 py-3 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <p class="text-sm font-semibold text-gray-950 dark:text-white">
                    {{ $log->actor_name ?: 'Usuario #'.$log->actor_id }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ $log->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }} · Traza #{{ $log->id }}
                </p>
            </div>

            <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                <span class="font-medium">Motivo:</span> {{ $log->note }}
            </p>

            <table class="mt-2 w-full table-fixed text-left text-xs">
                <thead class="text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="w-1/3 py-1 font-semibold">Campo</th>
                        <th class="w-1/3 py-1 font-semibold">Antes</th>
                        <th class="w-1/3 py-1 font-semibold">Después</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-950/5 dark:divide-white/10">
                    @foreach ((array) $log->changes as $field => $change)
                        <tr wire:key="payment-edit-log-{{ $log->id }}-{{ $field }}">
                            <td class="py-1 pe-2 font-medium text-gray-700 dark:text-gray-300">{{ PendingPaymentEdition::FIELD_LABELS[$field] ?? $field }}</td>
                            <td class="break-words py-1 pe-2 text-rose-700 line-through decoration-rose-400/60 dark:text-rose-300">{{ PendingPaymentEdition::displayValue((string) $field, $change['old'] ?? null) }}</td>
                            <td class="break-words py-1 font-semibold text-emerald-700 dark:text-emerald-300">{{ PendingPaymentEdition::displayValue((string) $field, $change['new'] ?? null) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">Este pago no se ha editado.</p>
    @endforelse
</div>
