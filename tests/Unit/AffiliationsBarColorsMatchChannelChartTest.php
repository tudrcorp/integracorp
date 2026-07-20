<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Affiliations\Widgets\AffiliationChart;
use App\Filament\Business\Resources\Affiliations\Widgets\TotalAfiliacionesPorEstado;
use App\Filament\Business\Resources\CorporateQuoteRequests\Widgets\CorporateQuoteRequestChannelChart;

it('usa la misma paleta de barras que CorporateQuoteRequestChannelChart', function (): void {
    $expected = (new \ReflectionMethod(CorporateQuoteRequestChannelChart::class, 'getBarColors'))
        ->invoke(app(CorporateQuoteRequestChannelChart::class));

    $affiliationColors = (new \ReflectionMethod(AffiliationChart::class, 'getBarColors'))
        ->invoke(app(AffiliationChart::class));

    $estadoColors = (new \ReflectionMethod(TotalAfiliacionesPorEstado::class, 'getBarColors'))
        ->invoke(app(TotalAfiliacionesPorEstado::class));

    expect($affiliationColors)->toBe($expected)
        ->and($estadoColors)->toBe($expected)
        ->and($expected)->toContain('#38bdf8')
        ->and($expected)->toContain('#0e7490');
});
