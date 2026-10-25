{{--
    Tooltip del Dashboard de Operaciones para widgets en Blade (mapa de calor,
    línea de negocio). Lee `tip` del x-data del contenedor, que debe ser `relative`:
    { title, value, unit, swatch, lines: [], footer, x, y }.

    La posición va como objeto de estilos: x-show también escribe en `style` y un
    string lo pisaría. El centrado va en línea (no con clases de translate, que la
    transición reemplazaría) y la transición es solo de opacidad.
--}}
<div
    x-show="tip"
    x-cloak
    x-transition:enter="transition-opacity ease-out duration-150"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition-opacity ease-in duration-100"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    x-bind:style="{ left: (tip ? tip.x : 0) + 'px', top: (tip ? tip.y : 0) + 'px', transform: 'translate(-50%, -100%)' }"
    class="pointer-events-none absolute z-20 w-60 rounded-xl bg-gray-950 px-3.5 py-3 text-white shadow-xl ring-1 ring-white/10 dark:bg-white dark:text-gray-950 dark:ring-gray-950/10"
    role="tooltip"
>
    <p class="text-xs font-medium opacity-70" x-text="tip ? tip.title : ''"></p>
    <div class="mt-1 flex items-center gap-2">
        <span class="size-3 shrink-0 rounded-[3px] ring-1 ring-white/20 dark:ring-gray-950/20" x-show="tip && tip.swatch" x-bind:style="{ background: tip ? tip.swatch : 'transparent' }"></span>
        <span class="text-xl font-semibold tabular-nums" x-text="tip ? tip.value : ''"></span>
        <span class="text-xs opacity-70" x-text="tip ? tip.unit : ''"></span>
    </div>
    <template x-for="(line, index) in (tip ? tip.lines : [])" x-bind:key="index">
        <p class="mt-1 text-xs opacity-80" x-text="line"></p>
    </template>
    <p class="mt-2 border-t border-white/10 pt-1.5 text-[11px] opacity-60 dark:border-gray-950/10" x-show="tip && tip.footer" x-text="tip ? tip.footer : ''"></p>
</div>
