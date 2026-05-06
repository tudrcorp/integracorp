@php
    $documents = $documents ?? [];
    $count = count($documents);
@endphp

<div class="fi-helpdesk-documents-modal space-y-4 px-0.5 py-1">
    <div
        class="overflow-hidden rounded-[1.25rem] border border-gray-200/80 bg-gray-50/90 shadow-inner ring-1 ring-black/[0.03] dark:border-white/10 dark:bg-white/[0.06] dark:ring-white/[0.05]">
        <div class="flex items-center justify-between gap-3 border-b border-gray-200/70 px-4 py-3.5 dark:border-white/10">
            <div class="flex min-w-0 items-center gap-3">
                <span
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-white shadow-sm ring-1 ring-black/5 dark:bg-gray-800 dark:ring-white/10">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 text-indigo-600 dark:text-indigo-400" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m6.75 12H9m3.75 0v-3.375c0-.621-.504-1.125-1.125-1.125H8.25m9.75 3h-1.5m-9-3h1.5m6.75 0v-3.375c0-.621-.504-1.125-1.125-1.125H8.25M9 20.25h6" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <p class="text-[0.65rem] font-semibold uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">
                        Archivos adjuntos
                    </p>
                    <p class="truncate text-sm font-semibold tracking-tight text-gray-900 dark:text-white">
                        {{ $count === 1 ? '1 documento' : $count.' documentos' }}
                    </p>
                </div>
            </div>
        </div>
        <div class="px-4 py-3">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Imágenes y PDF cargados al crear el ticket. Desplázate para ver cada uno.
            </p>
        </div>
    </div>

    @forelse ($documents as $doc)
        @include('filament.business.helpdesks.file-preview-card', [
            'url' => $doc['url'] ?? '',
            'downloadUrl' => $doc['download_url'] ?? '',
            'extension' => $doc['extension'] ?? '',
            'missing' => $doc['missing'] ?? true,
            'storedPath' => $doc['path'] ?? '',
            'basename' => $doc['basename'] ?? '',
        ])
        @if (! ($doc['missing'] ?? true) && filled($doc['url'] ?? null))
            <div class="-mt-1 flex flex-wrap justify-center gap-2 pb-2">
                <a
                    href="{{ $doc['url'] }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="ticket-btn-ios-gray inline-flex shrink-0 items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                    </svg>
                    Abrir en pestaña
                </a>
                <a
                    href="{{ $doc['download_url'] ?? $doc['url'] }}"
                    class="ticket-btn-ios-gray inline-flex shrink-0 items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Descargar
                </a>
            </div>
        @endif
    @empty
        <div
            class="rounded-[1.35rem] border border-dashed border-gray-300/90 bg-white/60 px-4 py-12 text-center text-sm text-gray-500 dark:border-white/15 dark:bg-white/[0.04] dark:text-gray-400">
            <p class="font-medium text-gray-600 dark:text-gray-300">Sin documentos</p>
            <p class="mt-1 text-xs">Este ticket no tiene archivos adjuntos.</p>
        </div>
    @endforelse

    <p class="text-center text-[0.7rem] text-gray-400 dark:text-gray-500">
        Ticket #{{ $record->getKey() }} · {{ $record->created_by }}
    </p>
</div>
