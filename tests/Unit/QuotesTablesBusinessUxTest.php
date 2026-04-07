<?php

declare(strict_types=1);

it('incluye mejoras de UX en las tablas de cotizaciones del panel business', function (): void {
    $individualPath = __DIR__.'/../../app/Filament/Business/Resources/IndividualQuotes/Tables/IndividualQuotesTable.php';
    $corporatePath = __DIR__.'/../../app/Filament/Business/Resources/CorporateQuotes/Tables/CorporateQuotesTable.php';

    $individual = file_get_contents($individualPath);
    $corporate = file_get_contents($corporatePath);

    expect($individual)->not->toBeFalse()
        ->toContain('emptyStateHeading')
        ->toContain('paginationPageOptions')
        ->toContain('deferFilters(false)')
        ->toContain('modifyQueryUsing')
        ->not->toContain('dd($th)');

    expect($corporate)->not->toBeFalse()
        ->toContain('emptyStateHeading')
        ->toContain('paginationPageOptions')
        ->toContain('deferFilters(false)')
        ->toContain('modifyQueryUsing')
        ->not->toContain('dd($th)');
});
