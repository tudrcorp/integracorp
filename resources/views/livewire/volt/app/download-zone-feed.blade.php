<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Storefront\StorefrontAuth;
use App\Support\Storefront\StorefrontDownloadZoneCatalog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.storefront')] #[Title('Zona de Descarga')] class extends Component
{
    public int $zone = 0;

    public string $folder = 'Carpeta';

    public function mount(int $zone): void
    {
        $container = StorefrontDownloadZoneCatalog::container($zone);

        if ($container === null) {
            $this->redirect(route('storefront.download-zone'), navigate: true);

            return;
        }

        $this->zone = $container['id'];
        $this->folder = $container['label'];
    }

    /**
     * @return list<array{id: int, description: string, zone: string, image_url: string|null, download_url: string, filename: string, liked: bool, likes: int}>
     */
    public function posts(): array
    {
        return StorefrontDownloadZoneCatalog::posts($this->zone, StorefrontAuth::user());
    }

    /**
     * @return array{id: int, liked: bool, likes: int}|null
     */
    public function toggleLike(int $id): ?array
    {
        $user = StorefrontAuth::user();

        if (! $user instanceof User) {
            return null;
        }

        $result = StorefrontDownloadZoneCatalog::toggleLike($user, $id);
        $this->skipRender();

        return $result;
    }
}; ?>

<div
    class="sf-reel"
    x-data="{
        downloadBusyId: 0,
        error: '',
        async downloadFile(id, url, filename) {
            if (! url || this.downloadBusyId) {
                return;
            }

            this.error = '';
            this.downloadBusyId = id;

            try {
                const response = await fetch(url, {
                    credentials: 'same-origin',
                    headers: { Accept: '*/*' },
                });

                if (! response.ok) {
                    throw new Error('unavailable');
                }

                const blob = await response.blob();
                const objectUrl = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = objectUrl;
                link.download = filename || 'documento';
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(objectUrl);
            } catch (error) {
                this.error = 'No pudimos descargar el documento. Inténtalo de nuevo.';
            } finally {
                this.downloadBusyId = 0;
            }
        },
    }"
>
    @php
        $posts = $this->posts();
        $total = count($posts);
    @endphp

    @if ($posts === [])
        <div class="sf-reel__empty sf-glass">
            <p class="sf-quotes__empty-title">Esta carpeta está vacía</p>
            <p class="sf-quotes__empty-copy">Vuelve y elige otra con archivos listos para descargar.</p>
            <a href="{{ route('storefront.download-zone') }}" wire:navigate class="sf-btn">Ver carpetas</a>
        </div>
    @else
        <p class="sf-error sf-reel__error" x-show="error !== ''" x-text="error" x-cloak></p>

        @foreach ($posts as $index => $post)
            <article
                class="sf-reel__post"
                wire:key="sf-reel-{{ $post['id'] }}"
                x-data="{
                    liked: @js((bool) $post['liked']),
                    likes: @js((int) $post['likes']),
                    burst: false,
                    busy: false,
                    lastTap: 0,
                    async like(fromDouble) {
                        if (fromDouble && this.liked) {
                            this.burst = true;
                            setTimeout(() => { this.burst = false }, 620);
                            return;
                        }

                        if (this.busy) {
                            return;
                        }

                        this.busy = true;

                        if (fromDouble && ! this.liked) {
                            this.burst = true;
                        }

                        const next = ! this.liked;
                        this.liked = next;
                        this.likes = Math.max(0, this.likes + (next ? 1 : -1));

                        try {
                            const result = await $wire.toggleLike({{ (int) $post['id'] }});

                            if (result && typeof result.liked === 'boolean') {
                                this.liked = result.liked;
                                this.likes = Number(result.likes ?? this.likes);
                            }
                        } catch (error) {
                            this.liked = ! next;
                            this.likes = Math.max(0, this.likes + (next ? -1 : 1));
                        } finally {
                            this.busy = false;
                            if (this.burst) {
                                setTimeout(() => { this.burst = false }, 620);
                            }
                        }
                    },
                    onTap() {
                        const now = Date.now();

                        if (now - this.lastTap < 280) {
                            this.lastTap = 0;
                            this.like(true);
                            return;
                        }

                        this.lastTap = now;
                    },
                }"
            >
                <div class="sf-reel__stage" x-on:click="onTap()">
                    <div class="sf-reel__fallback" aria-hidden="true">
                        @include('storefront.partials.nav-icon', ['name' => 'downloads'])
                    </div>
                    @if (($post['image_url'] ?? null) !== null)
                        <img
                            src="{{ $post['image_url'] }}"
                            alt="{{ $post['description'] }}"
                            class="sf-reel__photo"
                            width="780"
                            height="980"
                            loading="{{ $index < 2 ? 'eager' : 'lazy' }}"
                            decoding="async"
                            fetchpriority="{{ $index === 0 ? 'high' : 'auto' }}"
                            draggable="false"
                            onerror="this.hidden = true"
                        >
                    @endif

                    <span class="sf-reel__burst" x-bind:class="burst && 'is-on'" aria-hidden="true">♥</span>
                </div>

                <div class="sf-reel__chrome">
                    <p class="sf-reel__index">{{ $index + 1 }} / {{ $total }}</p>
                    <p class="sf-reel__kicker">{{ $folder }}</p>
                    <h2 class="sf-reel__title">{{ $post['description'] }}</h2>

                    <div class="sf-reel__actions">
                        <button
                            type="button"
                            class="sf-reel__like"
                            x-bind:class="liked && 'is-on'"
                            x-bind:aria-pressed="liked.toString()"
                            x-on:click.stop="like(false)"
                            aria-label="Me gusta"
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78L12 21.23l8.84-8.84a5.5 5.5 0 0 0 0-7.78Z" />
                            </svg>
                            <span x-text="likes"></span>
                        </button>

                        <button
                            type="button"
                            class="sf-btn sf-reel__download"
                            x-bind:disabled="downloadBusyId !== 0"
                            x-bind:class="downloadBusyId === {{ (int) $post['id'] }} && 'is-busy'"
                            x-bind:aria-busy="(downloadBusyId === {{ (int) $post['id'] }}).toString()"
                            x-on:click.stop="downloadFile({{ (int) $post['id'] }}, @js($post['download_url']), @js($post['filename']))"
                        >
                            <span x-show="downloadBusyId !== {{ (int) $post['id'] }}">Descargar</span>
                            <span x-show="downloadBusyId === {{ (int) $post['id'] }}" x-cloak>
                                <span class="sf-spinner" aria-hidden="true"></span>
                                Preparando…
                            </span>
                        </button>
                    </div>
                </div>
            </article>
        @endforeach
    @endif
</div>
