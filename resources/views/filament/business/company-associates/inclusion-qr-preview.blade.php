@props([
    'associate' => null,
    'planLabel' => 'INCLUSIÓN',
    'qrPreviewUrl' => null,
    'pdfDestinationUrl' => null,
])

<div class="fi-scoped space-y-4">
    <div class="overflow-hidden rounded-3xl border border-gray-200/80 bg-white/80 shadow-sm backdrop-blur-md dark:border-white/10 dark:bg-gray-900/70">
        <div class="flex items-center justify-between gap-3 border-b border-gray-200/80 px-4 py-3 dark:border-white/10">
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-400 dark:text-gray-500">Plan {{ $planLabel }}</p>
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                    {{ $associate?->full_name ?? 'Asociado' }}
                    @if (filled($associate?->identity_card))
                        <span class="font-normal text-gray-500 dark:text-gray-400">· {{ $associate->identity_card }}</span>
                    @endif
                </p>
            </div>
            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-700 dark:bg-amber-500/20 dark:text-amber-300">
                QR
            </span>
        </div>

        @if (filled($qrPreviewUrl))
            <div class="flex flex-col items-center gap-4 bg-gray-50/80 px-4 py-6 dark:bg-gray-950/60">
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <img
                        src="{{ $qrPreviewUrl }}"
                        alt="Código QR del plan {{ $planLabel }}"
                        class="mx-auto h-auto w-[min(280px,70vw)] max-w-full"
                        loading="lazy"
                    >
                </div>

                <p class="max-w-md text-center text-sm text-gray-600 dark:text-gray-300">
                    Apunte la cámara de su teléfono al código. Debe abrir el documento de canales de comunicación.
                </p>

                @if (filled($pdfDestinationUrl))
                    <p class="max-w-lg break-all text-center text-xs text-gray-500 dark:text-gray-400">
                        Destino: <span class="font-medium text-gray-700 dark:text-gray-200">{{ $pdfDestinationUrl }}</span>
                    </p>
                @endif
            </div>

            <div class="flex flex-wrap items-center justify-center gap-2 px-4 py-3">
                @if (filled($pdfDestinationUrl))
                    <a
                        href="{{ $pdfDestinationUrl }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center rounded-full bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-sky-500"
                    >
                        Abrir PDF destino
                    </a>
                @endif
                <a
                    href="{{ $qrPreviewUrl }}"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center rounded-full border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                >
                    Abrir imagen QR
                </a>
            </div>
        @else
            <div class="px-4 py-10 text-center">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-200">QR no disponible</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    El código QR del plan INCLUSIÓN aún no ha sido generado. Use el generador QR de nuevos negocios.
                </p>
            </div>
        @endif
    </div>
</div>
