<?php

declare(strict_types=1);

use App\Filament\Business\Resources\CorporateQuotes\Widgets\StatsOverviewCorporateQuote;
use Filament\Widgets\StatsOverviewWidget;

it('define el widget de analisis de cotizaciones corporativas emitidas', function (): void {
    expect(class_exists(StatsOverviewCorporateQuote::class))->toBeTrue()
        ->and(is_subclass_of(StatsOverviewCorporateQuote::class, StatsOverviewWidget::class))->toBeTrue();
});

it('incluye conteo de ejecutadas y porcentaje de efectividad en corporativas', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/CorporateQuotes/Widgets/StatsOverviewCorporateQuote.php';
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
