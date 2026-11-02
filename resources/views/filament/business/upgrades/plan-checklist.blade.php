@php
    /** @var list<array{label: string, detail: string, state: string}> $items */
    /** @var array{code: string, status: string, url: string}|null $linkedPlan */
    $missing = count(array_filter($items, static fn (array $item): bool => $item['state'] === 'missing'));
@endphp

<div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-white/10 dark:bg-white/5">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm font-semibold text-gray-950 dark:text-white">Requisitos para crear el plan</p>

        @if ($linkedPlan !== null)
            <x-filament::badge :color="$linkedPlan['status'] === 'ACTIVO' ? 'success' : 'warning'">
                {{ $linkedPlan['code'] }} · {{ $linkedPlan['status'] === 'ACTIVO' ? 'Se puede cotizar' : 'Aún no se puede cotizar' }}
            </x-filament::badge>
        @elseif ($missing > 0)
            <x-filament::badge color="warning">{{ $missing === 1 ? 'Falta 1' : 'Faltan '.$missing }}</x-filament::badge>
        @else
            <x-filament::badge color="success">Listo para guardar</x-filament::badge>
        @endif
    </div>

    <ul class="mt-3 grid gap-3 md:grid-cols-2">
        @foreach ($items as $item)
            <li class="flex items-start gap-2" wire:key="upgrade-plan-check-{{ $loop->index }}">
                <x-filament::icon
                    :icon="match ($item['state']) {
                        'done' => 'heroicon-s-check-circle',
                        'missing' => 'heroicon-s-exclamation-circle',
                        default => 'heroicon-s-clock',
                    }"
                    @class([
                        'mt-0.5 size-5 shrink-0',
                        'text-success-600 dark:text-success-400' => $item['state'] === 'done',
                        'text-warning-600 dark:text-warning-400' => $item['state'] === 'missing',
                        'text-gray-400 dark:text-gray-500' => $item['state'] === 'later',
                    ])
                />
                <span>
                    <span class="block text-sm font-medium text-gray-950 dark:text-white">{{ $item['label'] }}</span>
                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $item['detail'] }}</span>
                </span>
            </li>
        @endforeach
    </ul>

    @if ($linkedPlan !== null)
        <div class="mt-3">
            <x-filament::link :href="$linkedPlan['url']" size="sm" icon="heroicon-m-arrow-right" icon-position="after">
                Ir al uso clínico del plan
            </x-filament::link>
        </div>
    @endif
</div>
