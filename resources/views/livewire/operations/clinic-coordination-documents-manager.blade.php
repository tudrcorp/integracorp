@php
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Str;
@endphp

<div wire:key="clinic-docs-root-{{ $serviceId }}" class="space-y-8 text-sm text-gray-700 dark:text-gray-200">
    <div class="rounded-2xl border border-gray-200/80 bg-gradient-to-b from-white to-gray-50/90 p-4 shadow-sm dark:border-white/10 dark:from-gray-900 dark:to-gray-950/80">
        <p class="text-[0.65rem] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Orden de coordinación</p>
        <div class="mt-2 grid gap-3 sm:grid-cols-2">
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400">Paciente</p>
                <p class="font-semibold text-gray-950 dark:text-white">{{ $service->patient ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400">Referencia / estatus</p>
                <p class="font-semibold text-gray-950 dark:text-white">
                    {{ $service->reference_number ?? '—' }}
                    <span class="text-gray-500 dark:text-gray-400">·</span>
                    {{ $service->status ?? '—' }}
                </p>
            </div>
        </div>
        @if ($readOnly)
            <p class="mt-3 rounded-lg border border-success-200/80 bg-success-50/90 px-3 py-2 text-xs text-success-950 dark:border-success-500/30 dark:bg-success-950/35 dark:text-success-50">
                Orden finalizada: solo puede consultar o descargar los documentos. No se permiten altas ni eliminaciones.
            </p>
        @else
            <p class="mt-3 rounded-lg border border-primary-200/80 bg-primary-50/90 px-3 py-2 text-xs text-primary-950 dark:border-primary-500/30 dark:bg-primary-950/40 dark:text-primary-50">
                Cargue los documentos de <strong>ingreso a clínica</strong>. Use un botón dedicado para los de <strong>egreso</strong>; al guardar el egreso, la orden pasará a <strong>Finalizado</strong>.
            </p>
        @endif
    </div>

    @foreach ([['title' => 'Documentos de ingreso a clínica', 'docs' => $ingresoDocuments, 'tone' => 'primary'], ['title' => 'Documentos de egreso de clínica', 'docs' => $egresoDocuments, 'tone' => 'amber']] as $block)
        <section class="space-y-3" wire:key="block-{{ $loop->index }}">
            <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $block['title'] }}</h3>
            @if ($block['docs']->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">Aún no hay archivos en esta categoría.</p>
            @else
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    @foreach ($block['docs'] as $doc)
                        @php
                            $url = Storage::disk('public')->url($doc->path);
                            $name = $doc->original_filename ?: basename($doc->path);
                            $ext = Str::lower((string) pathinfo($doc->path, PATHINFO_EXTENSION));
                        @endphp
                        <div
                            wire:key="doc-{{ $doc->id }}"
                            class="rounded-3xl border border-gray-200/70 bg-white/90 p-4 shadow-sm dark:border-gray-700/70 dark:bg-gray-900/60"
                        >
                            <div class="mb-2 truncate text-sm font-semibold text-gray-800 dark:text-gray-100" title="{{ $name }}">
                                {{ $name }}
                            </div>
                            <div class="mb-3">
                                @if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true))
                                    <img
                                        src="{{ $url }}"
                                        alt="{{ $name }}"
                                        class="max-h-60 w-full rounded-2xl border border-gray-200/80 object-contain dark:border-gray-700"
                                        loading="lazy"
                                    />
                                @elseif ($ext === 'pdf')
                                    <iframe
                                        src="{{ $url }}#toolbar=0&navpanes=0"
                                        class="h-60 w-full rounded-2xl border border-gray-200/80 bg-white dark:border-gray-700"
                                        title="{{ $name }}"
                                    ></iframe>
                                @else
                                    <div class="rounded-2xl border border-dashed border-gray-300 p-4 text-center text-xs text-gray-500 dark:border-gray-600 dark:text-gray-400">
                                        Vista previa no disponible para este tipo de archivo.
                                    </div>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <a
                                    href="{{ $url }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 rounded-full border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 no-underline hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                                >
                                    Ver
                                </a>
                                <a
                                    href="{{ route('operations.coordination.clinic-documents.download', $doc) }}"
                                    class="inline-flex items-center gap-1 rounded-full border-b-2 border-primary-600 bg-primary-500/10 px-3 py-1.5 text-xs font-semibold text-primary-800 no-underline dark:border-primary-500 dark:bg-primary-500/20 dark:text-primary-100"
                                >
                                    Descargar
                                </a>
                                @if (! $readOnly)
                                    <button
                                        type="button"
                                        wire:click="deleteDocument({{ $doc->id }})"
                                        wire:confirm="¿Eliminar este documento? Esta acción no se puede deshacer."
                                        wire:loading.attr="disabled"
                                        class="inline-flex items-center gap-1 rounded-full border-b-2 border-danger-600 bg-danger-500/10 px-3 py-1.5 text-xs font-semibold text-danger-800 dark:border-danger-500 dark:bg-danger-500/15 dark:text-danger-100"
                                    >
                                        <span wire:loading.remove wire:target="deleteDocument">Eliminar</span>
                                        <span wire:loading wire:target="deleteDocument">…</span>
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @endforeach

    @if (! $readOnly)
        <section class="rounded-2xl border border-gray-200/80 bg-white/95 p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900/40">
            @include('livewire.operations.partials.clinic-document-uploader-zone', ['variant' => 'ingreso', 'serviceId' => $serviceId])
        </section>

        <section class="rounded-2xl border border-amber-200/90 bg-amber-50/50 p-5 shadow-sm dark:border-amber-500/30 dark:bg-amber-950/25">
            @include('livewire.operations.partials.clinic-document-uploader-zone', ['variant' => 'egreso', 'serviceId' => $serviceId])
        </section>
    @endif
</div>
