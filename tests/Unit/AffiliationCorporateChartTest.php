<?php

declare(strict_types=1);

it('grafico de afiliaciones corporativas usa translatedFormat en lugar de translatedMonth', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Widgets/AffiliationCorporateChart.php');

    expect($source)
        ->toContain("->translatedFormat('F')")
        ->not->toContain('translatedMonth');
});
