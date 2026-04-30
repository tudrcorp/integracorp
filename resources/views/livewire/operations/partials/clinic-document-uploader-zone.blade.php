@php
    $isIngreso = $variant === 'ingreso';
    $inputId = $isIngreso ? 'clinic-ingreso-'.$serviceId : 'clinic-egreso-'.$serviceId;
    $pending = $isIngreso ? $ingresoUploads : $egresoUploads;
    $accept = '.jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf';
@endphp

<div
    class="space-y-4"
    wire:key="uploader-{{ $variant }}-{{ $serviceId }}"
    x-data="{
        over: false,
        assignFilesFromDrop(event) {
            this.over = false;
            const input = this.$refs.fileInput;
            if (!input || !event.dataTransfer?.files?.length) {
                return;
            }
            input.files = event.dataTransfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        },
    }"
>
    @if ($isIngreso)
        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Cargar nuevos documentos de ingreso</h3>
        <p class="text-xs leading-relaxed text-gray-500 dark:text-gray-400">
            Arrastre archivos a la zona o use el botón. Formatos permitidos:
            <span class="font-medium text-gray-700 dark:text-gray-300">JPG, PNG, WebP o PDF</span>
            · máximo
            <span class="font-medium text-gray-700 dark:text-gray-300">2 MB</span>
            por archivo.
        </p>
    @else
        <h3 class="text-base font-semibold text-amber-950 dark:text-amber-50">Cargar documentos de egreso (finaliza la orden)</h3>
        <p class="text-xs leading-relaxed text-amber-900/95 dark:text-amber-100/90">
            Al guardar, los archivos de egreso se registran y el estatus pasa a
            <strong>Finalizado</strong>. Misma regla de formatos y tamaño que en ingreso.
        </p>
    @endif

    <div class="relative">
        @if ($isIngreso)
            <div
                wire:loading
                wire:target="ingresoUploads"
                class="absolute inset-0 z-20 flex items-center justify-center rounded-2xl bg-white/85 backdrop-blur-sm dark:bg-gray-950/80"
            >
                <div class="flex flex-col items-center gap-2 text-primary-700 dark:text-primary-200">
                    <svg class="size-9 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="text-sm font-semibold">Procesando archivos…</span>
                </div>
            </div>
        @else
            <div
                wire:loading
                wire:target="egresoUploads"
                class="absolute inset-0 z-20 flex items-center justify-center rounded-2xl bg-amber-50/90 backdrop-blur-sm dark:bg-amber-950/80"
            >
                <div class="flex flex-col items-center gap-2 text-amber-900 dark:text-amber-100">
                    <svg class="size-9 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="text-sm font-semibold">Procesando archivos…</span>
                </div>
            </div>
        @endif

        <div
            class="relative overflow-hidden rounded-2xl border-2 border-dashed transition-all duration-200 ease-out focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-offset-white dark:focus-within:ring-offset-gray-950
                {{ $isIngreso
                    ? 'border-gray-300/90 bg-gradient-to-b from-gray-50/80 to-white dark:border-gray-600 dark:from-gray-900/80 dark:to-gray-950/60 focus-within:border-primary-400 focus-within:ring-primary-500/50'
                    : 'border-amber-300/80 bg-gradient-to-b from-amber-50/90 to-white dark:border-amber-600/50 dark:from-amber-950/40 dark:to-gray-950/60 focus-within:border-amber-400 focus-within:ring-amber-500/40' }}"
            x-bind:class="over
                ? '{{ $isIngreso
                    ? 'border-primary-400 bg-primary-500/10 ring-2 ring-primary-400/35 scale-[1.01] dark:border-primary-500 dark:bg-primary-500/15 dark:ring-primary-400/25'
                    : 'border-amber-400 bg-amber-400/15 ring-2 ring-amber-400/40 scale-[1.01] dark:border-amber-400 dark:bg-amber-400/10 dark:ring-amber-300/30' }}'
                : ''"
            x-on:dragover.prevent="over = true; $event.dataTransfer.dropEffect = 'copy'"
            x-on:dragleave.prevent="if (!$event.relatedTarget || !$event.currentTarget.contains($event.relatedTarget)) over = false"
            x-on:drop.prevent="assignFilesFromDrop($event)"
            role="region"
            aria-label="{{ $isIngreso ? 'Zona para soltar documentos de ingreso' : 'Zona para soltar documentos de egreso' }}"
        >
            <label for="{{ $inputId }}" class="sr-only">
                {{ $isIngreso ? 'Archivos de ingreso a clínica' : 'Archivos de egreso de clínica' }}, múltiples archivos permitidos
            </label>
            <input
                id="{{ $inputId }}"
                x-ref="fileInput"
                type="file"
                multiple
                accept="{{ $accept }}"
                class="sr-only"
                @if ($isIngreso)
                    wire:model="ingresoUploads"
                    wire:loading.attr="disabled"
                    wire:target="ingresoUploads"
                @else
                    wire:model="egresoUploads"
                    wire:loading.attr="disabled"
                    wire:target="egresoUploads"
                @endif
            />

            <div class="flex flex-col items-center justify-center gap-4 px-4 py-10 sm:py-12">
                <div
                    class="flex size-16 shrink-0 items-center justify-center rounded-2xl shadow-inner ring-1 ring-inset
                        {{ $isIngreso
                            ? 'bg-primary-500/10 text-primary-600 ring-primary-500/20 dark:bg-primary-500/15 dark:text-primary-300 dark:ring-primary-400/25'
                            : 'bg-amber-500/15 text-amber-800 ring-amber-600/25 dark:bg-amber-400/15 dark:text-amber-100 dark:ring-amber-400/30' }}"
                >
                    <svg class="size-9" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                    </svg>
                </div>

                <div class="max-w-md text-center">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white">
                        Arrastre y suelte aquí
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        o elija archivos desde su equipo
                    </p>
                </div>

                <div class="flex flex-wrap items-center justify-center gap-2">
                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-gray-600 dark:bg-gray-800 dark:text-gray-300">PDF</span>
                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-gray-600 dark:bg-gray-800 dark:text-gray-300">JPG</span>
                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-gray-600 dark:bg-gray-800 dark:text-gray-300">PNG</span>
                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-gray-600 dark:bg-gray-800 dark:text-gray-300">WebP</span>
                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide text-gray-600 dark:bg-gray-800 dark:text-gray-300">máx. 2 MB</span>
                </div>

                <button
                    type="button"
                    x-on:click.prevent="$refs.fileInput.click()"
                    class="inline-flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 active:scale-[0.98]
                        {{ $isIngreso
                            ? 'border-b-2 border-primary-600 bg-primary-600 text-white hover:bg-primary-500 focus-visible:ring-primary-500 dark:border-primary-500 dark:bg-primary-600 dark:hover:bg-primary-500'
                            : 'border-b-2 border-amber-700 bg-amber-600 text-white hover:bg-amber-500 focus-visible:ring-amber-500 dark:border-amber-500 dark:bg-amber-600 dark:hover:bg-amber-500' }}"
                >
                    <svg class="size-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                    </svg>
                    Elegir archivos
                </button>
            </div>
        </div>
    </div>

    @if (count($pending) > 0)
        <div class="rounded-2xl border border-gray-200/90 bg-gray-50/80 p-3 dark:border-gray-700 dark:bg-gray-900/50">
            <p class="mb-2 text-[0.65rem] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Listos para guardar ({{ count($pending) }})
            </p>
            <ul class="space-y-2">
                @foreach ($pending as $idx => $file)
                    <li
                        wire:key="pending-{{ $variant }}-{{ $idx }}"
                        class="flex items-center gap-3 rounded-xl border border-gray-200/80 bg-white px-3 py-2.5 shadow-sm dark:border-gray-700 dark:bg-gray-950/60"
                    >
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            <svg class="size-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white" title="{{ $file->getClientOriginalName() }}">
                                {{ $file->getClientOriginalName() }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ number_format(max(0, (int) ($file->getSize() ?: 0)) / 1024, 1, ',', '.') }} KB
                            </p>
                        </div>
                        @if ($isIngreso)
                            <button
                                type="button"
                                wire:click="removeIngresoUpload({{ $idx }})"
                                class="shrink-0 rounded-full border border-gray-200 px-2.5 py-1 text-xs font-semibold text-gray-600 transition hover:border-danger-300 hover:bg-danger-50 hover:text-danger-700 dark:border-gray-600 dark:text-gray-300 dark:hover:border-danger-500/50 dark:hover:bg-danger-950/40 dark:hover:text-danger-200"
                            >
                                Quitar
                            </button>
                        @else
                            <button
                                type="button"
                                wire:click="removeEgresoUpload({{ $idx }})"
                                class="shrink-0 rounded-full border border-gray-200 px-2.5 py-1 text-xs font-semibold text-gray-600 transition hover:border-danger-300 hover:bg-danger-50 hover:text-danger-700 dark:border-gray-600 dark:text-gray-300 dark:hover:border-danger-500/50 dark:hover:bg-danger-950/40 dark:hover:text-danger-200"
                            >
                                Quitar
                            </button>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($isIngreso)
        @error('ingresoUploads')
            <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
        @enderror
        @error('ingresoUploads.*')
            <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
        @enderror
    @else
        @error('egresoUploads')
            <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
        @enderror
        @error('egresoUploads.*')
            <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
        @enderror
    @endif

    <div class="flex justify-end pt-1">
        @if ($isIngreso)
            <button
                type="button"
                wire:click="saveIngreso"
                wire:loading.attr="disabled"
                wire:target="saveIngreso,ingresoUploads"
                class="inline-flex items-center gap-2 rounded-full border-b-2 border-primary-600 bg-primary-500/15 px-5 py-2.5 text-sm font-semibold text-primary-800 shadow-sm transition hover:bg-primary-500/25 active:scale-[0.98] dark:border-primary-500 dark:bg-primary-500/25 dark:text-primary-100 dark:hover:bg-primary-500/35"
            >
                <span wire:loading.remove wire:target="saveIngreso">Guardar documentos de ingreso</span>
                <span wire:loading wire:target="saveIngreso">Guardando…</span>
            </button>
        @else
            <button
                type="button"
                wire:click="saveEgreso"
                wire:loading.attr="disabled"
                wire:target="saveEgreso,egresoUploads"
                class="inline-flex items-center gap-2 rounded-full border-b-2 border-amber-700 bg-amber-500/25 px-5 py-2.5 text-sm font-semibold text-amber-950 shadow-sm transition hover:bg-amber-500/35 active:scale-[0.98] dark:border-amber-400 dark:bg-amber-500/20 dark:text-amber-50 dark:hover:bg-amber-500/30"
            >
                <span wire:loading.remove wire:target="saveEgreso">Guardar egreso y finalizar orden</span>
                <span wire:loading wire:target="saveEgreso">Guardando…</span>
            </button>
        @endif
    </div>
</div>
