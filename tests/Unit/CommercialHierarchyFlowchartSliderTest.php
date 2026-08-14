<?php

declare(strict_types=1);

it('el slider de jerarquía se desplaza con el control horizontal y no se sale del contenedor', function (): void {
    $flowchartPath = dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/CommercialHierarchyFlowchart.php';
    $cssPath = dirname(__DIR__, 2).'/resources/css/filament/shared/hierarchy-flowchart.css';

    $flowchartSource = file_get_contents($flowchartPath);
    $cssSource = file_get_contents($cssPath);

    expect($flowchartSource)->not->toBeFalse();
    expect($cssSource)->not->toBeFalse();

    expect($flowchartSource)
        ->toContain('getSlideOffset')
        ->toContain('getBoundingClientRect()')
        ->toContain('scrollWidth')
        ->toContain('canScrollNext = el.scrollLeft < (maxScroll - 1)')
        ->toContain('scrollToSlide(el, Math.min(last, current + 1), true, false)')
        ->toContain('scrollToSlide(el, Math.max(0, current - 1), true, false)')
        ->toContain('x-init="$nextTick(() => initSlider($refs.viewport))"')
        ->toContain('@click.stop="scrollNext($refs.viewport)"')
        ->toContain('@click.stop="scrollPrev($refs.viewport)"')
        ->not->toContain('if (center === undefined) { center = true; }')
        ->not->toContain('slide.offsetLeft - el.scrollLeft');

    expect($cssSource)
        ->toContain('.tdg-hierarchy-flowchart-shell__canvas')
        ->toContain('.tdg-hierarchy-flowchart__tier-body')
        ->toContain('justify-content: flex-start')
        ->toContain('overscroll-behavior-x: contain')
        ->toContain('flex-wrap: nowrap')
        ->not->toContain('.tdg-hierarchy-flowchart__tier--general .tdg-hierarchy-slider__track {
    min-width: 100%;
    justify-content: center;
  }');

    expect($cssSource)
        ->toContain('.tdg-hierarchy-slider {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    overflow: hidden;
    container-type: inline-size;
  }')
        ->not->toContain('container-type: inline-size;
    scroll-snap-type: x mandatory;');
});
