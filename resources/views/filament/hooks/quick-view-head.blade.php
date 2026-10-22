{{--
    Vista rápida (lado incrustado). Corre antes de pintar: si esta página vive dentro del marco
    de la vista rápida del mismo sistema, marca <html> para mostrar solo el contenido.
--}}
<script>
    (function () {
        try {
            var frame = window.frameElement;

            if (window.self !== window.top && frame && frame.hasAttribute('data-ic-quick-view')) {
                document.documentElement.classList.add('ic-qv-embedded');
            }
        } catch (error) {
            /* Marco de otro origen: no es la vista rápida. */
        }
    })();
</script>
<style>
    html.ic-qv-embedded .fi-topbar-ctn,
    html.ic-qv-embedded .fi-sidebar,
    html.ic-qv-embedded .fi-sidebar-close-overlay,
    html.ic-qv-embedded .fi-layout-sidebar-toggle-btn-ctn,
    html.ic-qv-embedded [wire\:key="business-helpdesk-tickets-ticker-root"] {
        display: none !important;
    }

    html.ic-qv-embedded .fi-main-ctn {
        min-height: 100dvh !important;
    }

    html.ic-qv-embedded .fi-main {
        padding-top: 0.75rem;
    }
</style>
