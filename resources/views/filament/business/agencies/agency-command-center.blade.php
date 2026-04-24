@php
    /** @var \App\Models\Agency $record */
    $typeLabel = $record->typeAgency?->definition ?? '—';
    $fichaPreviewUrl = route('business.agencies.ficha-pdf.preview', $record);
    $fichaDownloadUrl = route('business.agencies.ficha-pdf.download', $record);
    $codeLabel = filled($typeLabel) && $typeLabel !== '—' ? $typeLabel.' — '.$record->code : $record->code;
    $fichaWhatsappUrl = 'https://wa.me/?text='.rawurlencode(
        'Ficha de la agencia '.$record->name_corporative.' ('.$codeLabel.'). Descarga PDF (requiere inicio de sesión en Integracorp): '.$fichaDownloadUrl
    );
@endphp

<div class="agency-command-center-root space-y-6 overflow-x-hidden px-0.5 pb-6 sm:pb-8">
    <section
        class="rounded-[1.35rem] border border-slate-200/80 bg-gradient-to-b from-white to-slate-50/95 p-5 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] ring-1 ring-black/[0.04] dark:border-white/10 dark:from-gray-900/90 dark:to-slate-950/90 dark:ring-white/[0.06]">
        <p class="text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">Agencia</p>
        <div class="mt-2 space-y-3">
            <div>
                <p class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $record->name_corporative }}</p>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                    <span class="font-semibold text-slate-800 dark:text-slate-100">Código</span><br />
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-900 dark:bg-emerald-500/20 dark:text-emerald-100">{{ $typeLabel }} — {{ $record->code }}</span>
                </p>
            </div>
            <div class="grid gap-3 border-t border-slate-200/70 pt-3 text-sm dark:border-white/10 sm:grid-cols-2">
                <div>
                    <p class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Estado</p>
                    <p class="mt-0.5 font-medium text-slate-900 dark:text-white">{{ $record->status }}</p>
                </div>
                <div>
                    <p class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Tipo</p>
                    <p class="mt-0.5 font-medium text-slate-900 dark:text-white">{{ $typeLabel }}</p>
                </div>
                <div>
                    <p class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Correo</p>
                    <p class="mt-0.5 break-all text-slate-800 dark:text-slate-100">{{ $record->email ?: '—' }}</p>
                </div>
                <div>
                    <p class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Teléfono</p>
                    <p class="mt-0.5 text-slate-800 dark:text-slate-100">{{ $record->phone ?: '—' }}</p>
                </div>
                <div class="sm:col-span-2">
                    <p class="text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Owner (owner_code)</p>
                    <p class="mt-0.5 font-mono text-sm text-slate-800 dark:text-slate-100">{{ $record->owner_code ?: '—' }}</p>
                </div>
            </div>
        </div>
    </section>

    @if (($canAddObservation ?? false) && is_array($noteTimeline ?? null))
        @include('filament.business.agencies.agency-notes-command-center', [
            'record' => $record,
            'noteTimeline' => $noteTimeline,
        ])
    @endif

    {{-- Ficha PDF --}}
    <section class="rounded-[1.35rem] border border-slate-200/80 bg-white/90 p-5 ring-1 ring-black/[0.04] dark:border-white/10 dark:bg-white/[0.03] dark:ring-white/[0.05]">
        <p class="text-sm font-semibold text-slate-900 dark:text-white">Ficha de agencia (PDF)</p>
        <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
            Vista previa con datos de la agencia y notas internas (más recientes primero). Marca Tu Dr en Casa. Las descargas y envíos quedan auditados.
        </p>
        <div class="mt-4 overflow-hidden rounded-xl border border-slate-200/80 bg-slate-100/80 dark:border-white/10 dark:bg-slate-900/40">
            <iframe
                title="Vista previa ficha de agencia"
                src="{{ $fichaPreviewUrl }}"
                class="h-[min(70vh,520px)] w-full min-h-[320px] border-0"
                loading="lazy"></iframe>
        </div>
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
            <a
                href="{{ $fichaDownloadUrl }}"
                target="_blank"
                rel="noopener"
                class="inline-flex min-h-[2.75rem] flex-1 items-center justify-center gap-2 rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100 sm:min-w-[11rem]">
                Descargar PDF
            </a>
            <a
                href="{{ $fichaWhatsappUrl }}"
                target="_blank"
                rel="noopener"
                class="inline-flex min-h-[2.75rem] flex-1 items-center justify-center gap-2 rounded-2xl border border-emerald-200/90 bg-emerald-50/90 px-4 py-3 text-sm font-semibold text-emerald-950 shadow-sm transition dark:border-emerald-500/30 dark:bg-emerald-950/30 dark:text-emerald-50 sm:min-w-[11rem]">
                WhatsApp (enlace)
            </a>
        </div>
        <div
            class="mt-4 rounded-xl border border-dashed border-slate-200/90 bg-slate-50/60 p-4 dark:border-white/10 dark:bg-slate-900/30"
            x-data="{ email: @js($record->email ?? '') }">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Enviar por correo</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Se encola el envío en segundo plano; el PDF se adjunta al mensaje.</p>
            <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-end">
                <label class="flex-1 text-xs font-medium text-slate-600 dark:text-slate-300">
                    Correo destino
                    <input
                        type="email"
                        x-model="email"
                        autocomplete="email"
                        class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm dark:border-white/10 dark:bg-slate-950 dark:text-white" />
                </label>
                <button
                    type="button"
                    class="inline-flex min-h-[2.5rem] items-center justify-center rounded-2xl bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700 disabled:opacity-50"
                    @click="$wire.queueAgencyFichaPdfEmail({{ (int) $record->getKey() }}, email)">
                    Enviar correo
                </button>
            </div>
        </div>
    </section>
</div>
