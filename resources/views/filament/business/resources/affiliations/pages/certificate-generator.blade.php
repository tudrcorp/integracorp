<x-filament-panels::page>
    {{ $this->content }}

    @php($issue = $this->currentIssue())

    @if ($issue?->pdf_status === \App\Models\AffiliationCertificateIssue::PDF_PROCESSING)
        <x-filament::section icon="heroicon-o-clock" wire:poll.5s wire:key="certificate-processing-{{ $this->issueId }}">
            <x-slot name="heading">Generando el certificado…</x-slot>
            <x-slot name="description">
                {{ $issue->affiliation_code }} · {{ number_format($issue->carnets_count, 0, ',', '.') }} carnets. Un documento de este tamaño se prepara en segundo plano: puede seguir trabajando y le llegará una notificación cuando esté listo.
            </x-slot>
            <div class="flex items-center gap-3 text-sm text-gray-600 dark:text-gray-300">
                <x-filament::loading-indicator class="h-5 w-5" />
                Esta sección se actualiza sola.
            </div>
        </x-filament::section>
    @elseif ($issue?->pdf_status === \App\Models\AffiliationCertificateIssue::PDF_FAILED)
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="danger">
            <x-slot name="heading">No se pudo generar el certificado</x-slot>
            <x-slot name="description">Vuelva a pulsar «Generar certificado». Si el problema continúa, reporte a soporte indicando {{ $issue->affiliation_code }}.</x-slot>
        </x-filament::section>
    @elseif ($url = $this->previewUrl())
        <x-filament::section icon="heroicon-o-eye" wire:key="certificate-preview-{{ $this->issueId }}">
            <x-slot name="heading">Vista previa</x-slot>
            <x-slot name="description">
                {{ $issue?->affiliation_code }} · clave de verificación {{ $issue?->verification_key }}. Es el mismo PDF que se descarga.
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
                <iframe src="{{ $url }}#view=FitH" title="Vista previa del certificado" class="block w-full" style="height: 88vh; min-height: 760px;" loading="lazy"></iframe>
            </div>
        </x-filament::section>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
