@php
    $hasNote = filled(trim((string) ($observation ?? '')));
    $days = max(0, (int) $daysElapsed);
    $daysLabel = $days === 1 ? '1 día' : $days.' días';
@endphp

<div class="fi-helpdesk-notes-modal space-y-4 px-0.5 py-1">
    {{-- Metadatos: última actualización (estilo iOS / grupo de ajustes) --}}
    <div
        class="overflow-hidden rounded-[1.25rem] border border-gray-200/80 bg-gray-50/90 shadow-inner ring-1 ring-black/[0.03] dark:border-white/10 dark:bg-white/[0.06] dark:ring-white/[0.05]">
        <div
            class="flex items-center justify-between gap-3 border-b border-gray-200/70 px-4 py-3.5 dark:border-white/10">
            <div class="flex min-w-0 items-center gap-3">
                <span
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-white shadow-sm ring-1 ring-black/5 dark:bg-gray-800 dark:ring-white/10">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 text-sky-600 dark:text-sky-400" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5a2.25 2.25 0 0 0 2.25-2.25m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5a2.25 2.25 0 0 1 2.25 2.25v7.5" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <p class="text-[0.65rem] font-semibold uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">
                        Última actualización
                    </p>
                    <p class="truncate text-sm font-semibold tracking-tight text-gray-900 dark:text-white">
                        {{ $updatedAtFormatted }}
                    </p>
                    @if (filled($updatedRelative ?? null))
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                            {{ $updatedRelative }}
                        </p>
                    @endif
                </div>
            </div>
            <span
                class="shrink-0 rounded-full bg-sky-500/15 px-3 py-1.5 text-center text-xs font-bold tabular-nums text-sky-700 ring-1 ring-sky-500/25 dark:bg-sky-400/15 dark:text-sky-200 dark:ring-sky-400/30">
                {{ $daysLabel }}
            </span>
        </div>
        <div class="px-4 py-3">
            <p class="text-[0.65rem] font-semibold uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">
                Tiempo transcurrido
            </p>
            <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                Han pasado <span class="font-semibold text-gray-900 dark:text-white">{{ $daysLabel }}</span> desde la última
                modificación de este ticket.
            </p>
            @if (filled($updatedBy ?? null))
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Registrado por <span class="font-medium text-gray-700 dark:text-gray-300">{{ $updatedBy }}</span>
                </p>
            @endif
        </div>
    </div>

    {{-- Cuerpo de la nota --}}
    <div
        class="overflow-hidden rounded-[1.35rem] border border-gray-200/80 bg-gradient-to-b from-white/95 to-gray-50/90 p-4 shadow-[0_8px_30px_rgb(0,0,0,0.08)] ring-1 ring-black/[0.04] dark:border-white/10 dark:from-gray-950/90 dark:to-gray-900/80 dark:ring-white/[0.06]">
        <div class="mb-3 flex items-center gap-2">
            <span class="text-[0.65rem] font-semibold uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">
                Nota / observación
            </span>
        </div>
        @if ($hasNote)
            <div
                class="rounded-2xl bg-gray-100/90 px-4 py-3.5 text-sm leading-relaxed text-gray-800 shadow-inner ring-1 ring-black/[0.04] dark:bg-gray-900/60 dark:text-gray-100 dark:ring-white/[0.06]">
                {!! nl2br(e(trim((string) $observation))) !!}
            </div>
        @else
            <div
                class="rounded-2xl border border-dashed border-gray-300/90 bg-white/60 px-4 py-10 text-center text-sm text-gray-500 dark:border-white/15 dark:bg-white/[0.04] dark:text-gray-400">
                <p class="font-medium text-gray-600 dark:text-gray-300">Sin observaciones</p>
                <p class="mt-1 text-xs">Aún no se ha añadido una nota interna a este ticket.</p>
            </div>
        @endif
    </div>

    <p class="text-center text-[0.7rem] text-gray-400 dark:text-gray-500">
        Ticket #{{ $record->getKey() }} · {{ $record->created_by }}
    </p>
</div>
