@php
    /** @var list<array{document_name: string, file_path: string, document_types: list<string>, services: list<string>, source: string, uploaded_at: ?string, extension: string, preview_url: ?string, is_pdf: bool, is_image: bool}> $documents */
    $documents = is_array($documents ?? null) ? $documents : [];
    $embeddedInModal = (bool) ($embeddedInModal ?? false);
    $count = count($documents);
@endphp

<div
    x-data="{
        open: false,
        url: '',
        previewSrc: '',
        name: '',
        isPdf: false,
        isImage: false,
        show(doc) {
            this.url = doc.url
            this.name = doc.name
            this.isPdf = doc.isPdf
            this.isImage = doc.isImage
            const base = String(doc.url || '').split('#')[0]
            this.previewSrc = doc.isPdf
                ? base + '#toolbar=1&navpanes=0&scrollbar=1&view=FitH'
                : base
            this.open = true
            document.body.classList.add('overflow-hidden')
        },
        close() {
            this.open = false
            this.url = ''
            this.previewSrc = ''
            document.body.classList.remove('overflow-hidden')
        }
    }"
    @keydown.escape.window="close()"
    class="{{ $embeddedInModal ? '' : 'rounded-2xl border border-sky-200/80 bg-sky-50/80 p-4 dark:border-sky-500/25 dark:bg-sky-950/35' }}"
>
    @unless ($embeddedInModal)
        <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-slate-900 dark:text-white">Resultados cargados por Operaciones</p>
                <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                    {{ $count === 1
                        ? 'Hay 1 archivo de laboratorio o imagenología. Ábralo antes de redactar la lectura.'
                        : 'Hay '.$count.' archivos de laboratorio e imagenología. Ábralos antes de redactar la lectura.' }}
                </p>
            </div>
        </div>
    @endunless

    @if ($documents === [])
        <p class="text-sm text-slate-500 dark:text-slate-400">No hay resultados de laboratorio o imagenología cargados para este caso.</p>
    @else
        <ul class="flex flex-col gap-2">
            @foreach ($documents as $index => $document)
                @php
                    $previewUrl = $document['preview_url'] ?? null;
                    $name = (string) ($document['document_name'] ?? 'Documento');
                    $extension = (string) ($document['extension'] ?? '');
                    $types = is_array($document['document_types'] ?? null) ? $document['document_types'] : [];
                    $services = is_array($document['services'] ?? null) ? $document['services'] : [];
                    $source = (string) ($document['source'] ?? 'Operaciones');
                    $isPdf = (bool) ($document['is_pdf'] ?? false);
                    $isImage = (bool) ($document['is_image'] ?? false);
                @endphp
                <li
                    wire:key="lab-imaging-result-{{ $index }}-{{ md5((string) ($document['file_path'] ?? $index)) }}"
                    class="flex flex-col gap-3 rounded-xl border border-white/70 bg-white/90 p-3 shadow-sm dark:border-white/10 dark:bg-slate-900/70 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $name }}</p>
                        <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                            {{ $extension !== '' ? $extension : 'Archivo' }}
                            · {{ $source }}
                            @if ($services !== [])
                                · {{ implode(' · ', $services) }}
                            @endif
                        </p>
                        @if ($types !== [])
                            <div class="mt-1.5 flex flex-wrap gap-1">
                                @foreach ($types as $type)
                                    <span class="inline-flex rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-sky-800 dark:bg-sky-500/15 dark:text-sky-200">{{ $type }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                        @if (filled($previewUrl))
                            <button
                                type="button"
                                class="inline-flex min-h-[2.5rem] items-center justify-center gap-2 rounded-2xl bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700 active:scale-[0.98] dark:bg-sky-500 dark:hover:bg-sky-400"
                                @click="show({ url: @js($previewUrl), name: @js($name), isPdf: @js($isPdf), isImage: @js($isImage) })"
                            >
                                Vista previa
                            </button>
                            <a
                                href="{{ $previewUrl }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex min-h-[2.5rem] items-center justify-center rounded-2xl border border-sky-300 bg-white px-3 py-2 text-sm font-semibold text-sky-700 shadow-sm transition hover:bg-sky-50 dark:border-sky-500/40 dark:bg-sky-950/40 dark:text-sky-200 dark:hover:bg-sky-900/50"
                                title="Abrir {{ $name }} en una pestaña nueva"
                            >
                                Pestaña nueva
                            </a>
                        @else
                            <span class="text-xs font-medium text-amber-700 dark:text-amber-300">Archivo no disponible</span>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            x-transition.opacity.duration.150ms
            class="fixed inset-0 z-[200] flex h-[100dvh] w-screen items-stretch justify-center bg-slate-950/80 p-3 sm:p-5"
            @click.self="close()"
            role="dialog"
            aria-modal="true"
            aria-label="Vista previa del resultado"
        >
            <div class="flex h-full min-h-0 w-full max-w-7xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-slate-900">
                <div class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-slate-700">
                    <p class="truncate text-sm font-semibold text-slate-900 dark:text-white" x-text="name"></p>
                    <div class="flex items-center gap-2">
                        <a
                            x-show="url"
                            :href="url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex rounded-full px-3 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-50 dark:text-sky-200 dark:hover:bg-sky-900/40"
                        >
                            Abrir en pestaña nueva
                        </a>
                        <button
                            type="button"
                            class="inline-flex rounded-full px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                            @click="close()"
                        >
                            Cerrar
                        </button>
                    </div>
                </div>
                <div class="relative min-h-0 flex-1 bg-white dark:bg-slate-900" wire:ignore>
                    <template x-if="open && isPdf">
                        <iframe
                            :src="previewSrc"
                            class="absolute inset-0 h-full w-full border-0 bg-white"
                            title="Vista previa del resultado"
                        ></iframe>
                    </template>
                    <template x-if="open && isImage">
                        <div class="absolute inset-0 flex items-center justify-center overflow-auto p-4">
                            <img :src="url" :alt="name" class="max-h-full max-w-full rounded-lg object-contain">
                        </div>
                    </template>
                    <template x-if="open && !isPdf && !isImage">
                        <div class="absolute inset-0 flex items-center justify-center p-6 text-sm text-slate-500 dark:text-slate-400">
                            Este archivo no se puede previsualizar aquí. Úselo en una pestaña nueva.
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>
