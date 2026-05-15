@php
    use Illuminate\Support\Str;
@endphp

<section
    class="rounded-xl border border-gray-200 bg-white px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:border-white/10 dark:bg-gray-900 dark:ring-white/10"
    aria-labelledby="patient-case-obs-heading"
    wire:key="patient-case-obs-{{ $telemedicineCaseId }}"
>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between sm:gap-6">
        <div class="flex min-w-0 flex-1 gap-3">
            <span
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg border border-solid border-primary-200/90 bg-primary-50 text-primary-600 shadow-[inset_0_1px_0_0_rgb(255_255_255/0.6)] dark:border-primary-500/35 dark:bg-primary-400/10 dark:text-primary-400 dark:shadow-none"
                aria-hidden="true"
            >
                <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="h-5 w-5 shrink-0" />
            </span>
            <div class="min-w-0 flex-1 space-y-1">
                <h2 id="patient-case-obs-heading" class="text-[0.65rem] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Observaciones del caso
                </h2>
                <p class="text-xs leading-relaxed text-gray-600 dark:text-gray-400">
                    Registre notas administrativas o de seguimiento; quedan auditadas con su usuario y fecha.
                </p>
                <div class="relative pt-2">
                    <label for="case-obs-text-{{ $telemedicineCaseId }}" class="sr-only">
                        Texto de la observación
                    </label>
                    <x-filament::input.wrapper class="w-full overflow-hidden rounded-lg">
                        <textarea
                            id="case-obs-text-{{ $telemedicineCaseId }}"
                            wire:model.live.debounce.400ms="description"
                            rows="3"
                            maxlength="5000"
                            placeholder="Escriba aquí la observación sobre este caso…"
                            class="block h-full min-h-0 w-full resize-none border-none bg-transparent px-4 py-3.5 text-sm leading-6 text-gray-950 shadow-none outline-none transition placeholder:text-gray-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/30 dark:text-white dark:placeholder:text-gray-500 dark:focus-visible:ring-primary-400/30 [&:disabled]:text-gray-500 dark:[&:disabled]:text-gray-400"
                            style="min-height: 5.75rem; max-height: 16rem; overflow-y: auto; field-sizing: content;"
                        ></textarea>
                    </x-filament::input.wrapper>
                    <div class="mt-1.5 flex flex-wrap items-center justify-between gap-2 text-[0.65rem] text-gray-500 dark:text-gray-400">
                        <span>Mín. 2 caracteres · máx. 5.000</span>
                        <span class="tabular-nums">{{ strlen($description) }} / 5000</span>
                    </div>
                    @error('description')
                        <p class="mt-2 text-xs font-medium text-danger-600 dark:text-danger-400">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex flex-wrap items-center gap-2 pt-1">
                    <button
                        type="button"
                        wire:click="save"
                        wire:loading.attr="disabled"
                        wire:target="save"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-xs font-semibold text-white shadow-sm outline-none ring-1 ring-primary-600/20 transition hover:bg-primary-500 focus-visible:ring-2 focus-visible:ring-primary-500/35 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-primary-600 dark:ring-primary-400/25 dark:hover:bg-primary-500"
                    >
                        <span wire:loading.remove wire:target="save">Registrar observación</span>
                        <span wire:loading wire:target="save" class="inline-flex items-center gap-1.5">
                            <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Guardando…
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    @if ($latestObservation)
        <div class="mt-5 border-t border-gray-200 pt-4 dark:border-white/10">
            <p class="mb-3 text-[0.65rem] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                Última observación
            </p>
            <div
                class="rounded-lg border border-gray-200 bg-gray-50/90 px-3 py-2.5 dark:border-white/10 dark:bg-white/5"
                role="article"
            >
                <p class="text-[0.65rem] font-medium text-gray-500 dark:text-gray-400">
                    {{ optional($latestObservation->created_at)->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
                    @if ($latestObservation->createdBy)
                        <span class="text-gray-400 dark:text-gray-500">·</span>
                        <span class="text-gray-700 dark:text-gray-300">{{ Str::limit($latestObservation->createdBy->name ?? $latestObservation->createdBy->email ?? 'Usuario', 42) }}</span>
                    @endif
                </p>
                <p class="mt-1.5 whitespace-pre-line break-words text-left text-sm leading-relaxed text-gray-950 dark:text-white">
                    {{ trim((string) $latestObservation->description) }}
                </p>
            </div>
        </div>
    @else
        <p class="mt-4 border-t border-dashed border-gray-200 pt-4 text-center text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
            Aún no hay observaciones registradas para este caso.
        </p>
    @endif
</section>
