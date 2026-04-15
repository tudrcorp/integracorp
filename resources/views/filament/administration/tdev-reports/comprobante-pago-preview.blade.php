@php
    $ext = filled($path ?? null) ? strtolower(pathinfo((string) $path, PATHINFO_EXTENSION)) : '';
    $isPdf = $ext === 'pdf';
@endphp

<div class="space-y-4">
    @if (blank($url ?? null))
        <p class="text-sm text-gray-500 dark:text-gray-400">
            No hay comprobante disponible.
        </p>
    @elseif ($isPdf)
        <iframe
            src="{{ $url }}"
            class="fi-input-wrp w-full min-h-[70vh] rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-950"
            title="Vista previa del comprobante (PDF)"
        ></iframe>
        <a
            href="{{ $url }}"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex text-sm font-semibold text-primary-600 underline decoration-primary-600/30 underline-offset-2 hover:text-primary-500 dark:text-primary-400"
        >
            Abrir PDF en una pestaña nueva
        </a>
    @else
        <div
            class="flex justify-center overflow-auto rounded-2xl border border-gray-200 bg-gray-50/90 p-4 shadow-inner dark:border-white/10 dark:bg-gray-900/40"
        >
            <img
                src="{{ $url }}"
                alt="Comprobante de pago"
                class="max-h-[75vh] max-w-full rounded-xl object-contain shadow-md ring-1 ring-black/5 dark:ring-white/10"
                loading="lazy"
            />
        </div>
        <a
            href="{{ $url }}"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex text-sm font-semibold text-primary-600 underline decoration-primary-600/30 underline-offset-2 hover:text-primary-500 dark:text-primary-400"
        >
            Abrir imagen en una pestaña nueva
        </a>
    @endif
</div>
