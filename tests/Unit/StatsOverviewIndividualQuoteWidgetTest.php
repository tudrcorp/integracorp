<?php

declare(strict_types=1);

use App\Filament\Business\Resources\IndividualQuotes\Widgets\StatsOverviewIndividualQuote;
use Filament\Widgets\StatsOverviewWidget;

it('define el widget de análisis de cotizaciones individuales emitidas', function (): void {
    expect(class_exists(StatsOverviewIndividualQuote::class))->toBeTrue()
        ->and(is_subclass_of(StatsOverviewIndividualQuote::class, StatsOverviewWidget::class))->toBeTrue();
});

it('incluye conteo de ejecutadas y porcentaje de efectividad', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/IndividualQuotes/Widgets/StatsOverviewIndividualQuote.php';
    $code = file_get_contents($path);

    expect($code)
        ->not->toBeFalse()
        ->toContain('EXECUTED_STATUS')
        ->toContain("'EJECUTADA'")
        ->toContain("->where('status', self::EXECUTED_STATUS)")
        ->toContain('Efectividad')
        ->toContain('Ejecutadas')
        ->toContain('Todo el año')
        ->toContain("View::make('filament.widgets.stats-overview-filters')");
});
