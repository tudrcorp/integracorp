<?php

declare(strict_types=1);

use App\Filament\Operations\Widgets\AffiliationChart;

it('puede comprobarse la visibilidad con canView() estática', function (): void {
    expect(AffiliationChart::canView())->toBeTrue();
});
