<?php

declare(strict_types=1);

use App\Support\Storefront\StorefrontDownloadZoneCatalog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.storefront')] #[Title('Zona de Descarga')] class extends Component
{
    /**
     * @return list<array{id: int, label: string, count: int, image_url: string|null, url: string}>
     */
    public function containers(): array
    {
        return StorefrontDownloadZoneCatalog::containers();
    }
}; ?>

<div class="sf-downloads">
    <section class="sf-hero sf-hero--compact">
        <p class="sf-kicker">Documentos</p>
        <h1 class="sf-title">Zona de Descarga</h1>
        <p class="sf-lead">Toca una carpeta para ver el material. Desliza, da doble toque si te gusta y descárgalo al instante.</p>
    </section>

    @php
        $containers = $this->containers();
    @endphp

    @if ($containers === [])
        <div class="sf-quotes__empty sf-glass">
            <p class="sf-quotes__empty-title">Aún no hay carpetas</p>
            <p class="sf-quotes__empty-copy">Cuando el equipo publique documentos, aparecerán aquí listas para abrir.</p>
            <a href="{{ route('storefront.home') }}" wire:navigate class="sf-btn">Volver a planes</a>
        </div>
    @else
        <div class="sf-albums">
            @foreach ($containers as $container)
                <a
                    href="{{ $container['url'] }}"
                    wire:navigate
                    wire:key="sf-album-{{ $container['id'] }}"
                    class="sf-album"
                    x-on:pointerenter="window.__sfPrefetchPlan && window.__sfPrefetchPlan(@js($container['url']), @js($container['image_url']))"
                    x-on:pointerdown="window.__sfPrefetchPlan && window.__sfPrefetchPlan(@js($container['url']), @js($container['image_url']))"
                >
                    <span class="sf-album__media" aria-hidden="true">
                        <span class="sf-album__placeholder">
                            @include('storefront.partials.nav-icon', ['name' => 'downloads'])
                        </span>
                        @if (($container['image_url'] ?? null) !== null)
                            <img
                                src="{{ $container['image_url'] }}"
                                alt=""
                                class="sf-album__photo"
                                width="320"
                                height="420"
                                loading="{{ $loop->index < 4 ? 'eager' : 'lazy' }}"
                                decoding="async"
                                fetchpriority="{{ $loop->first ? 'high' : 'auto' }}"
                                onerror="this.hidden = true"
                            >
                        @endif
                        <span class="sf-album__shade"></span>
                    </span>
                    <span class="sf-album__body">
                        <span class="sf-album__count">{{ $container['count'] === 1 ? '1 archivo' : $container['count'].' archivos' }}</span>
                        <span class="sf-album__title">{{ $container['label'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</div>
