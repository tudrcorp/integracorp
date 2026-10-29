@php
    /** @var array<int, array<string, mixed>> $cards */
    /** @var list<int> $selected */
    /** @var bool $limitReached */
    $iqcSelected = array_flip($selected ?? []);
@endphp

@once
    <style>
        .iqc { display: flex; flex-wrap: wrap; gap: 0.875rem; }
        .iqc-card { position: relative; width: 10.5rem; aspect-ratio: 3 / 4; flex: 0 0 auto; overflow: hidden; border-radius: 1rem; border: 0; padding: 0; text-align: start; cursor: pointer; color: #fff; isolation: isolate; box-shadow: 0 6px 18px -8px rgb(15 23 42 / 0.45); transition: transform 160ms ease, box-shadow 160ms ease; scroll-snap-align: start; }
        .iqc-card:hover { transform: translateY(-3px); box-shadow: 0 14px 28px -12px rgb(15 23 42 / 0.55); }
        .iqc-card:focus-visible { outline: 3px solid var(--primary-500, #3b82f6); outline-offset: 3px; }
        .iqc-card:disabled { cursor: not-allowed; opacity: 0.55; transform: none; }
        .iqc-card img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; z-index: -2; }
        .iqc-card::after { content: ''; position: absolute; inset: 0; z-index: -1; background: linear-gradient(180deg, rgb(0 0 0 / 0.05) 30%, rgb(0 0 0 / 0.78) 100%); }
        .iqc-body { position: absolute; inset: auto 0 0 0; display: flex; flex-direction: column; gap: 0.25rem; padding: 0.75rem; }
        .iqc-eyebrow { font-size: 0.625rem; line-height: 1.25; font-weight: 500; opacity: 0.9; }
        .iqc-name { font-size: 0.875rem; line-height: 1.15; font-weight: 800; text-transform: uppercase; letter-spacing: 0.01em; }
        .iqc-from { font-size: 0.6875rem; font-weight: 600; opacity: 0.95; }
        .iqc-check { position: absolute; top: 0.5rem; right: 0.5rem; display: none; width: 1.75rem; height: 1.75rem; align-items: center; justify-content: center; border-radius: 9999px; background: var(--primary-500, #3b82f6); box-shadow: 0 2px 6px rgb(0 0 0 / 0.35); }
        .iqc-card.is-selected { box-shadow: 0 0 0 3px var(--primary-500, #3b82f6), 0 14px 28px -12px rgb(15 23 42 / 0.55); }
        .iqc-card.is-selected .iqc-check { display: inline-flex; }
        .iqc-hint { margin-top: 0.625rem; font-size: 0.75rem; color: rgb(107 114 128); }
        .dark .iqc-hint { color: rgb(156 163 175); }
        .iqc-empty { font-size: 0.875rem; color: rgb(107 114 128); }
        @media (max-width: 640px) {
            .iqc { flex-wrap: nowrap; overflow-x: auto; scroll-snap-type: x mandatory; padding-bottom: 0.5rem; -webkit-overflow-scrolling: touch; }
            .iqc-card { width: 9rem; }
        }
        @media (prefers-reduced-motion: reduce) { .iqc-card { transition: none; } .iqc-card:hover { transform: none; } }
    </style>
@endonce

@if ($cards === [])
    <p class="iqc-empty">No hay upgrades activos. Se administran en Configuración → Upgrades.</p>
@else
    <div class="iqc" role="group" aria-label="Upgrades disponibles">
        @foreach ($cards as $iqcCard)
            @php
                $iqcIsSelected = isset($iqcSelected[$iqcCard['id']]);
            @endphp
            <button
                type="button"
                wire:key="upgrade-card-{{ $iqcCard['id'] }}"
                wire:click="toggleQuoteUpgrade({{ (int) $iqcCard['id'] }})"
                wire:loading.attr="disabled"
                wire:target="toggleQuoteUpgrade"
                @disabled(! $iqcIsSelected && $limitReached)
                aria-pressed="{{ $iqcIsSelected ? 'true' : 'false' }}"
                title="{{ $iqcIsSelected ? 'Quitar' : 'Agregar' }} {{ $iqcCard['name'] }}"
                @class(['iqc-card', 'is-selected' => $iqcIsSelected])
                @if ($iqcCard['image_url'] === null)
                    style="background: linear-gradient(160deg, {{ $iqcCard['gradient'][0] }}, {{ $iqcCard['gradient'][1] }});"
                @endif
            >
                @if ($iqcCard['image_url'] !== null)
                    <img src="{{ $iqcCard['image_url'] }}" alt="" loading="lazy" decoding="async">
                @endif

                <span class="iqc-check" aria-hidden="true">
                    <x-filament::icon icon="heroicon-m-check" class="size-4 text-white" />
                </span>

                <span class="iqc-body">
                    <span class="iqc-eyebrow">{{ $iqcCard['eyebrow'] }}</span>
                    <span class="iqc-name">{{ $iqcCard['name'] }}</span>
                    <span class="iqc-from">{{ $iqcCard['from_label'] }}</span>
                </span>
            </button>
        @endforeach
    </div>

    <p class="iqc-hint">
        Toque una tarjeta para agregar el upgrade; tóquela de nuevo para quitarlo.
        @if ($limitReached)
            Llegó al máximo de upgrades por cotización.
        @endif
    </p>
@endif
