@php
    $expanded = $this->sectionExpanded;
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $variant = $this->salesStatsPanelVariant();
    $pollingInterval = $this->getPollingInterval();
@endphp

<x-filament-widgets::widget
    :attributes="
        (new \Illuminate\View\ComponentAttributeBag)
            ->merge([
                'wire:poll.' . $pollingInterval => $pollingInterval ? true : null,
            ], escape: false)
            ->class([
                'fi-wi-stats-overview',
                'fi-admin-sales-stats-widget',
                'fi-admin-sales-stats-widget--' . $variant,
            ])
    "
>
    <div
        class="fi-admin-sales-stats-panel"
        data-expanded="{{ $expanded ? 'true' : 'false' }}"
        data-variant="{{ $variant }}"
        wire:key="sales-stats-panel-{{ class_basename($this) }}"
    >
        <button
            type="button"
            class="fi-admin-sales-stats-panel__trigger"
            wire:click="toggleSection"
            aria-expanded="{{ $expanded ? 'true' : 'false' }}"
        >
            <span class="fi-admin-sales-stats-panel__trigger-main">
                <span class="fi-admin-sales-stats-panel__icon" aria-hidden="true">
                    <x-filament::icon
                        :icon="$this->salesStatsPanelIcon()"
                        class="fi-admin-sales-stats-panel__icon-svg"
                    />
                </span>

                <span class="min-w-0 text-left">
                    <span class="fi-admin-sales-stats-panel__title">
                        {{ $heading }}
                    </span>
                    @if (filled($description))
                        <span class="fi-admin-sales-stats-panel__subtitle">
                            {{ $expanded ? $description : 'Colapsado · haz clic para ver las métricas' }}
                        </span>
                    @endif
                </span>
            </span>

            <span class="fi-admin-sales-stats-panel__trigger-meta">
                <span class="fi-admin-sales-stats-panel__state">
                    {{ $expanded ? 'Ocultar' : 'Mostrar' }}
                </span>
                <span class="fi-admin-sales-stats-panel__chevron" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4">
                        <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                    </svg>
                </span>
            </span>
        </button>

        @if ($expanded)
            <div
                class="fi-admin-sales-stats-panel__body"
                wire:key="sales-stats-body-{{ class_basename($this) }}-open"
            >
                {{ $this->content }}
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
