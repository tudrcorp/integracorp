@php
    $open = (bool) ($open ?? false);
    $entries = $entries ?? [];
    $sectionKey = \Illuminate\Support\Str::slug((string) $title);
@endphp

@if (count($entries) > 0)
    <x-operations.bitacora-accordion :title="$title" :count="count($entries)" :open="$open">
        <div class="divide-y divide-slate-100 border-t border-slate-100 dark:divide-white/10 dark:border-white/10">
            @foreach ($entries as $index => $entry)
                @php
                    $filled = collect($entry ?? [])
                        ->filter(function (mixed $value): bool {
                            if (is_bool($value) || is_array($value)) {
                                return false;
                            }

                            $text = trim((string) $value);

                            return $text !== '' && $text !== '—';
                        });
                    $summary = $filled->take(3)->implode(' · ');
                @endphp
                <article
                    x-data="{ open: false }"
                    class="px-4 py-2.5"
                    wire:key="bitacora-{{ $sectionKey }}-{{ $index }}"
                >
                    <button
                        type="button"
                        @click="open = ! open"
                        class="flex w-full items-start justify-between gap-3 text-left"
                    >
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium text-slate-800 dark:text-slate-100">
                                {{ $summary !== '' ? $summary : $title.' '.($index + 1) }}
                            </span>
                        </span>
                        <x-filament::icon
                            icon="heroicon-m-chevron-down"
                            class="mt-0.5 size-4 shrink-0 text-slate-400 transition-transform"
                            x-bind:class="open && 'rotate-180'"
                        />
                    </button>
                    <dl x-show="open" x-collapse class="mt-2 space-y-1.5" style="display: none;">
                        @foreach ($filled as $label => $value)
                            <div class="grid gap-1 sm:grid-cols-3">
                                <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</dt>
                                <dd class="sm:col-span-2 whitespace-pre-wrap text-sm text-slate-800 dark:text-slate-100">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </article>
            @endforeach
        </div>
    </x-operations.bitacora-accordion>
@endif
