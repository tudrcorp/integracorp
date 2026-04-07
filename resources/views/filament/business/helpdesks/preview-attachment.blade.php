@php
    $ext = strtolower((string) ($extension ?? ''));
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $isImage = in_array($ext, $imageExtensions, true);
    $isPdf = $ext === 'pdf';
@endphp

<div class="fi-helpdesk-attachment-preview space-y-4">
    @if ($missing ?? false)
        <div
            class="rounded-[1.35rem] border border-amber-200/80 bg-amber-50/90 px-4 py-6 text-center text-sm text-amber-900 shadow-inner dark:border-amber-500/30 dark:bg-amber-950/40 dark:text-amber-100">
            <p class="font-medium">No se encontró el archivo en el servidor.</p>
            <p class="mt-1 text-xs opacity-90">Ruta registrada: {{ $record->image ?? '—' }}</p>
        </div>
    @else
        <div
            class="overflow-hidden rounded-[1.35rem] border border-gray-200/80 bg-gradient-to-b from-white/95 to-gray-50/90 p-3 shadow-[0_8px_30px_rgb(0,0,0,0.08)] ring-1 ring-black/[0.04] dark:border-white/10 dark:from-gray-950/90 dark:to-gray-900/80 dark:ring-white/[0.06]">
            @if ($isImage)
                <div class="flex max-h-[min(72vh,560px)] items-center justify-center overflow-auto rounded-2xl bg-gray-100/80 p-2 dark:bg-gray-900/50">
                    <img
                        src="{{ $url }}"
                        alt="Vista previa del adjunto"
                        class="max-h-[min(68vh,520px)] w-auto max-w-full rounded-xl object-contain shadow-sm"
                        loading="lazy"
                    />
                </div>
            @elseif ($isPdf)
                <div class="overflow-hidden rounded-2xl bg-gray-100 ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
                    <iframe
                        src="{{ $url }}#toolbar=1"
                        title="Vista previa PDF"
                        class="h-[min(72vh,640px)] w-full border-0"
                    ></iframe>
                </div>
            @else
                <div
                    class="flex flex-col items-center justify-center gap-4 rounded-2xl bg-gray-100/90 px-6 py-14 text-center dark:bg-gray-900/60">
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        class="h-14 w-14 text-gray-400 dark:text-gray-500"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"
                        />
                    </svg>
                    <div>
                        <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Vista previa no disponible</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Abre el archivo en una nueva pestaña para verlo o descargarlo.
                        </p>
                    </div>
                    <a
                        href="{{ $url }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="aviso-btn-ios-info inline-flex shrink-0 items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]"
                    >
                        Abrir archivo
                    </a>
                </div>
            @endif
        </div>

        <p class="truncate text-center text-xs font-medium text-gray-500 dark:text-gray-400">
            {{ basename((string) $record->image) }}
        </p>
    @endif
</div>
