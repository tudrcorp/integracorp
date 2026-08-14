<?php

declare(strict_types=1);

use App\Filament\Business\Resources\AffiliationCorporates\Pages\ListAffiliationCorporates;
use App\Filament\Business\Resources\AffiliationCorporates\Widgets\AffiliationCorporatePorEstadoChart;
use Filament\Support\RawJs;

it('usa torta (pie), sin filtro de año y ancho completo', function (): void {
    $ref = new ReflectionClass(AffiliationCorporatePorEstadoChart::class);

    expect($ref->getDefaultProperties()['columnSpan'] ?? null)->toBe(1);
    expect($ref->getDefaultProperties()['maxHeight'] ?? null)->toBe('360px');
    expect($ref->getMethod('getFilters')->getDeclaringClass()->getName())
        ->not->toBe(AffiliationCorporatePorEstadoChart::class);

    $source = file_get_contents($ref->getFileName());

    expect($source)
        ->not->toContain('whereYear')
        ->toContain("return 'pie'");

    $widget = new AffiliationCorporatePorEstadoChart;

    $type = (new ReflectionMethod(AffiliationCorporatePorEstadoChart::class, 'getType'))->invoke($widget);
    expect($type)->toBe('pie');

    $tablePage = (new ReflectionMethod(AffiliationCorporatePorEstadoChart::class, 'getTablePage'))->invoke($widget);
    expect($tablePage)->toBe(ListAffiliationCorporates::class);

    $options = (new ReflectionMethod(AffiliationCorporatePorEstadoChart::class, 'getOptions'))->invoke($widget);
    expect($options)->toBeInstanceOf(RawJs::class);
});
