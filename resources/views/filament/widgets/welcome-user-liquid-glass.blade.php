<x-filament-widgets::widget>
    <div class="fi-welcome-liquid-glass-shell">
        <div class="fi-welcome-liquid-glass relative isolate overflow-hidden">
            <div aria-hidden="true" class="fi-welcome-liquid-glass__specular"></div>
            <div aria-hidden="true" class="fi-welcome-liquid-glass__orb"></div>

            <div class="relative z-[1] flex items-center gap-3">
                <div class="fi-welcome-liquid-glass__avatar relative shrink-0">
                    @if ($avatar)
                        <img
                            src="{{ $avatar }}"
                            alt="{{ $name }}"
                            loading="lazy"
                            class="h-11 w-11 rounded-full object-cover"
                        />
                    @else
                        <span class="fi-welcome-liquid-glass__avatar-fallback flex h-11 w-11 items-center justify-center rounded-full text-sm font-semibold text-white">
                            {{ \Illuminate\Support\Str::of($name)->explode(' ')->take(2)->map(fn ($w) => \Illuminate\Support\Str::substr($w, 0, 1))->implode('') ?: 'U' }}
                        </span>
                    @endif

                    <span
                        class="fi-welcome-liquid-glass__status absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full"
                        title="En línea"
                    ></span>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">
                        <span class="fi-welcome-liquid-glass__greeting text-xs font-medium">
                            {{ $greeting }}
                        </span>
                        <span class="fi-welcome-liquid-glass__dot hidden h-1 w-1 rounded-full sm:inline-block"></span>
                        <span class="fi-welcome-liquid-glass__date hidden truncate text-[11px] sm:inline">
                            {{ $date }}
                        </span>
                    </div>

                    <h2 class="fi-welcome-liquid-glass__name mt-0.5 truncate text-[15px] font-semibold tracking-tight">
                        {{ $name }}
                    </h2>

                    <span class="fi-welcome-liquid-glass__badge mt-1 inline-flex max-w-full items-center gap-1 truncate rounded-full px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide">
                        <x-filament::icon icon="heroicon-m-briefcase" class="h-3 w-3 shrink-0" />
                        <span class="truncate">{{ $role }}</span>
                    </span>
                </div>

                <form
                    action="{{ filament()->getLogoutUrl() }}"
                    method="post"
                    class="shrink-0"
                >
                    @csrf

                    <button
                        type="submit"
                        class="{{ \App\Support\Filament\FilamentIosButton::extraClassForFilamentColor('danger') }} !px-3 !py-1.5 !text-xs text-white"
                        title="Salir"
                    >
                        <x-filament::icon icon="heroicon-m-arrow-left-end-on-rectangle" class="h-3.5 w-3.5" />
                        <span class="hidden sm:inline">Salir</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
