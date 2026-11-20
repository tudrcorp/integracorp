{{-- Vista previa del PDF de un diseño de cotización (QuoteDesignResource). --}}
<div class="space-y-3">
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-white/10 dark:bg-white/5">
        <iframe
            src="{{ $url }}#view=FitH"
            title="Vista previa de la propuesta"
            class="block w-full"
            style="height: 75vh;"
        ></iframe>
    </div>

    <p class="text-sm text-gray-500 dark:text-gray-400">
        ¿No se ve el documento?
        <a href="{{ $url }}" target="_blank" rel="noopener" class="font-medium text-primary-600 underline dark:text-primary-400">Ábralo en una pestaña nueva</a>.
        El enlace vence en 30 minutos.
    </p>
</div>
