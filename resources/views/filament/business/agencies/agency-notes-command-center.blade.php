@php
    /** @var \App\Models\Agency $record */
    /** @var array{events: list<array<string, mixed>>, total: int, loaded: int, limited: bool, max_id?: int} $noteTimeline */
    $events = $noteTimeline['events'] ?? [];
    $total = (int) ($noteTimeline['total'] ?? 0);
    $loaded = (int) ($noteTimeline['loaded'] ?? 0);
    $limited = (bool) ($noteTimeline['limited'] ?? false);
    $notesWireKey = (int) ($noteTimeline['max_id'] ?? 0);
    $tz = (string) config('app.timezone');
@endphp

<div
    class="fi-agency-notes-command-center space-y-5"
    wire:key="agency-command-center-notes-{{ $record->getKey() }}-{{ $notesWireKey }}"
    x-data="{ draft: '' }"
>
    <div class="fi-helpdesk-notes-modal space-y-3 px-0.5 py-1">
        <div
            class="overflow-hidden rounded-[1.35rem] border border-gray-200/80 bg-gradient-to-b from-white/95 to-gray-50/90 p-4 shadow-[0_8px_30px_rgb(0,0,0,0.08)] ring-1 ring-black/[0.04] dark:border-white/10 dark:from-gray-950/90 dark:to-gray-900/80 dark:ring-white/[0.06]">
            <div class="mb-3 flex items-center gap-2">
                <span class="text-[0.65rem] font-semibold uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">
                    Nueva nota u observación
                </span>
            </div>
            <form
                class="space-y-3"
                novalidate
                @submit.prevent="$wire.saveAgencyCommandCenterNoteFromSlideover('{{ $record->getKey() }}', draft)"
            >
                <textarea
                    x-model="draft"
                    maxlength="255"
                    rows="5"
                    class="w-full resize-y rounded-2xl border border-gray-200/90 bg-gray-100/90 px-4 py-3 text-sm leading-relaxed text-gray-800 shadow-inner ring-1 ring-black/[0.04] placeholder:text-gray-400 focus:border-sky-400/80 focus:outline-none focus:ring-2 focus:ring-sky-500/25 dark:border-white/10 dark:bg-gray-900/60 dark:text-gray-100 dark:ring-white/[0.06] dark:placeholder:text-gray-500"
                    placeholder="Escriba el seguimiento interno para esta agencia (máx. 255 caracteres)."
                ></textarea>
                <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500 dark:text-gray-400">
                    <span x-text="(255 - (draft || '').length) + ' caracteres restantes'"></span>
                    <button
                        type="submit"
                        wire:target="saveAgencyCommandCenterNoteFromSlideover"
                        wire:loading.attr="disabled"
                        class="ticket-btn-ios-success inline-flex min-h-[2.5rem] items-center justify-center gap-2 rounded-2xl px-5 py-2.5 text-sm font-semibold shadow-sm transition active:scale-[0.98] disabled:opacity-50"
                    >
                        <span wire:loading.remove wire:target="saveAgencyCommandCenterNoteFromSlideover">Guardar nota</span>
                        <span wire:loading wire:target="saveAgencyCommandCenterNoteFromSlideover">Guardando…</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="helpdesk-timeline-modal-root space-y-4 overflow-x-hidden px-1 py-1">
        <section class="rounded-[1.35rem] border border-sky-200/60 bg-sky-50/40 p-4 ring-1 ring-sky-100/80 dark:border-sky-500/25 dark:bg-sky-950/25 dark:ring-sky-500/15">
            <p class="text-sm font-semibold text-sky-950 dark:text-sky-100">Bitácora de notas de la agencia</p>
            <div class="mt-2 space-y-2 text-xs leading-relaxed text-sky-900/90 dark:text-sky-100/85">
                <p>
                    Cada tarjeta es <strong>una nota u observación</strong> registrada en el sistema, de la más antigua a la más reciente.
                </p>
                <p>
                    La franja vertical une el tiempo en el eje. <strong>El círculo muestra las iniciales</strong> de quien registró la nota.
                </p>
                <p>
                    Las fechas absolutas usan la zona <code class="rounded bg-white/70 px-1 py-0.5 text-[0.65rem] dark:bg-black/30">{{ $tz }}</code>.
                </p>
            </div>
        </section>

        <section class="rounded-[1.35rem] border border-gray-200/80 bg-white/90 p-4 ring-1 ring-black/[0.04] dark:border-white/10 dark:bg-white/[0.03] dark:ring-white/[0.05]">
            <p class="mb-1 text-[0.7rem] font-semibold uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">
                Línea de tiempo de notas
            </p>
            <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">
                @if ($total === 0)
                    Aún no hay notas registradas para esta agencia.
                @else
                    {{ $loaded }} {{ $loaded === 1 ? 'nota mostrada' : 'notas mostradas' }}
                    @if ($limited)
                        (de {{ $total }} en total; se muestran las {{ $loaded }} más recientes en orden cronológico)
                    @endif
                @endif
            </p>

            @if (count($events) === 0)
                <div
                    class="rounded-2xl border border-dashed border-gray-300/90 bg-white/60 px-4 py-10 text-center text-sm text-gray-500 dark:border-white/15 dark:bg-white/[0.04] dark:text-gray-400">
                    <p class="font-medium text-gray-600 dark:text-gray-300">Sin notas en el historial</p>
                    <p class="mt-1 text-xs">Use el formulario superior para registrar la primera observación.</p>
                </div>
            @else
                <div class="relative isolate overflow-x-hidden pl-1">
                    <div class="pointer-events-none absolute bottom-2 left-[19px] top-2 z-0 w-px bg-gradient-to-b from-gray-200 via-gray-300 to-gray-200 dark:from-white/10 dark:via-white/15 dark:to-white/10"></div>

                    <div class="relative z-0 space-y-5">
                        @foreach ($events as $event)
                            @php
                                $isRight = ($event['side'] ?? 'left') === 'right';
                                $type = $event['type'] ?? 'note';
                                $ringAvatar = match ($type) {
                                    'legacy_note' => 'ring-gray-400/60 dark:ring-gray-500/40',
                                    default => 'ring-fuchsia-400/70 dark:ring-fuchsia-400/45',
                                };
                                $typeBadge = match ($type) {
                                    'legacy_note' => 'Histórico',
                                    default => 'Nota',
                                };
                            @endphp
                            <div class="relative flex gap-3">
                                <div class="relative shrink-0" style="width: 40px;">
                                    @if (filled($event['avatar_url'] ?? null))
                                        <img
                                            src="{{ $event['avatar_url'] }}"
                                            alt=""
                                            class="h-10 w-10 rounded-full object-cover ring-2 ring-white shadow-md dark:ring-gray-950 {{ $ringAvatar }}"
                                            loading="lazy"
                                        />
                                    @else
                                        <div
                                            class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-slate-600 to-slate-800 text-[0.7rem] font-bold uppercase tracking-tight text-white shadow-md ring-2 ring-white dark:from-slate-500 dark:to-slate-700 dark:ring-gray-950 {{ $ringAvatar }}"
                                            aria-hidden="true"
                                        >
                                            {{ $event['initials'] ?? '?' }}
                                        </div>
                                    @endif
                                </div>

                                <div class="min-w-0 flex-1 pb-1">
                                    <div @class([
                                        'rounded-2xl border px-4 py-3 shadow-sm ring-1',
                                        'border-sky-200/80 bg-sky-50/70 ring-sky-200/40 dark:border-sky-500/30 dark:bg-sky-500/10 dark:ring-sky-500/20' => $isRight,
                                        'border-gray-200/80 bg-white ring-black/[0.04] dark:border-white/10 dark:bg-gray-900/60 dark:ring-white/[0.04]' => ! $isRight,
                                    ])>
                                        <div class="mb-2 flex flex-wrap items-center gap-2">
                                            <span class="rounded-full bg-gray-900/5 px-2 py-0.5 text-[0.6rem] font-bold uppercase tracking-wider text-gray-600 dark:bg-white/10 dark:text-gray-300">
                                                {{ $typeBadge }}
                                            </span>
                                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $event['title'] }}</p>
                                        </div>

                                        <p class="mb-3 text-xs leading-relaxed text-gray-600 dark:text-gray-300">
                                            {{ $event['summary'] ?? '' }}
                                        </p>

                                        <div class="mb-3 space-y-1.5 border-t border-gray-200/80 pt-3 text-[0.7rem] text-gray-500 dark:border-white/10 dark:text-gray-400">
                                            <p class="flex items-start gap-2 text-gray-700 dark:text-gray-200">
                                                <svg class="mt-0.5 h-3.5 w-3.5 shrink-0 opacity-70" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>
                                                <span>
                                                    <span class="block text-[0.6rem] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Quién actuó</span>
                                                    <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $event['display_name'] ?? $event['actor'] }}</span>
                                                </span>
                                            </p>
                                            <p class="flex items-start gap-2">
                                                <svg class="mt-0.5 h-3.5 w-3.5 shrink-0 opacity-70" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5a2.25 2.25 0 0 0 2.25-2.25m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5a2.25 2.25 0 0 1 2.25 2.25v7.5" /></svg>
                                                <span>
                                                    <span class="block text-[0.6rem] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Fecha y hora exactas</span>
                                                    <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $event['datetime_full'] ?? '—' }}</span>
                                                </span>
                                            </p>
                                            <p class="flex items-start gap-2">
                                                <svg class="mt-0.5 h-3.5 w-3.5 shrink-0 opacity-70" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                                <span>
                                                    <span class="block text-[0.6rem] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Referencia relativa</span>
                                                    <span>{{ $event['relative'] ?? '—' }}</span>
                                                </span>
                                            </p>
                                        </div>

                                        @if (filled(trim(strip_tags($event['body_html'] ?? ''))))
                                            <p class="mb-1 text-[0.65rem] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Contenido asociado a esta acción</p>
                                            <div class="text-sm leading-relaxed text-gray-800 dark:text-gray-100 [&_p]:mb-2 [&_p:last-child]:mb-0 [&_ul]:my-2 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:my-2 [&_ol]:list-decimal [&_ol]:pl-5 [&_a]:text-sky-600 [&_a]:underline dark:[&_a]:text-sky-400 [&_strong]:font-semibold [&_mark]:rounded [&_mark]:px-0.5">
                                                {!! $event['body_html'] !!}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>
    </div>

    <p class="text-center text-[0.7rem] text-gray-400 dark:text-gray-500">
        Agencia {{ $record->code }}
    </p>
</div>
