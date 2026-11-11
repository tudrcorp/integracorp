@php
    $summary = $this->summary();
    $statusTone = match (strtoupper($summary['status'])) {
        'ACTIVA', 'ACTIVO' => 'bg-emerald-50 text-emerald-800 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-400/30',
        'EXCLUIDO', 'ANULADA', 'INACTIVA', 'INACTIVO' => 'bg-rose-50 text-rose-800 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-200 dark:ring-rose-400/30',
        default => 'bg-amber-50 text-amber-900 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-400/30',
    };
@endphp

<x-filament-panels::page>
    <div class="space-y-4">
        <section class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="mb-3 flex items-center gap-2">
                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusTone }}">{{ $summary['status'] ?: 'SIN ESTATUS' }}</span>
                <span class="text-xs text-gray-500 dark:text-gray-400">Datos principales de la afiliación</span>
            </div>
            <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-5">
                @foreach ($summary['items'] as $item)
                    <div class="min-w-0">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $item['label'] }}</dt>
                        <dd class="truncate text-sm font-semibold text-gray-950 dark:text-white" title="{{ $item['value'] }}">{{ $item['value'] }}</dd>
                        @if (filled($item['hint'] ?? null))
                            <dd class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $item['hint'] }}</dd>
                        @endif
                    </div>
                @endforeach
            </dl>
            @if ($summary['warning'])
                <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-400/30">{{ $summary['warning'] }}</p>
            @endif
        </section>

        @livewire($this->relationManager(), [
            'ownerRecord' => $this->getRecord(),
            'pageClass' => $this->relationManagerPageClass(),
            'lazy' => true,
        ], key('affiliates-'.$this->getRecord()->getKey()))
    </div>
</x-filament-panels::page>
