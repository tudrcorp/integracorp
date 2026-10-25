@php
    /** @var array{current: int, events: \Illuminate\Support\Collection<int, \App\Models\TelemedicinePanelEvent>} $state */
    $state = $getState() ?? [];
    $current = (int) ($state['current'] ?? 0);
    $events = $state['events'] ?? collect();
@endphp

@if ($events->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">No hay otros eventos de este médico alrededor de este momento.</p>
@else
    <ol class="relative flex flex-col gap-1 border-s border-gray-200 ps-4 dark:border-white/10">
        @foreach ($events as $event)
            @php
                $isCurrent = (int) $event->getKey() === $current;
                $level = $event->levelEnum();
                $dot = match ($level?->value) {
                    'error' => 'bg-danger-500',
                    'warning' => 'bg-warning-500',
                    default => 'bg-gray-300 dark:bg-gray-600',
                };
            @endphp
            <li wire:key="tpe-seq-{{ $event->getKey() }}"
                @class([
                    'relative rounded-lg px-3 py-2',
                    'bg-primary-50 ring-1 ring-primary-300 dark:bg-primary-500/10 dark:ring-primary-500/40' => $isCurrent,
                ])>
                <span @class(['absolute -start-[1.4rem] top-3.5 size-2.5 rounded-full ring-2 ring-white dark:ring-gray-900', $dot])></span>
                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5">
                    <span class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $event->occurred_at?->format('H:i:s') }}</span>
                    @if ($isCurrent)
                        <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $event->title }}</span>
                    @else
                        <a href="{{ \App\Filament\Operations\Resources\TelemedicinePanelEvents\TelemedicinePanelEventResource::getUrl('view', ['record' => $event]) }}"
                           wire:navigate
                           @class([
                               'text-sm hover:underline',
                               'font-semibold text-danger-600 dark:text-danger-400' => $level?->value === 'error',
                               'font-semibold text-warning-600 dark:text-warning-400' => $level?->value === 'warning',
                               'text-gray-700 dark:text-gray-200' => $level?->value === 'info',
                           ])>{{ $event->title }}</a>
                    @endif
                    @if (filled($event->step))
                        <span class="text-xs text-gray-500 dark:text-gray-400">· Paso: {{ $event->step }}</span>
                    @endif
                </div>
                @if (filled($event->message) && ($isCurrent || $level?->value !== 'info'))
                    <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-300">{{ \Illuminate\Support\Str::limit((string) $event->message, 400) }}</p>
                @endif
            </li>
        @endforeach
    </ol>
@endif
