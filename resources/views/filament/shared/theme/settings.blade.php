@php
    use App\Support\Theme\IntegracorpTheme as Theme;

    $themes = [Theme::ORIGINAL, Theme::BRAND];
@endphp

<x-filament-panels::page>
    <link rel="stylesheet" href="{{ $fontsUrl }}">

    @if ($preview !== null)
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-300/80 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900 dark:border-amber-400/30 dark:bg-amber-500/15 dark:text-amber-100">
            <span>Estás en vista previa del {{ Theme::label($preview) }}. Solo tú la ves. Recorre los paneles y vuelve aquí.</span>
            <x-filament::button color="gray" size="sm" wire:click="stopPreview" wire:loading.attr="disabled">Salir de la vista previa</x-filament::button>
        </div>
    @endif

    <div class="grid gap-4 xl:grid-cols-2">
        @foreach ($themes as $theme)
            <x-filament::section wire:key="theme-{{ $theme }}">
                <x-slot name="heading">{{ Theme::label($theme) }}</x-slot>
                <x-slot name="description">
                    {{ $theme === Theme::BRAND ? 'Manual de marca Integracorp v1.0, para los siete paneles internos.' : 'El aspecto de siempre: cada panel con su propio color.' }}
                </x-slot>
                <x-slot name="afterHeader">
                    @if ($active === $theme)
                        <x-filament::badge color="success" icon="heroicon-m-check-circle">Activo para todos</x-filament::badge>
                    @endif
                </x-slot>

                <div class="space-y-5">
                    <div>
                        <p class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Colores</p>
                        <ul class="space-y-2">
                            @foreach ($theme === Theme::BRAND ? $brandColors : array_map(fn (array $panel): array => [$panel[0], $panel[1], 'Color principal del panel'], $originalPanels) as [$name, $hex, $use])
                                <li class="flex items-center gap-3 text-sm">
                                    <span class="size-8 shrink-0 rounded-lg border border-gray-200 dark:border-white/10" style="background: {{ $hex }}"></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="font-semibold text-gray-950 dark:text-white">{{ $name }}</span>
                                        <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $use }}</span>
                                    </span>
                                    <code class="shrink-0 text-xs text-gray-500 dark:text-gray-400">{{ strtoupper($hex) }}</code>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div>
                        <p class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Tipos de letra</p>
                        @if ($theme === Theme::BRAND)
                            <p class="text-3xl text-gray-950 dark:text-white" style="font-family: 'Cormorant Garamond', Georgia, serif; font-weight: 300">Cormorant Garamond</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Títulos, cifras y saludos. En Telemedicina, solo títulos.</p>
                            <p class="mt-3 text-lg text-gray-950 dark:text-white" style="font-family: 'Manrope', sans-serif">Manrope</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Texto, menús, formularios y etiquetas.</p>
                        @else
                            <p class="text-lg text-gray-950 dark:text-white" style="font-family: 'Inter Variable', Inter, ui-sans-serif, system-ui, sans-serif">Inter</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Letra predeterminada de todos los paneles, en texto y títulos.</p>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-2">
                        @if ($preview === $theme)
                            <x-filament::button color="gray" wire:click="stopPreview" wire:loading.attr="disabled">Salir de la vista previa</x-filament::button>
                        @else
                            <x-filament::button color="gray" icon="heroicon-m-eye" wire:click="previewTheme('{{ $theme }}')" wire:loading.attr="disabled">Vista previa solo para mí</x-filament::button>
                        @endif

                        @if ($active !== $theme)
                            <x-filament::button wire:click="mountAction('applyTheme', { theme: '{{ $theme }}' })" wire:loading.attr="disabled">Aplicar a todos</x-filament::button>
                        @endif
                    </div>
                </div>
            </x-filament::section>
        @endforeach
    </div>

    <x-filament::section heading="Historial de cambios">
        @if ($history->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">Todavía nadie ha cambiado el tema. Rige el tema original.</p>
        @else
            <ul class="divide-y divide-gray-200 text-sm dark:divide-white/10">
                @foreach ($history as $change)
                    <li wire:key="change-{{ $change->id }}" class="flex flex-wrap justify-between gap-2 py-2">
                        <span class="text-gray-950 dark:text-white">{{ Theme::label($change->previous_theme) }} → {{ Theme::label($change->theme) }}</span>
                        <span class="text-gray-500 dark:text-gray-400">{{ $change->changed_by_name ?? 'Sistema' }} · {{ $change->created_at?->format('d/m/Y h:i a') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-panels::page>
