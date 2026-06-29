<x-filament-widgets::widget>
    <div
        class="fi-telemedicine-welcome relative isolate overflow-hidden rounded-3xl p-5 sm:p-6
            bg-white ring-1 ring-gray-950/5 shadow-sm
            dark:bg-gray-900 dark:ring-white/10"
    >
        {{-- Halo decorativo --}}
        <div
            aria-hidden="true"
            class="pointer-events-none absolute -right-16 -top-24 h-56 w-56 rounded-full
                bg-primary-500/10 blur-3xl dark:bg-primary-400/10"
        ></div>
        <div
            aria-hidden="true"
            class="pointer-events-none absolute -bottom-24 -left-10 h-48 w-48 rounded-full
                bg-primary-500/5 blur-3xl dark:bg-primary-400/5"
        ></div>

        <div class="relative flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                <div class="relative shrink-0">
                    @if ($avatar)
                        <img
                            src="{{ $avatar }}"
                            alt="{{ $name }}"
                            loading="lazy"
                            class="h-16 w-16 rounded-full object-cover ring-2 ring-primary-500/30 shadow-md
                                dark:ring-primary-400/30"
                        />
                    @else
                        <span class="flex h-16 w-16 items-center justify-center rounded-full bg-primary-500 text-xl font-semibold text-white shadow-md">
                            {{ \Illuminate\Support\Str::of($name)->explode(' ')->take(2)->map(fn ($w) => \Illuminate\Support\Str::substr($w, 0, 1))->implode('') ?: 'TD' }}
                        </span>
                    @endif

                    {{-- Indicador en línea --}}
                    <span
                        class="absolute bottom-0 right-0 h-4 w-4 rounded-full border-2 border-white bg-green-500
                            dark:border-gray-900"
                        title="En línea"
                    ></span>
                </div>

                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-primary-600 dark:text-primary-400">
                            {{ $greeting }} 👋
                        </span>
                        <span class="hidden h-1 w-1 rounded-full bg-gray-300 sm:inline-block dark:bg-gray-600"></span>
                        <span class="hidden text-xs text-gray-500 sm:inline dark:text-gray-400">
                            {{ $date }}
                        </span>
                    </div>

                    <h2 class="mt-0.5 truncate text-xl font-bold tracking-tight text-gray-950 sm:text-2xl dark:text-white">
                        {{ $name }}
                    </h2>

                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-50 px-2.5 py-0.5 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-600/10
                            dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-400/20">
                            <x-filament::icon icon="healthicons-f-doctor-male" class="h-3.5 w-3.5" />
                            {{ $role }}
                        </span>
                        <span class="inline-flex items-center gap-1 text-xs text-gray-500 sm:hidden dark:text-gray-400">
                            {{ $date }}
                        </span>
                    </div>
                </div>
            </div>

            <form
                action="{{ filament()->getLogoutUrl() }}"
                method="post"
                class="shrink-0"
            >
                @csrf

                <button
                    type="submit"
                    class="{{ \App\Support\Filament\FilamentIosButton::extraClassForFilamentColor('danger') }} text-white"
                >
                    <x-filament::icon icon="heroicon-m-arrow-left-end-on-rectangle" class="h-4 w-4" />
                    <span>Salir</span>
                </button>
            </form>
        </div>
    </div>
</x-filament-widgets::widget>
