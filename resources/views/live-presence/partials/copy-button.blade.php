{{--
    Botón que copia un texto al portapapeles (diagnóstico, comando).
    Espera: $text, $label. Si el navegador no permite el portapapeles, selecciona el texto para copiarlo a mano.
--}}
<span class="lcopy" x-data="{ copied: false }">
    <textarea x-ref="text" readonly aria-hidden="true" tabindex="-1" style="position: absolute; left: -9999px; width: 1px; height: 1px;">{{ $text }}</textarea>
    <button type="button" class="lcopy-btn"
        x-on:click="
            const text = $refs.text.value;
            const done = () => { copied = true; setTimeout(() => copied = false, 2000) };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(done);
            } else {
                $refs.text.select();
                document.execCommand('copy');
                done();
            }
        ">
        <span x-show="! copied">📋 {{ $label }}</span>
        <span x-show="copied" x-cloak>✓ Copiado</span>
    </button>
</span>
