{{-- Paginación de una lista del Centro de colas. Espera: $pager (page, pages, total, from, to) y $list (nombre de la lista). --}}
@if ($pager['total'] > 0)
    <div class="lqc-pager">
        <span class="lqc-muted">Mostrando {{ $pager['from'] }}–{{ $pager['to'] }} de {{ $pager['total'] }}</span>
        @if ($pager['pages'] > 1)
            <div class="lqc-pages">
                <button type="button" class="lqc-page" wire:click="goToPage('{{ $list }}', {{ $pager['page'] - 1 }})" @disabled($pager['page'] <= 1) aria-label="Página anterior">‹</button>
                @foreach (range(1, $pager['pages']) as $number)
                    @if ($number === 1 || $number === $pager['pages'] || abs($number - $pager['page']) <= 1)
                        <button type="button" class="lqc-page {{ $number === $pager['page'] ? 'on' : '' }}" wire:click="goToPage('{{ $list }}', {{ $number }})" wire:key="page-{{ $list }}-{{ $number }}">{{ $number }}</button>
                    @elseif (abs($number - $pager['page']) === 2)
                        <span class="lqc-muted" wire:key="gap-{{ $list }}-{{ $number }}">…</span>
                    @endif
                @endforeach
                <button type="button" class="lqc-page" wire:click="goToPage('{{ $list }}', {{ $pager['page'] + 1 }})" @disabled($pager['page'] >= $pager['pages']) aria-label="Página siguiente">›</button>
            </div>
        @endif
    </div>
@endif
