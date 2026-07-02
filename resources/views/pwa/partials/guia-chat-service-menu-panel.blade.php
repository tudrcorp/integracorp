<div class="guia-chat-menu-panel flex max-h-[inherit] max-w-full flex-col overflow-hidden">
    <div class="shrink-0 border-b border-white/10 px-4 pb-3 pt-3 sm:px-5 sm:pt-4">
        <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-white/20 sm:hidden" aria-hidden="true"></div>

        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <template x-if="view === 'main'">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-cyan-300/75">Servicios</p>
                        <h2 id="guia-chat-service-menu-title-{{ $panelIdPrefix }}" class="mt-1 text-base font-semibold text-white sm:text-lg">
                            Menú GUIA-CHAT
                        </h2>
                        <p class="mt-0.5 text-xs text-white/60 sm:text-sm">Soporte, accesos y reportes</p>
                    </div>
                </template>

                <template x-if="view === 'login'">
                    <div>
                        <button
                            type="button"
                            x-on:click="view = 'main'"
                            class="mb-2 inline-flex items-center gap-1.5 text-xs font-medium text-cyan-200/90 transition hover:text-white"
                        >
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 0 1-.02 1.06L8.832 10l3.938 3.71a.75.75 0 1 1-1.04 1.08l-4.5-4.25a.75.75 0 0 1 0-1.08l4.5-4.25a.75.75 0 0 1 1.06.02Z" clip-rule="evenodd"/>
                            </svg>
                            Volver
                        </button>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-cyan-300/75">Accesos</p>
                        <h2 class="mt-1 text-base font-semibold text-white sm:text-lg">Login INTEGRACORP</h2>
                        <p class="mt-0.5 text-xs text-white/60 sm:text-sm">Elige el portal según tu perfil</p>
                    </div>
                </template>
            </div>

            <button
                type="button"
                x-on:click="closeMenu()"
                class="rounded-full p-1.5 text-white/60 transition hover:bg-white/10 hover:text-white"
                aria-label="Cerrar menú"
            >
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>
        </div>
    </div>

    <div class="min-h-0 flex-1 overflow-x-hidden overflow-y-auto overscroll-contain px-3 py-2 sm:px-4 sm:py-3">
        <ul x-show="view === 'main'" class="space-y-1.5">
            @foreach ($serviceMenuOptions as $option)
                <li wire:key="service-menu-{{ $panelIdPrefix }}-{{ $option['key'] }}" class="max-w-full">
                    <button
                        type="button"
                        x-on:click="selectOption(@js($option['key']))"
                        class="guia-chat-menu-option group flex w-full max-w-full items-start gap-3 overflow-hidden rounded-2xl border border-white/10 bg-white/[0.04] px-3 py-3 text-left transition hover:border-white/20 hover:bg-white/[0.08] focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300/50 sm:px-3.5 sm:py-3.5"
                    >
                        <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br {{ $option['accent'] }} ring-1 ring-white/15">
                            <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $option['icon'] }}"/>
                            </svg>
                        </span>

                        <span class="min-w-0 flex-1 overflow-hidden">
                            <span class="block min-w-0 break-words text-sm font-medium leading-snug text-white sm:text-[15px]">
                                @if (($option['highlight_brand'] ?? false) === true)
                                    Reportar fallas del
                                    <span class="bg-gradient-to-r from-emerald-300 via-cyan-300 to-teal-200 bg-clip-text font-bold text-transparent">GUIA-CHAT</span>
                                @else
                                    {{ $option['label'] }}
                                @endif
                            </span>
                            <span class="mt-1 block break-words text-xs leading-relaxed text-white/55">{{ $option['description'] }}</span>
                        </span>

                        <svg class="mt-2 h-4 w-4 shrink-0 text-white/35 transition group-hover:translate-x-0.5 group-hover:text-white/70" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.5 4.25a.75.75 0 0 1 0 1.06l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </li>
            @endforeach
        </ul>

        <ul x-show="view === 'login'" x-cloak class="grid gap-1.5 sm:grid-cols-1">
            @foreach ($integracorpLoginPanels as $panel)
                <li wire:key="login-panel-{{ $panelIdPrefix }}-{{ $panel['label'] }}">
                    <a
                        href="{{ $panel['url'] }}"
                        x-on:click="closeMenu()"
                        class="group flex items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.04] px-3 py-3 transition hover:border-white/20 hover:bg-white/[0.08] focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300/50 sm:px-3.5"
                    >
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br {{ $panel['accent'] }} ring-1 ring-white/15">
                            <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $panel['icon'] }}"/>
                            </svg>
                        </span>
                        <span class="min-w-0 flex-1 truncate text-sm font-semibold tracking-wide text-white">{{ $panel['label'] }}</span>
                        <span class="text-white/40 transition group-hover:translate-x-0.5 group-hover:text-white/70">›</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="shrink-0 border-t border-white/10 px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:px-5">
        <p class="text-center text-[11px] text-white/40">
            Integracorp · GUIA-CHAT
        </p>
    </div>
</div>
