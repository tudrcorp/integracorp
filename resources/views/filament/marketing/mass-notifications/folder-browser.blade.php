<div class="grid gap-4 md:grid-cols-2">
    <div
        class="rounded-2xl bg-gradient-to-r from-primary-50 to-amber-50 px-4 py-4 ring-1 ring-primary-100 dark:from-primary-950/40 dark:to-amber-950/20 dark:ring-primary-900/70 md:col-span-2"
    >
        <div class="flex items-start gap-3">
            <div
                class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/80 ring-1 ring-primary-200 dark:bg-primary-900/60 dark:ring-primary-700"
            >
                <x-filament::icon
                    icon="heroicon-o-folder-open"
                    class="h-5 w-5 text-primary-600 dark:text-primary-300"
                />
            </div>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                    Organiza tus campañas por carpetas
                </p>
                <p class="text-xs text-gray-600 dark:text-gray-300">
                    Selecciona una carpeta para ver sus notificaciones masivas.
                </p>
            </div>
        </div>
    </div>

    @forelse ($folders as $folder)
        <button
            type="button"
            wire:click="openFolder({{ $folder->id }})"
            class="group h-full w-full rounded-3xl border border-gray-200 bg-white p-4 text-left shadow-sm transition duration-300 hover:-translate-y-1 hover:border-primary-300 hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:hover:border-primary-700"
        >
            <div class="flex flex-col gap-4 md:flex-row md:items-center">
                <div
                    class="relative isolate h-40 w-full overflow-hidden rounded-3xl bg-gradient-to-br from-slate-100 via-slate-200 to-slate-100 p-4 ring-1 ring-slate-300/70 dark:from-slate-800 dark:via-slate-900 dark:to-slate-800 dark:ring-slate-700 md:w-[18rem]"
                >
                    <div
                        class="absolute left-1/2 top-[4.3rem] h-16 w-44 -translate-x-1/2 rounded-full bg-blue-500/40 blur-2xl transition duration-500 group-hover:h-20 group-hover:w-52 group-hover:bg-blue-500/50 dark:bg-blue-400/25"
                    ></div>

                    <div
                        class="absolute left-[5.4rem] top-[2.65rem] h-[3.1rem] w-24 rounded-t-2xl rounded-b-lg bg-gradient-to-b from-blue-500 to-blue-600 shadow-sm transition duration-500 group-hover:-translate-y-0.5 dark:from-blue-400 dark:to-blue-500"
                    ></div>

                    <div
                        class="absolute left-1/2 top-[4.5rem] h-[4.35rem] w-[12.8rem] -translate-x-1/2 rounded-2xl bg-gradient-to-b from-blue-500 to-blue-700 shadow-[0_18px_35px_-10px_rgba(37,99,235,0.8)] transition duration-500 group-hover:-translate-y-1 dark:from-blue-400 dark:to-blue-600"
                    ></div>

                    @php
                        $previewImages = $folder->preview_images ?? [];
                        $previewSlots = [
                            'absolute left-[8.4rem] top-[4.85rem] h-[2.75rem] w-[2.15rem] rounded-md border border-white/45 opacity-0 shadow-md transition duration-500 group-hover:-translate-x-5 group-hover:-translate-y-[2.15rem] group-hover:-rotate-6 group-hover:opacity-100',
                            'absolute left-[9.75rem] top-[4.65rem] h-[3rem] w-[2.25rem] rounded-md border border-white/45 opacity-0 shadow-md transition duration-500 group-hover:-translate-y-[2.8rem] group-hover:rotate-2 group-hover:opacity-100',
                            'absolute left-[11.25rem] top-[4.95rem] h-[2.6rem] w-[1.95rem] rounded-md border border-white/45 opacity-0 shadow-md transition duration-500 group-hover:translate-x-5 group-hover:-translate-y-[2.05rem] group-hover:rotate-7 group-hover:opacity-100',
                        ];
                    @endphp

                    @foreach ($previewSlots as $slotIndex => $slotClass)
                        @php
                            $previewImage = $previewImages[$slotIndex] ?? null;
                        @endphp
                        <div class="{{ $slotClass }}">
                            @if ($previewImage)
                                <img
                                    src="{{ $previewImage }}"
                                    alt="Vista previa de notificación"
                                    loading="lazy"
                                    class="h-full w-full rounded-md object-cover"
                                />
                            @else
                                <div
                                    class="h-full w-full rounded-md bg-white/85 dark:bg-slate-100/80"
                                ></div>
                            @endif
                        </div>
                    @endforeach

                    <div
                        class="absolute inset-x-4 bottom-4 z-20 rounded-2xl border border-blue-300/40 bg-blue-500/20 px-4 py-3 backdrop-blur-sm transition duration-500 group-hover:translate-y-1 dark:border-blue-300/25 dark:bg-blue-500/10"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-2.5">
                                <span
                                    class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-700/45 ring-1 ring-blue-200/30"
                                >
                                    <x-filament::icon icon="heroicon-o-folder" class="h-4 w-4 text-blue-100" />
                                </span>
                                <p class="truncate text-sm font-semibold text-white">
                                    {{ $folder->name }}
                                </p>
                            </div>
                            <span
                                class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-white/85 text-blue-700 shadow transition duration-300 group-hover:scale-110"
                            >
                                <x-filament::icon icon="heroicon-o-arrow-right" class="h-5 w-5" />
                            </span>
                        </div>
                    </div>
                </div>

                <div class="min-w-0 flex-1 space-y-2.5">
                    <div class="flex items-center gap-2">
                        <h3 class="truncate text-base font-semibold text-gray-950 dark:text-white">
                            {{ $folder->name }}
                        </h3>

                        @if ($folder->is_default)
                            <span
                                class="inline-flex items-center rounded-full bg-primary-100 px-2 py-0.5 text-[11px] font-medium text-primary-700 dark:bg-primary-900/40 dark:text-primary-300"
                            >
                                Predeterminada
                            </span>
                        @endif
                    </div>

                    <div class="text-sm text-gray-600 dark:text-gray-300">
                        @if ($folder->is_default)
                            Las notificaciones nuevas se guardan aquí por defecto.
                        @else
                            {{ $folder->mass_notifications_count }}
                            {{ $folder->mass_notifications_count === 1 ? 'notificación' : 'notificaciones' }}
                            en esta carpeta.
                        @endif
                    </div>

                    <div class="flex items-center justify-between gap-3 pt-1">
                        <div
                            class="inline-flex items-center rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-white/10 dark:text-gray-200"
                        >
                            Actualizada:
                            {{ $folder->updated_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                        </div>

                        <span
                            class="inline-flex items-center gap-1 text-sm font-medium text-primary-600 transition group-hover:gap-1.5 dark:text-primary-400"
                        >
                            Abrir carpeta
                            <x-filament::icon icon="heroicon-o-arrow-right" class="h-4 w-4" />
                        </span>
                    </div>
                </div>
            </div>
        </button>
    @empty
        <div
            class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-10 text-center dark:border-white/15 dark:bg-gray-900 md:col-span-2"
        >
            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 dark:bg-white/10">
                <x-filament::icon icon="heroicon-o-folder-plus" class="h-6 w-6 text-gray-500 dark:text-gray-300" />
            </div>
            <p class="text-sm font-medium text-gray-800 dark:text-gray-100">
                No hay carpetas todavía
            </p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Crea tu primera carpeta con el botón «Crear carpeta».
            </p>
        </div>
    @endforelse
</div>
