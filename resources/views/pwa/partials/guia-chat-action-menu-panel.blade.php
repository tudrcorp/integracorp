<div class="flex max-h-[inherit] flex-col">
    <div class="shrink-0 border-b border-white/10 px-4 pb-3 pt-3 sm:px-5 sm:pt-4">
        <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-white/20 sm:hidden" aria-hidden="true"></div>

        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-cyan-300/75">Acciones</p>
                <h2 id="guia-chat-action-menu-title-{{ $panelIdPrefix }}" class="mt-1 text-base font-semibold text-white sm:text-lg">
                    ¿Qué quieres hacer?
                </h2>
                <p class="mt-0.5 text-xs text-white/60 sm:text-sm">Elige una opción para comenzar el chat guiado</p>
            </div>

            <button
                type="button"
                x-on:click="closeMenu()"
                class="rounded-full p-1.5 text-white/60 transition hover:bg-white/10 hover:text-white"
                aria-label="Cerrar menú de acciones"
            >
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>
        </div>
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-2 sm:px-4 sm:py-3">
        <ul class="space-y-1.5" role="listbox">
            @foreach ($actionMenuOptions as $action)
                <li wire:key="action-menu-{{ $panelIdPrefix }}-{{ $action['key'] }}">
                    <button
                        type="button"
                        x-on:click="closeMenu(); guiaChatSelectAction(@js($action['key']), @js($action['label']))"
                        class="{{ $selectedAction === $action['key']
                            ? 'group flex w-full items-start gap-3 rounded-2xl border border-emerald-400/40 bg-emerald-500/15 px-3 py-3 text-left transition hover:border-emerald-400/55 hover:bg-emerald-500/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300/50 sm:px-3.5 sm:py-3.5'
                            : 'group flex w-full items-start gap-3 rounded-2xl border border-white/10 bg-white/[0.04] px-3 py-3 text-left transition hover:border-white/20 hover:bg-white/[0.08] focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300/50 sm:px-3.5 sm:py-3.5' }}"
                        role="option"
                        aria-selected="{{ $selectedAction === $action['key'] ? 'true' : 'false' }}"
                    >
                        <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br {{ $action['accent'] }} ring-1 ring-white/15">
                            <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $action['icon'] }}"/>
                            </svg>
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-2">
                                <span class="block text-sm font-medium leading-snug text-white sm:text-[15px]">{{ $action['label'] }}</span>
                                @if ($selectedAction === $action['key'])
                                    <span class="inline-flex items-center rounded-full bg-emerald-400/20 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-200">
                                        Activa
                                    </span>
                                @endif
                            </span>
                            <span class="mt-1 block text-xs leading-relaxed text-white/55">{{ $action['description'] }}</span>
                        </span>

                        <svg class="mt-2 h-4 w-4 shrink-0 text-white/35 transition group-hover:translate-x-0.5 group-hover:text-white/70" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.5 4.25a.75.75 0 0 1 0 1.06l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="shrink-0 border-t border-white/10 px-4 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:px-5 sm:pb-3">
        <p class="text-center text-[11px] text-white/40">
            Selecciona una acción antes de enviar
        </p>
    </div>
</div>
