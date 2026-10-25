{{--
    Menú superior del panel de Métricas: una barra de pestañas compacta en una
    sola línea (sin saltos), con la página activa resaltada.
    Todo va bajo `.fi-panel-metrics`: con la navegación SPA estos estilos siguen en
    la página al pasar a otro panel y no deben afectarlo.
--}}
<style>
    .fi-panel-metrics .fi-topbar-nav-groups {
        justify-content: flex-start !important;
        /* Pegado a la derecha, junto al avatar: el espacio libre queda al lado del logo. */
        margin-inline-start: auto !important;
        margin-inline-end: 12px !important;
        flex-wrap: nowrap !important;
        gap: 2px !important;
        margin-block: 0 !important;
        padding: 4px;
        border-radius: 9999px;
        background: rgb(15 23 42 / .04);
        box-shadow: inset 0 0 0 1px rgb(15 23 42 / .06);
        overflow-x: auto;
        scrollbar-width: none;
        max-width: 100%;
    }

    /* Si el bloque del avatar también empuja con margen automático, el espacio se reparte y el menú queda al centro. */
    .fi-panel-metrics .fi-topbar-end {
        margin-inline-start: 0 !important;
    }

    .fi-panel-metrics .fi-topbar-nav-groups::-webkit-scrollbar {
        display: none;
    }

    .dark .fi-panel-metrics .fi-topbar-nav-groups {
        background: rgb(255 255 255 / .04);
        box-shadow: inset 0 0 0 1px rgb(255 255 255 / .08);
    }

    .fi-panel-metrics .fi-topbar-nav-groups .fi-topbar-item-btn {
        padding: 6px 12px !important;
        border-radius: 9999px !important;
        white-space: nowrap;
        transition: background-color 150ms ease, box-shadow 150ms ease, color 150ms ease;
    }

    .fi-panel-metrics .fi-topbar-nav-groups .fi-topbar-item-btn:hover {
        background: rgb(15 23 42 / .05);
    }

    .dark .fi-panel-metrics .fi-topbar-nav-groups .fi-topbar-item-btn:hover {
        background: rgb(255 255 255 / .06);
    }

    .fi-panel-metrics .fi-topbar-nav-groups .fi-topbar-item-label {
        font-size: 13px;
    }

    .fi-panel-metrics .fi-topbar-nav-groups .fi-topbar-item-btn > .fi-icon {
        width: 16px;
        height: 16px;
    }

    .fi-panel-metrics .fi-topbar-nav-groups .fi-topbar-item.fi-active .fi-topbar-item-btn {
        background: #fff;
        box-shadow: 0 1px 2px rgb(15 23 42 / .08), 0 0 0 1px rgb(15 23 42 / .06);
    }

    .dark .fi-panel-metrics .fi-topbar-nav-groups .fi-topbar-item.fi-active .fi-topbar-item-btn {
        background: rgb(255 255 255 / .10);
        box-shadow: 0 0 0 1px rgb(255 255 255 / .10);
    }

    /* «Volver a Negocios»: salida del panel, en ámbar y separada de las páginas. */
    .fi-panel-metrics .fi-topbar-nav-groups .corp-nav-back {
        margin-inline-end: 6px;
        padding-inline-end: 8px;
        border-inline-end: 1px solid rgb(15 23 42 / .10);
    }

    .dark .fi-panel-metrics .fi-topbar-nav-groups .corp-nav-back {
        border-inline-end-color: rgb(255 255 255 / .12);
    }

    .fi-panel-metrics .fi-topbar-nav-groups .corp-nav-back .fi-topbar-item-btn {
        background: rgb(245 158 11 / .12);
        box-shadow: inset 0 0 0 1px rgb(245 158 11 / .35);
    }

    .fi-panel-metrics .fi-topbar-nav-groups .corp-nav-back .fi-topbar-item-btn:hover {
        background: rgb(245 158 11 / .20);
    }

    .fi-panel-metrics .fi-topbar-nav-groups .corp-nav-back .fi-topbar-item-label,
    .fi-panel-metrics .fi-topbar-nav-groups .corp-nav-back .fi-topbar-item-btn > .fi-icon {
        color: #b45309;
    }

    .dark .fi-panel-metrics .fi-topbar-nav-groups .corp-nav-back .fi-topbar-item-label,
    .dark .fi-panel-metrics .fi-topbar-nav-groups .corp-nav-back .fi-topbar-item-btn > .fi-icon {
        color: #fbbf24;
    }

    /* Pantallas medianas: sin íconos, para que todo quepa en una línea. */
    @media (max-width: 1535px) {
        .fi-panel-metrics .fi-topbar-nav-groups .fi-topbar-item:not(.corp-nav-back) .fi-topbar-item-btn > .fi-icon {
            display: none;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .fi-panel-metrics .fi-topbar-nav-groups .fi-topbar-item-btn { transition: none; }
    }
</style>
