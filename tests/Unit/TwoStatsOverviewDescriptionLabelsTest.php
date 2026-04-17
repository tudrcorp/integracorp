<?php

declare(strict_types=1);

use App\Filament\Business\Widgets\TwoStatsOverview;

uses(Tests\TestCase::class);

it('usa el mismo esquema de descripción que el dashboard de indicadores (año arriba, mes abajo)', function () {
    $method = new \ReflectionMethod(TwoStatsOverview::class, 'descriptionHtml');
    $method->setAccessible(true);

    $html = $method->invoke(null, 2024, 7, 'Julio', 'text-info-600', 'bg-info-100')->toHtml();

    expect($html)->toContain('TOTAL AÑO 2024')
        ->and($html)->toContain('Mes seleccionado (Julio):')
        ->and($html)->toContain('7');
});
