@php
    use Illuminate\Support\Str;

    $fullWidth = $fullWidth ?? false;
    $wrapClass = $fullWidth
        ? 'w-full rounded-2xl'
        : 'shrink-0 w-full min-w-0 max-w-[280px] sm:max-w-[320px] rounded-2xl';
    $tickerItems = $tickets->count() > 1 ? $tickets->concat($tickets) : $tickets;
@endphp
{{-- Un solo nodo raíz: Livewire + SPA evitan duplicar el bloque al actualizar el DOM --}}
<div class="w-full" wire:key="business-helpdesk-tickets-ticker-root">
    @if($tickets->isNotEmpty())
        <div
            x-data="{ paused: false }"
            x-on:mouseenter="paused = true"
            x-on:mouseleave="paused = false"
            class="fi-helpdesk-ticker-ios mt-3 tickets-ticker-wrap {{ $wrapClass }} overflow-hidden border border-slate-200/80 bg-gradient-to-r from-white/95 via-slate-50/90 to-white/95 shadow-[0_14px_36px_-16px_rgba(15,23,42,0.35)] ring-1 ring-black/5 dark:border-slate-700/80 dark:from-slate-900/95 dark:via-slate-950/90 dark:to-slate-900/95 dark:ring-white/10"
            role="region"
            aria-label="Tickets asignados"
        >
            <div class="{{ $fullWidth ? 'flex flex-col gap-2 px-2.5 py-2 sm:flex-row sm:items-center' : 'flex items-center gap-1.5 px-2 py-1.5' }}">
                <div class="shrink-0 flex items-center {{ $fullWidth ? 'gap-1.5 rounded-xl px-2.5 py-1.5' : 'gap-1 rounded-md px-1.5 py-0.5' }} border border-primary-200/65 bg-primary-500/10 dark:border-primary-500/30 dark:bg-primary-500/20">
                    <span class="inline-flex size-2 rounded-full bg-primary-500 shadow-[0_0_0_4px_rgba(59,130,246,0.18)]"></span>
                    <svg class="{{ $fullWidth ? 'size-3.5' : 'size-3' }} text-primary-600 dark:text-primary-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 0 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                    </svg>
                    <span class="{{ $fullWidth ? 'text-[10px]' : 'text-[9px]' }} font-bold uppercase tracking-wider text-primary-700 dark:text-primary-300">Tickets activos</span>
                    <span class="rounded-full border border-primary-300/50 bg-primary-500/20 dark:border-primary-300/20 dark:bg-primary-400/30 {{ $fullWidth ? 'px-2 py-0.5 text-[10px]' : 'px-1 py-0.5 text-[9px]' }} font-semibold tabular-nums text-primary-700 dark:text-primary-300">{{ $tickets->count() }}</span>
                </div>

                <div class="fi-helpdesk-ticker-ios-track flex-1 min-w-0 overflow-hidden relative rounded-xl border border-slate-200/70 bg-white/55 dark:border-slate-700/70 dark:bg-slate-900/45">
                    <div
                        class="absolute inset-y-0 left-0 {{ $fullWidth ? 'w-10 sm:w-14' : 'w-4 sm:w-6' }} bg-gradient-to-r from-white dark:from-slate-900/95 to-transparent z-10 pointer-events-none"
                        aria-hidden="true"
                    ></div>
                    <div
                        class="absolute inset-y-0 right-0 {{ $fullWidth ? 'w-10 sm:w-14' : 'w-4 sm:w-6' }} bg-gradient-to-l from-white dark:from-slate-900/95 to-transparent z-10 pointer-events-none"
                        aria-hidden="true"
                    ></div>
                    <div class="overflow-hidden pl-10 pr-2 sm:pl-6">
                        <div
                            class="fi-helpdesk-ticker-ios-rail inline-flex items-center {{ $fullWidth ? 'gap-2 py-1' : 'gap-1 py-0.5' }} will-change-transform"
                            :style="paused ? {} : { animation: 'helpdesk-ticker-scroll 38s linear infinite' }"
                        >
                            @foreach($tickerItems as $ticket)
                                @php
                                    $descLen = $fullWidth ? 80 : 50;
                                    $desc = Str::limit(strip_tags($ticket->description ?? ''), $descLen);
                                    $priority = $ticket->priority ?? 'MEDIA';
                                    $priorityBadgeClasses = match($priority) {
                                        'BAJA' => 'bg-emerald-200/80 text-emerald-900 dark:bg-emerald-400/40 dark:text-emerald-100',
                                        'ALTA' => 'bg-rose-200/80 text-rose-900 dark:bg-rose-400/40 dark:text-rose-100',
                                        default => 'bg-amber-200/80 text-amber-900 dark:bg-amber-400/40 dark:text-amber-100',
                                    };
                                    $chipBgClasses = match($priority) {
                                        'BAJA' => 'bg-emerald-50 dark:bg-emerald-950/50 border-emerald-200 dark:border-emerald-500/40 hover:border-emerald-400 dark:hover:border-emerald-400/60',
                                        'ALTA' => 'bg-rose-50 dark:bg-rose-950/50 border-rose-200 dark:border-rose-500/40 hover:border-rose-400 dark:hover:border-rose-400/60',
                                        default => 'bg-amber-50 dark:bg-amber-950/50 border-amber-200 dark:border-amber-500/40 hover:border-amber-400 dark:hover:border-amber-400/60',
                                    };
                                    $descTextClasses = match($priority) {
                                        'BAJA' => 'text-emerald-900 dark:text-emerald-100 group-hover:text-emerald-700 dark:group-hover:text-emerald-200',
                                        'ALTA' => 'text-rose-900 dark:text-rose-100 group-hover:text-rose-700 dark:group-hover:text-rose-200',
                                        default => 'text-amber-900 dark:text-amber-100 group-hover:text-amber-700 dark:group-hover:text-amber-200',
                                    };
                                    $arrowClasses = match($priority) {
                                        'BAJA' => 'text-emerald-600 dark:text-emerald-400 group-hover:text-emerald-700 dark:group-hover:text-emerald-300',
                                        'ALTA' => 'text-rose-600 dark:text-rose-400 group-hover:text-rose-700 dark:group-hover:text-rose-300',
                                        default => 'text-amber-600 dark:text-amber-400 group-hover:text-amber-700 dark:group-hover:text-amber-300',
                                    };
                                    $createdAtFormatted = $ticket->created_at ? \Carbon\Carbon::parse($ticket->created_at)->format('d/m/Y H:i') : null;
                                    $createdAtShort = $ticket->created_at ? \Carbon\Carbon::parse($ticket->created_at)->format('d/m/Y') : '—';
                                @endphp
                                <button
                                    type="button"
                                    wire:click.prevent="openTicketNotification({{ $ticket->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="openTicketNotification"
                                    wire:key="business-ticket-chip-{{ $ticket->id }}-{{ $loop->index }}"
                                    class="group shrink-0 inline-flex items-start gap-1 rounded-xl border shadow-sm transition-all duration-200 hover:shadow-md hover:-translate-y-[1px] focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-primary-500/50 cursor-pointer text-left disabled:opacity-60 disabled:pointer-events-none {{ $fullWidth ? 'px-2.5 py-1.5 flex-col items-stretch min-w-[13rem] sm:min-w-[15rem]' : 'flex-row items-center rounded-lg px-1.5 py-0.5 min-w-[10rem]' }} {{ $chipBgClasses }}"
                                    title="{{ Str::limit(strip_tags($ticket->description ?? ''), 200) }}"
                                    aria-label="Ver resumen del ticket {{ $ticket->id }}"
                                >
                                    <div class="flex items-center gap-1.5 min-w-0 shrink-0">
                                        <span class="shrink-0 rounded {{ $fullWidth ? 'px-1.5 py-0.5 text-[10px]' : 'px-1 py-0.5 text-[9px]' }} font-semibold uppercase {{ $priorityBadgeClasses }}">
                                            {{ $priority }}
                                        </span>
                                        <span class="{{ $fullWidth ? 'text-sm max-w-[200px] sm:max-w-[280px]' : 'text-xs max-w-[120px] sm:max-w-[160px]' }} font-medium truncate {{ $descTextClasses }}">
                                            {{ $desc ?: 'Sin descripción' }}
                                        </span>
                                        <svg class="{{ $fullWidth ? 'size-3.5' : 'size-3' }} shrink-0 transition-colors {{ $arrowClasses }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                        </svg>
                                    </div>
                                    @if($fullWidth)
                                        <span class="text-[10px] font-medium opacity-80 {{ $descTextClasses }}">
                                            {{ $createdAtFormatted ? 'Registrado: ' . $createdAtFormatted : 'Registrado: —' }}
                                        </span>
                                    @else
                                        <span class="text-[9px] font-medium opacity-80 {{ $descTextClasses }}" title="{{ $createdAtFormatted ?? '—' }}">
                                            {{ $createdAtShort }}
                                        </span>
                                    @endif
                                </button>
                                <span class="shrink-0 text-slate-300 dark:text-slate-600 {{ $fullWidth ? 'text-sm' : 'text-xs' }}" aria-hidden="true">•</span>
                            @endforeach
                        </div>
                    </div>
                </div>
                @if($fullWidth)
                    <p class="shrink-0 hidden lg:block text-[10px] text-slate-500 dark:text-slate-400" title="Pasa el mouse para pausar • Clic para ver resumen">
                        <span x-show="! paused">Auto-scroll activo</span>
                        <span x-show="paused">Ticker pausado</span>
                    </p>
                @endif
            </div>
        </div>
    @endif
</div>
