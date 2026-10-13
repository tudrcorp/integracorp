@php
    /**
     * Visor del PDF del generador con aviso de carga: el servidor dibuja el PDF al
     * pedirlo y, mientras tanto, el iframe queda en blanco. El aviso se retira al
     * terminar de cargar (evento `load` del iframe).
     *
     * Estilos en línea a propósito: el tema del panel se compila con Vite y una
     * clase Tailwind nueva no existe hasta el próximo build.
     *
     * @var string $src
     * @var string|null $openUrl
     */
    $openUrl ??= $src;
@endphp

<div
    x-data="{ loaded: false, slow: false }"
    x-init="
        const frame = $refs.frame;
        try {
            if (frame.contentDocument && frame.contentDocument.readyState === 'complete' && ! String(frame.contentDocument.URL).startsWith('about:')) {
                loaded = true;
            }
        } catch (error) {}
        setTimeout(() => { if (! loaded) { slow = true } }, 20000);
    "
    class="overflow-hidden rounded-xl border border-gray-200 bg-gray-100 dark:border-white/10 dark:bg-white/5"
    style="position: relative;"
>
    <iframe
        x-ref="frame"
        x-on:load="loaded = true"
        src="{{ $src }}#view=FitH"
        title="Vista previa del certificado"
        class="block w-full"
        style="height: 88vh; min-height: 760px;"
    ></iframe>

    <div
        x-show="! loaded"
        x-transition.opacity.duration.300ms
        role="status"
        aria-live="polite"
        class="bg-gray-50 dark:bg-gray-900"
        style="position: absolute; inset: 0;"
    >
        {{-- El centrado va en capas internas: x-show quita el `display` del elemento que muestra u oculta. --}}
        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 14px; width: 100%; height: 100%; padding: 24px; box-sizing: border-box; text-align: center;">
            <x-filament::loading-indicator style="display: block; flex-shrink: 0; width: 44px; height: 44px; margin: 0 auto; color: var(--primary-600, #4c566a);" />

            <div style="display: flex; flex-direction: column; align-items: center; gap: 4px; max-width: 420px;">
                <span class="text-gray-900 dark:text-white" style="font-size: 1rem; font-weight: 600;">Preparando el PDF…</span>
                <span class="text-gray-500 dark:text-gray-400" style="font-size: 0.875rem; line-height: 1.5;">
                    El sistema está creando el documento. Puede tardar unos segundos; no cierre ni recargue la página.
                </span>
            </div>

            <div x-show="slow" x-cloak>
                <div class="text-gray-600 dark:text-gray-300" style="display: flex; flex-direction: column; align-items: center; gap: 8px; font-size: 0.85rem;">
                    <span>Está tardando más de lo normal.</span>
                    <x-filament::link :href="$openUrl" target="_blank" rel="noopener" icon="heroicon-o-arrow-top-right-on-square">
                        Abrir el PDF en otra pestaña
                    </x-filament::link>
                </div>
            </div>
        </div>
    </div>
</div>
