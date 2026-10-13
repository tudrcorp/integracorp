<x-filament-panels::page>
    {{ $this->content }}

    @php
        $issue = $this->currentIssue();
        $canRetry = method_exists($this, 'retry');
        $fullPreviewMax = \App\Services\AffiliationCertificateGeneratorService::FULL_PREVIEW_MAX_PAGES;
        $lightUrl = method_exists($this, 'lightPreviewUrl') ? $this->lightPreviewUrl() : null;
        $pages = $issue?->pdf_pages ?: ($issue ? \App\Services\AffiliationCertificateGeneratorService::estimatedPages((int) $issue->affiliates_count, (int) $issue->carnets_count) : 0);
    @endphp

    @if ($issue?->pdf_status === \App\Models\AffiliationCertificateIssue::PDF_PROCESSING && ! $issue->isStalled())
        @php
            $percent = $issue->progressPercent();
        @endphp
        <x-filament::section icon="heroicon-o-clock" wire:poll.4s wire:key="certificate-processing-{{ $this->issueId }}">
            <x-slot name="heading">Generando el certificado…</x-slot>
            <x-slot name="description">
                {{ $issue->affiliation_code }} · {{ number_format((int) $issue->carnets_count, 0, ',', '.') }} carnets · {{ number_format((int) $pages, 0, ',', '.') }} páginas. Se prepara en segundo plano: puede seguir trabajando y le llegará una notificación cuando esté listo.
            </x-slot>

            <div class="flex flex-col gap-2">
                <div class="flex items-center justify-between text-sm text-gray-700 dark:text-gray-200">
                    <span class="inline-flex items-center gap-2">
                        <x-filament::loading-indicator class="h-4 w-4" />
                        @if ((int) $issue->pdf_chunks_total > 0 && (int) $issue->pdf_chunks_done >= (int) $issue->pdf_chunks_total)
                            Uniendo las partes en un solo PDF…
                        @elseif ((int) $issue->pdf_chunks_total > 0)
                            Parte {{ min((int) $issue->pdf_chunks_done + 1, (int) $issue->pdf_chunks_total) }} de {{ $issue->pdf_chunks_total }}
                        @else
                            En cola…
                        @endif
                    </span>
                    <span class="font-semibold tabular-nums">{{ $percent }} %</span>
                </div>
                <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-white/10" role="progressbar" aria-label="Avance del certificado" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}">
                    <div class="h-2 rounded-full bg-primary-600 transition-all duration-500" style="width: {{ max(3, $percent) }}%;"></div>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">Esta sección se actualiza sola.</p>
            </div>
        </x-filament::section>

        @if ($lightUrl)
            {{-- Fuera de la sección que se refresca: el iframe no se recarga en cada actualización del avance. --}}
            <x-filament::section icon="heroicon-o-eye" wire:key="certificate-light-preview-{{ $this->issueId }}">
                <x-slot name="heading">Vista previa</x-slot>
                <x-slot name="description">Portada y primera hoja de carnets, mientras se prepara el documento completo de {{ number_format((int) $pages, 0, ',', '.') }} páginas.</x-slot>
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-gray-100 dark:border-white/10 dark:bg-white/5">
                    <iframe src="{{ $lightUrl }}#view=FitH" title="Vista previa del certificado" class="block w-full" style="height: 88vh; min-height: 760px;" loading="lazy"></iframe>
                </div>
            </x-filament::section>
        @endif
    @elseif ($issue?->isStalled())
        <x-filament::section icon="heroicon-o-pause-circle" icon-color="warning" wire:poll.15s wire:key="certificate-stalled-{{ $this->issueId }}">
            <x-slot name="heading">El certificado no avanza</x-slot>
            <x-slot name="description">
                {{ $issue->affiliation_code }} lleva más de {{ \App\Models\AffiliationCertificateIssue::STALLED_AFTER_MINUTES }} minutos sin progreso ({{ $issue->progressPercent() }} % listo). Puede que el servidor de documentos esté detenido. Al reintentar se conservan las partes ya listas.
            </x-slot>
            @if ($canRetry)
                <x-filament::button wire:click="retry" wire:loading.attr="disabled" wire:target="retry" icon="heroicon-o-arrow-path" color="warning">
                    <span wire:loading.remove wire:target="retry">Reintentar</span>
                    <span wire:loading wire:target="retry">Reanudando…</span>
                </x-filament::button>
            @endif
        </x-filament::section>
    @elseif ($issue?->pdf_status === \App\Models\AffiliationCertificateIssue::PDF_FAILED)
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="danger">
            <x-slot name="heading">No se pudo generar el certificado</x-slot>
            <x-slot name="description">
                {{ $issue->affiliation_code }} · {{ $issue->progressPercent() }} % listo antes del error.
                {{ $canRetry ? 'Al reintentar se conservan las partes ya listas.' : 'Vuelva a pulsar «Generar certificado».' }}
                Si el problema continúa, reporte a soporte indicando {{ $issue->affiliation_code }}.
            </x-slot>
            @if ($canRetry)
                <x-filament::button wire:click="retry" wire:loading.attr="disabled" wire:target="retry" icon="heroicon-o-arrow-path" color="danger">
                    <span wire:loading.remove wire:target="retry">Reintentar</span>
                    <span wire:loading wire:target="retry">Reanudando…</span>
                </x-filament::button>
            @endif
        </x-filament::section>
    @elseif ($url = $this->previewUrl())
        <x-filament::section icon="heroicon-o-document-check" wire:key="certificate-preview-{{ $this->issueId }}">
            <x-slot name="heading">{{ $pages > $fullPreviewMax ? 'Certificado listo' : 'Vista previa' }}</x-slot>
            <x-slot name="description">
                {{ $issue?->affiliation_code }} · {{ number_format((int) $pages, 0, ',', '.') }} páginas · clave de verificación {{ $issue?->verification_key }}.
                {{ $pages > $fullPreviewMax ? 'La vista previa muestra la portada y la primera hoja de carnets; el PDF descargado trae todas las páginas.' : 'Es el mismo PDF que se descarga.' }}
            </x-slot>
            <x-slot name="afterHeader">
                <div class="flex flex-wrap gap-2">
                    <x-filament::button tag="a" :href="$this->downloadUrl()" icon="heroicon-o-arrow-down-tray" color="success">
                        Descargar PDF
                    </x-filament::button>
                    <x-filament::button tag="a" :href="$url" target="_blank" rel="noopener" icon="heroicon-o-arrow-top-right-on-square" color="gray">
                        Abrir en otra pestaña
                    </x-filament::button>
                </div>
            </x-slot>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-gray-100 dark:border-white/10 dark:bg-white/5">
                <iframe src="{{ ($lightUrl ?? $url) }}#view=FitH" title="Vista previa del certificado" class="block w-full" style="height: 88vh; min-height: 760px;" loading="lazy"></iframe>
            </div>
        </x-filament::section>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
