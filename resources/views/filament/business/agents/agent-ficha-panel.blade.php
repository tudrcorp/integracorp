@php
    /** @var \App\Models\Agent $record */
    use App\Services\AgentFichaPdfService;

    $record->loadMissing(['typeAgent']);
    $codeLabel = AgentFichaPdfService::codeLabel($record);
    $fichaPreviewUrl = route('business.agents.ficha-pdf.preview', $record);
    $fichaDownloadUrl = route('business.agents.ficha-pdf.download', $record);
@endphp

<section
    class="rounded-[1.35rem] border border-slate-200/80 bg-white/90 p-5 ring-1 ring-black/[0.04] dark:border-white/10 dark:bg-white/[0.03] dark:ring-white/[0.05]">
    <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-sm font-semibold text-slate-900 dark:text-white">Ficha de agente (PDF)</p>
            <p class="mt-1 max-w-2xl text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                Vista previa con datos del agente y notas internas (más recientes primero). Marca Tu Dr en Casa.
                Las descargas y envíos quedan auditados.
            </p>
        </div>
        <span
            class="mt-2 inline-flex shrink-0 items-center rounded-full bg-emerald-100 px-2.5 py-1 text-[0.65rem] font-semibold uppercase tracking-wide text-emerald-900 dark:bg-emerald-500/20 dark:text-emerald-100 sm:mt-0">
            {{ $codeLabel }}
        </span>
    </div>

    <div
        class="relative mt-4 overflow-hidden rounded-xl border border-slate-200/80 bg-slate-100/80 dark:border-white/10 dark:bg-slate-900/40"
        x-data="{ pdfPreviewLoaded: false }">
        <div
            x-show="!pdfPreviewLoaded"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-slate-100/95 px-4 text-center dark:bg-slate-900/95"
            role="status"
            aria-live="polite">
            <svg
                class="h-10 w-10 shrink-0 animate-spin text-emerald-600 dark:text-emerald-400"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Generando vista previa del PDF</p>
            <p class="max-w-xs text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                El documento se está preparando; en breve podrá verlo aquí. Puede tardar unos segundos.
            </p>
        </div>
        <iframe
            title="Vista previa ficha de agente"
            src="{{ $fichaPreviewUrl }}"
            class="relative z-0 h-[min(70vh,520px)] w-full min-h-[320px] border-0"
            @load="pdfPreviewLoaded = true"></iframe>
    </div>

    <div class="mt-4">
        <a
            href="{{ $fichaDownloadUrl }}"
            target="_blank"
            rel="noopener"
            class="inline-flex min-h-[2.75rem] w-full items-center justify-center gap-2 rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M4 12V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v5" />
            </svg>
            Descargar PDF
        </a>
    </div>

    <div class="mt-5 grid gap-4 lg:grid-cols-2">
        <div
            class="rounded-xl border border-dashed border-sky-200/90 bg-sky-50/50 p-4 dark:border-sky-500/20 dark:bg-sky-950/20"
            x-data="{ email: @js($record->email ?? '') }">
            <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-sky-800 dark:text-sky-200">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                Enviar por correo
            </p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Se encola el envío en segundo plano; el PDF se adjunta al mensaje.
            </p>
            <label class="mt-3 block text-xs font-medium text-slate-600 dark:text-slate-300">
                Correo destino
                <input
                    type="email"
                    x-model="email"
                    autocomplete="email"
                    placeholder="ejemplo@empresa.com"
                    class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-400/30 dark:border-white/10 dark:bg-slate-950 dark:text-white" />
            </label>
            <button
                type="button"
                class="mt-3 inline-flex min-h-[2.5rem] w-full items-center justify-center gap-2 rounded-2xl bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700 active:scale-[0.98] disabled:opacity-50"
                @click="$wire.queueAgentFichaPdfEmail({{ (int) $record->getKey() }}, email)">
                Enviar correo
            </button>
        </div>

        <div
            class="rounded-xl border border-dashed border-emerald-200/90 bg-emerald-50/50 p-4 dark:border-emerald-500/20 dark:bg-emerald-950/20"
            x-data="{ phone: @js($record->phone ?? '') }">
            <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-emerald-900 dark:text-emerald-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                    <path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492a.75.75 0 00.918.918l4.458-1.495A11.945 11.945 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.75c-2.17 0-4.207-.6-5.947-1.64l-.425-.253-2.642.886.886-2.642-.253-.425A9.712 9.712 0 012.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75z"/>
                </svg>
                Enviar por WhatsApp
            </p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Envía el PDF adjunto por la API de WhatsApp al número indicado por el analista.
            </p>
            <label class="mt-3 block text-xs font-medium text-slate-600 dark:text-slate-300">
                Número WhatsApp
                <input
                    type="tel"
                    x-model="phone"
                    autocomplete="tel"
                    placeholder="04127018390 o +584121234567"
                    class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-400/30 dark:border-white/10 dark:bg-slate-950 dark:text-white" />
            </label>
            <button
                type="button"
                wire:loading.attr="disabled"
                wire:target="queueAgentFichaPdfWhatsApp"
                class="mt-3 inline-flex min-h-[2.5rem] w-full items-center justify-center gap-2 rounded-2xl border border-emerald-300/90 bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 active:scale-[0.98] disabled:opacity-50 dark:border-emerald-500/40"
                @click="$wire.queueAgentFichaPdfWhatsApp({{ (int) $record->getKey() }}, phone)">
                <span wire:loading.remove wire:target="queueAgentFichaPdfWhatsApp">Enviar WhatsApp</span>
                <span wire:loading wire:target="queueAgentFichaPdfWhatsApp">Enviando…</span>
            </button>
        </div>
    </div>
</section>
