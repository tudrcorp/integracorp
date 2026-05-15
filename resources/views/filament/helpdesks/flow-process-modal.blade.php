@php
    $files = $files ?? collect();
@endphp

<div class="space-y-4">
    <div
        class="overflow-hidden rounded-[1.25rem] border border-gray-200/80 bg-gray-50/90 shadow-inner ring-1 ring-black/[0.03] dark:border-white/10 dark:bg-white/[0.06] dark:ring-white/[0.05]">
        <div class="flex items-center justify-between gap-3 border-b border-gray-200/70 px-4 py-3.5 dark:border-white/10">
            <div class="flex min-w-0 items-center gap-3">
                <span
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-white shadow-sm ring-1 ring-black/5 dark:bg-gray-800 dark:ring-white/10">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 text-amber-500 dark:text-amber-300" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125V5.625c0-.621.504-1.125 1.125-1.125h6.163c.298 0 .583.118.794.329l2.226 2.226c.211.211.329.496.329.794v10.526c0 .621-.504 1.125-1.125 1.125m-8.387 0h8.387m-8.387 0v-4.5m8.387 4.5v-4.5m0-5.625H7.5m5.25 0v5.25m0 0 1.875-1.875m-1.875 1.875-1.875-1.875" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <p class="text-[0.65rem] font-semibold uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">
                        Biblioteca del proceso
                    </p>
                    <p class="truncate text-sm font-semibold tracking-tight text-gray-900 dark:text-white">
                        {{ $files->count() === 1 ? '1 archivo disponible' : $files->count().' archivos disponibles' }}
                    </p>
                </div>
            </div>
        </div>
        <div class="px-4 py-3">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Puedes cargar múltiples archivos desde esta modal. Al guardar, se agregan a esta biblioteca para descargar o eliminar.
            </p>
        </div>
    </div>

    @forelse ($files as $file)
        @php
            $path = (string) $file->file_path;
            $name = (string) ($file->original_name ?: basename($path));
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $url = asset('storage/'.$path);
            $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
            $isVideo = in_array($ext, ['mp4', 'mov', 'avi', 'wmv', 'mkv'], true);
            $isPdf = $ext === 'pdf';
        @endphp

        <div
            wire:key="helpdesk-flow-file-{{ $file->getKey() }}"
            class="overflow-hidden rounded-[1.35rem] border border-gray-200/80 bg-gradient-to-b from-white/95 to-gray-50/90 p-3 shadow-[0_8px_30px_rgb(0,0,0,0.08)] ring-1 ring-black/[0.04] dark:border-white/10 dark:from-gray-950/90 dark:to-gray-900/80 dark:ring-white/[0.06]"
        >
            @if ($isImage)
                <div class="flex max-h-[min(60vh,460px)] items-center justify-center overflow-auto rounded-2xl bg-gray-100/80 p-2 dark:bg-gray-900/50">
                    <img
                        src="{{ $url }}"
                        alt="{{ $name }}"
                        class="max-h-[min(54vh,420px)] w-auto max-w-full rounded-xl object-contain shadow-sm"
                        loading="lazy"
                    />
                </div>
            @elseif ($isVideo)
                <div class="overflow-hidden rounded-2xl bg-black/95 ring-1 ring-black/10 dark:ring-white/10">
                    <video controls class="h-[min(56vh,430px)] w-full" preload="metadata">
                        <source src="{{ $url }}" type="{{ $file->mime_type ?: 'video/mp4' }}">
                    </video>
                </div>
            @elseif ($isPdf)
                <div class="overflow-hidden rounded-2xl bg-gray-100 ring-1 ring-black/5 dark:bg-gray-900 dark:ring-white/10">
                    <iframe
                        src="{{ $url }}#toolbar=1"
                        title="Vista previa PDF"
                        class="h-[min(58vh,460px)] w-full border-0"
                    ></iframe>
                </div>
            @else
                <div class="flex flex-col items-center justify-center gap-3 rounded-2xl bg-gray-100/90 px-6 py-10 text-center dark:bg-gray-900/60">
                    <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-500/20 dark:text-amber-200">
                        {{ strtoupper($ext ?: 'archivo') }}
                    </span>
                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $name }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Vista previa no disponible para este formato.
                    </p>
                </div>
            @endif

            <div class="mt-3 flex flex-col gap-3 border-t border-gray-200/80 pt-3 dark:border-white/10 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <p class="truncate text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $name }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ filled($file->created_at) ? $file->created_at->format('d/m/Y H:i') : '—' }}
                    </p>
                </div>
                <div class="flex flex-wrap justify-end gap-2">
                    <a
                        href="{{ route('helpdesks.flow-process-files.download', $file) }}"
                        class="ticket-btn-ios-gray inline-flex shrink-0 items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]"
                    >
                        Descargar
                    </a>
                    <button
                        type="button"
                        wire:click="mountDeleteHelpdeskFlowProcessFile({{ $file->getKey() }})"
                        class="inline-flex shrink-0 cursor-pointer items-center justify-center gap-2 rounded-full bg-red-600 px-4 py-2 text-sm font-semibold tracking-tight text-white transition-all duration-200 hover:bg-red-700 active:scale-[0.98]"
                    >
                        Eliminar
                    </button>
                </div>
            </div>
        </div>
    @empty
        <div class="rounded-[1.35rem] border border-dashed border-gray-300/90 bg-white/60 px-4 py-12 text-center text-sm text-gray-500 dark:border-white/15 dark:bg-white/[0.04] dark:text-gray-400">
            <p class="font-medium text-gray-600 dark:text-gray-300">Aún no hay archivos en el flujo del proceso</p>
            <p class="mt-1 text-xs">Carga documentos o videos para que estén disponibles en esta biblioteca.</p>
        </div>
    @endforelse
</div>
