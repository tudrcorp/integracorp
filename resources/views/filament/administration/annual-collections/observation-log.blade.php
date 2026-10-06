@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\CollectionObservation> $observations */
@endphp

@if ($observations->isEmpty())
    <div class="rounded-xl border border-dashed border-gray-300 px-4 py-6 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
        Esta afiliación todavía no tiene observaciones de cobranza. La primera que guarde aparecerá aquí.
    </div>
@else
    <ol class="flex flex-col gap-3">
        @foreach ($observations as $entry)
            <li wire:key="collection-observation-{{ $entry->id }}" class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 dark:border-white/10 dark:bg-white/5">
                <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500 dark:text-gray-400">
                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $entry->created_by_name ?: 'Usuario no disponible' }}</span>
                    <span>{{ $entry->created_at?->format('d/m/Y h:i A') }}</span>
                </div>
                <p class="mt-2 whitespace-pre-line text-sm text-gray-900 dark:text-white">{{ $entry->observation }}</p>
                <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                    @if ($entry->due_date)
                        <span>Cuota con vencimiento {{ $entry->due_date->format('d/m/Y') }}</span>
                    @endif
                    @if ($entry->amount !== null)
                        <span>US$ {{ number_format((float) $entry->amount, 2, ',', '.') }}</span>
                    @endif
                    @if ((int) $entry->collection_id === $currentCollectionId)
                        <span class="font-medium text-primary-600 dark:text-primary-400">Cuota actual</span>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
@endif
