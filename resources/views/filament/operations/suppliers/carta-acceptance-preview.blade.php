@props([
    'exists' => false,
    'url' => null,
    'extension' => '',
    'supplier' => null,
])

@php
    $pdfExtensions = ['pdf'];
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
    $isPdf = $exists && in_array(strtolower((string) $extension), $pdfExtensions, true);
    $isImage = $exists && in_array(strtolower((string) $extension), $imageExtensions, true);
@endphp

<div class="fi-scoped space-y-4">
    @if (! $exists)
        <div
            class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-6 text-center dark:border-gray-600 dark:bg-gray-900/40"
            role="alert"
        >
            <svg class="mx-auto mb-2 h-10 w-10 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
            </svg>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
                No se encontró el archivo en el almacenamiento.
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Compruebe que la carta esté cargada correctamente o vuelva a subirla desde «Editar».
            </p>
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-950">
            @if ($isPdf)
                <iframe
                    title="Vista previa PDF — carta de aceptación"
                    src="{{ $url }}#toolbar=1"
                    class="h-[min(72vh,800px)] w-full border-0 bg-gray-100 dark:bg-gray-900"
                    loading="lazy"
                ></iframe>
            @elseif ($isImage)
                <div class="flex max-h-[min(72vh,800px)] items-center justify-center overflow-auto bg-gray-50 p-4 dark:bg-gray-900/50">
                    <img
                        src="{{ $url }}"
                        alt="Carta de aceptación — {{ $supplier?->razon_social ?? $supplier?->name ?? 'proveedor' }}"
                        class="max-h-full max-w-full rounded-lg object-contain shadow-sm"
                        loading="lazy"
                    />
                </div>
            @else
                <div class="p-6 text-center">
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        Vista previa no disponible para este tipo de archivo ({{ strtoupper($extension) }}).
                    </p>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Puede abrirlo o descargarlo con los botones de abajo.
                    </p>
                </div>
            @endif
        </div>

        <div
            class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-4 dark:border-white/10"
        >
            <p class="text-xs text-gray-500 dark:text-gray-400">
                @if ($isPdf)
                    Use la barra del visor PDF para ampliar o descargar.
                @elseif ($isImage)
                    Imagen ampliada al ancho del modal.
                @else
                    Abra el archivo en una pestaña nueva si no se muestra aquí.
                @endif
            </p>
            <div class="flex flex-wrap items-center gap-2">
                <a
                    href="{{ $url }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="fi-btn fi-btn-size-md inline-flex items-center justify-center gap-x-1 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm outline-none transition duration-75 hover:bg-gray-50 focus-visible:ring-2 focus-visible:ring-primary-600 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                >
                    Abrir en pestaña
                </a>
                <a
                    href="{{ $url }}"
                    download
                    class="fi-btn fi-btn-size-md inline-flex items-center justify-center gap-x-1 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white shadow-sm outline-none transition duration-75 hover:bg-emerald-500 focus-visible:ring-2 focus-visible:ring-emerald-500 dark:bg-emerald-600 dark:hover:bg-emerald-500"
                >
                    Descargar
                </a>
            </div>
        </div>
    @endif
</div>
