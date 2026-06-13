@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\OperationQuoteGenerator> $quotes */
    use App\Support\Operations\CoordinationServiceQuoteManager;
@endphp

<div class="fi-coordination-manage-quotes-checkbox-list space-y-3">
    @if ($quotes->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">No hay cotizaciones pendientes por aprobar.</p>
    @else
        <div class="flex flex-wrap items-center justify-end gap-2">
            <button
                type="button"
                wire:click="selectAllPendingQuotes"
                class="text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400"
            >
                Seleccionar todos
            </button>
            <span class="text-gray-300 dark:text-white/20">·</span>
            <button
                type="button"
                wire:click="deselectAllPendingQuotes"
                class="text-xs font-semibold text-gray-500 hover:underline dark:text-gray-400"
            >
                Limpiar selección
            </button>
        </div>

        <div class="space-y-3">
            @foreach ($quotes as $quote)
                @php
                    $isSelected = in_array($quote->id, $selectedIds ?? [], true);
                @endphp
                <div @class([
                    'fi-fo-checkbox-list-option flex flex-wrap items-center justify-between gap-3 rounded-2xl border p-3 shadow-[0_1px_2px_rgb(0_0_0/0.04)] transition-all duration-200',
                    'border-primary-400/80 bg-gradient-to-br from-primary-50/90 to-white ring-2 ring-primary-500/15 dark:border-primary-500/40 dark:from-primary-950/30 dark:to-zinc-900/90 dark:ring-primary-400/20' => $isSelected,
                    'border-zinc-200/90 bg-gradient-to-br from-white to-zinc-50/80 hover:border-primary-300/70 hover:shadow-md dark:border-white/10 dark:from-zinc-900/90 dark:to-zinc-950/90 dark:hover:border-primary-500/35' => ! $isSelected,
                ])>
                    <label class="flex min-w-0 flex-1 cursor-pointer items-start gap-3">
                        <input
                            type="checkbox"
                            value="{{ $quote->id }}"
                            wire:model.live="data.selected_pending_quote_ids"
                            class="mt-1 rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-white/20 dark:bg-white/5"
                        />
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-gray-900 dark:text-white">
                                #{{ $quote->id }} · {{ $quote->type_service }} · {{ CoordinationServiceQuoteManager::formatManageQuoteAmountPreview((float) $quote->total) }}
                            </span>
                            <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                Creada {{ optional($quote->created_at)->format('d/m/Y H:i') ?? '—' }}
                                @if (filled($quote->supplier?->name))
                                    · {{ $quote->supplier->name }}
                                @endif
                            </span>
                        </span>
                    </label>
                    <button
                        type="button"
                        wire:click="mountAction('editPendingQuote', { quoteId: {{ $quote->id }} })"
                        wire:loading.attr="disabled"
                        wire:target="mountAction('editPendingQuote', { quoteId: {{ $quote->id }} })"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-full border-b-2 border-primary-600 bg-primary-500/15 px-3 py-1.5 text-xs font-semibold text-primary-700 transition hover:bg-primary-500/25 dark:border-primary-500 dark:bg-primary-500/25 dark:text-primary-300"
                    >
                        <x-filament::icon icon="heroicon-m-pencil-square" class="size-3.5" />
                        Editar
                    </button>
                </div>
            @endforeach
        </div>
    @endif
</div>
