{{-- Vista previa del comprobante de un pago (ManageAffiliationPayments). --}}
<div class="space-y-3">
    @if (! $exists)
        <div class="rounded-xl border border-danger-300 bg-danger-50 p-4 text-sm text-danger-800 dark:border-danger-500/40 dark:bg-danger-500/10 dark:text-danger-200">
            <p class="font-semibold">No se encontró el archivo de este comprobante.</p>
            <p class="mt-1">El pago tiene un comprobante registrado, pero el archivo ya no está en el servidor. Solicite al área de Administración que lo vuelva a cargar.</p>
        </div>
    @elseif ($kind === 'image')
        <div class="flex justify-center rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-white/5">
            <img src="{{ $url }}" alt="Comprobante de pago" class="rounded-lg" style="max-height: 70vh; width: auto; max-width: 100%; object-fit: contain;" loading="lazy">
        </div>
    @elseif ($kind === 'pdf')
        <iframe src="{{ $url }}" title="Comprobante de pago" class="w-full rounded-xl border border-gray-200 dark:border-white/10" style="height: 70vh;"></iframe>
    @else
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-200">
            <p class="font-semibold">Este tipo de archivo no tiene vista previa.</p>
            <p class="mt-1">Use «Descargar» para abrirlo en su equipo.</p>
        </div>
    @endif

    @if ($exists)
        <p class="text-xs text-gray-500 dark:text-gray-400">Archivo: {{ $name }}</p>
    @endif
</div>
