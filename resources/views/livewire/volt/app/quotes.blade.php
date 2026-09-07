<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Storefront\StorefrontAuth;
use App\Support\Storefront\StorefrontQuotesIndex;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('components.layouts.storefront')] #[Title('Mis cotizaciones')] class extends Component
{
    public string $search = '';

    public string $status = 'all';

    public int $page = 1;

    public function mount(): void
    {
        $user = StorefrontAuth::user();

        if (! $user instanceof User) {
            $this->redirect(route('storefront.login'), navigate: true);
        }
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    public function setStatus(string $status): void
    {
        $this->status = StorefrontQuotesIndex::normalizeStatus($status);
        $this->page = 1;
    }

    public function previousPage(): void
    {
        $this->page = max(1, $this->page - 1);
    }

    public function nextPage(): void
    {
        if ($this->quotes()->hasMorePages()) {
            $this->page++;
        }
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->status = 'all';
        $this->page = 1;
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function quotes(): LengthAwarePaginator
    {
        $user = StorefrontAuth::user();

        if (! $user instanceof User) {
            return new Paginator([], 0, StorefrontQuotesIndex::PER_PAGE, $this->page);
        }

        return StorefrontQuotesIndex::paginate($user, $this->search, $this->page, $this->status);
    }
}; ?>

<div class="sf-quotes">
    <section class="sf-hero sf-hero--compact">
        <p class="sf-kicker">Tu historial</p>
        <h1 class="sf-title">Mis cotizaciones</h1>
        <p class="sf-lead">Toca una cotización para pagar o cargar el comprobante. También puedes abrir la propuesta o el PDF.</p>
    </section>

    <div class="sf-quotes__toolbar sf-glass">
        <label class="sf-quotes__search" for="sf-quotes-search">
            <span class="sf-quotes__search-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="6.25" />
                    <path d="m16.2 16.2 3.55 3.55" />
                </svg>
            </span>
            <input
                id="sf-quotes-search"
                type="search"
                enterkeyhint="search"
                autocomplete="off"
                autocapitalize="none"
                spellcheck="false"
                placeholder="Código, nombre, correo o teléfono"
                wire:model.live.debounce.300ms="search"
            >
        </label>

        <div class="sf-quotes__filters" role="tablist" aria-label="Filtrar por estado">
            <button type="button" class="sf-quotes__filter {{ $status === 'all' ? 'is-on' : '' }}" wire:click="setStatus('all')" role="tab" aria-selected="{{ $status === 'all' ? 'true' : 'false' }}">Todas</button>
            <button type="button" class="sf-quotes__filter {{ $status === 'ready' ? 'is-on' : '' }}" wire:click="setStatus('ready')" role="tab" aria-selected="{{ $status === 'ready' ? 'true' : 'false' }}">Listas</button>
            <button type="button" class="sf-quotes__filter {{ $status === 'closed' ? 'is-on' : '' }}" wire:click="setStatus('closed')" role="tab" aria-selected="{{ $status === 'closed' ? 'true' : 'false' }}">Cerradas</button>
        </div>
    </div>

    @php
        $quotes = $this->quotes();
        $searching = StorefrontQuotesIndex::normalizeSearch($this->search) !== '' || $this->status !== 'all';
    @endphp

    <div class="sf-quotes__list" wire:loading.class="is-busy" wire:target="search, setStatus, previousPage, nextPage, clearFilters">
        @forelse ($quotes as $quote)
            <article class="sf-quote-card sf-glass" wire:key="sf-quote-{{ $quote['id'] }}">
                <a href="{{ $quote['pay_url'] }}" wire:navigate class="sf-quote-card__hit">
                    <div class="sf-quote-card__top">
                        <p class="sf-quote-card__code">{{ $quote['code'] }}</p>
                        <span class="sf-quote-card__status is-{{ $quote['status_tone'] }}">{{ $quote['status'] }}</span>
                    </div>
                    <h2 class="sf-quote-card__client">{{ $quote['client'] !== '' ? $quote['client'] : 'Sin nombre' }}</h2>
                    @include('storefront.partials.quote-plan-row', ['quote' => $quote])
                    @if ($quote['has_receipt'])
                        <p class="sf-quote-card__pill">Comprobante enviado</p>
                    @endif
                </a>
                <div class="sf-quote-card__actions">
                    <a href="{{ $quote['url'] }}" wire:navigate class="sf-btn">Ver propuesta</a>
                    <a href="{{ $quote['pdf_url'] }}" class="sf-btn sf-btn-ghost" target="_blank" rel="noopener noreferrer">PDF</a>
                </div>
            </article>
        @empty
            <div class="sf-quotes__empty sf-glass">
                @if ($searching)
                    <p class="sf-quotes__empty-title">No hay coincidencias</p>
                    <p class="sf-quotes__empty-copy">Prueba con el código COT-IND, el nombre del titular o el teléfono.</p>
                    <button type="button" class="sf-btn sf-btn-ghost" wire:click="clearFilters">Limpiar búsqueda</button>
                @else
                    <p class="sf-quotes__empty-title">Aún no tienes cotizaciones</p>
                    <p class="sf-quotes__empty-copy">Cuando cotices un plan desde la app, aparecerán aquí para reabrirlas cuando las necesites.</p>
                    <a href="{{ route('storefront.home') }}" wire:navigate class="sf-btn">Cotizar un plan</a>
                @endif
            </div>
        @endforelse
    </div>

    @if ($quotes->total() > 0)
        <div class="sf-quotes__pager">
            <p class="sf-quotes__count">
                {{ $quotes->firstItem() }}–{{ $quotes->lastItem() }} de {{ $quotes->total() }}
            </p>
            @if ($quotes->hasPages())
                <div class="sf-quotes__pager-btns">
                    <button type="button" class="sf-btn sf-btn-ghost" wire:click="previousPage" @disabled($quotes->onFirstPage())>Anterior</button>
                    <button type="button" class="sf-btn sf-btn-ghost" wire:click="nextPage" @disabled(! $quotes->hasMorePages())>Siguiente</button>
                </div>
            @endif
        </div>
    @endif
</div>
