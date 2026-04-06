@props([
    'supplier' => null,
    'documents' => [],
])

@php
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
@endphp

<div class="fi-scoped space-y-4">
    @if (empty($documents))
        <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50/90 p-6 text-center shadow-sm dark:border-white/15 dark:bg-gray-900/60">
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                No hay documentos de afiliación cargados.
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Use el botón «Agregar documentos» en el pie del modal, o la acción «Agregar Documentos de Afiliación» en la barra superior.
            </p>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($documents as $document)
                @php
                    $isImage = $document['exists'] && in_array($document['extension'], $imageExtensions, true);
                    $isPdf = $document['exists'] && $document['extension'] === 'pdf';
                @endphp

                <article class="overflow-hidden rounded-3xl border border-gray-200/80 bg-white/80 shadow-sm backdrop-blur-md dark:border-white/10 dark:bg-gray-900/70">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200/80 px-4 py-3 dark:border-white/10">
                        <div>
                            <p class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">
                                Documento {{ $document['id'] }}
                            </p>
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                                {{ $document['name'] }}
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-medium text-sky-700 dark:bg-sky-500/20 dark:text-sky-300">
                                {{ strtoupper($document['extension'] ?: 'N/A') }}
                            </span>
                            <span @class([
                                'rounded-full px-2.5 py-1 text-xs font-medium',
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300' => $document['exists'],
                                'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300' => ! $document['exists'],
                            ])>
                                {{ $document['exists'] ? 'Disponible' : 'No encontrado' }}
                            </span>
                            <button
                                type="button"
                                wire:click="deleteSupplierAffiliationDocument({{ $document['index'] }})"
                                wire:confirm="¿Quitar este documento de la ficha? Si el archivo existe en almacenamiento, también se eliminará."
                                class="inline-flex items-center rounded-full border border-rose-300/90 bg-white px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-50 dark:border-rose-500/40 dark:bg-gray-900 dark:text-rose-300 dark:hover:bg-rose-950/50"
                            >
                                Eliminar
                            </button>
                        </div>
                    </div>

                    <div class="bg-gray-50/80 p-3 dark:bg-gray-950/60">
                        @if (! $document['exists'])
                            <div class="rounded-2xl border border-dashed border-gray-300 p-5 text-center dark:border-white/15">
                                <p class="text-sm text-gray-600 dark:text-gray-300">
                                    El archivo no existe en almacenamiento.
                                </p>
                            </div>
                        @elseif ($isPdf)
                            <iframe
                                title="Vista previa {{ $document['name'] }}"
                                src="{{ $document['url'] }}#toolbar=1"
                                class="h-[360px] w-full rounded-2xl border-0 bg-white dark:bg-gray-900"
                                loading="lazy"
                            ></iframe>
                        @elseif ($isImage)
                            <div class="flex max-h-[360px] items-center justify-center overflow-auto rounded-2xl bg-white p-3 dark:bg-gray-900">
                                <img
                                    src="{{ $document['url'] }}"
                                    alt="{{ $document['name'] }} - {{ $supplier?->name ?? 'Proveedor' }}"
                                    class="max-h-[330px] max-w-full rounded-xl object-contain shadow-sm"
                                    loading="lazy"
                                />
                            </div>
                        @else
                            <div class="rounded-2xl border border-dashed border-gray-300 p-5 text-center dark:border-white/15">
                                <p class="text-sm text-gray-600 dark:text-gray-300">
                                    Vista previa no disponible para este tipo de archivo.
                                </p>
                            </div>
                        @endif
                    </div>

                    @if ($document['exists'])
                        <div class="flex items-center justify-end gap-2 px-4 py-3">
                            <a
                                href="{{ $document['url'] }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center rounded-full border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                            >
                                Abrir
                            </a>
                            <a
                                href="{{ $document['url'] }}"
                                download
                                class="inline-flex items-center rounded-full bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-500"
                            >
                                Descargar
                            </a>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
</div>
