<?php

declare(strict_types=1);

it('el reenvío manual de propuesta usa CC a cotizaciones tdg y BCC a solrodriguez', function (string $relativePath): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/'.$relativePath);

    expect($source)
        ->toContain("->cc('cotizacionestdg.ve@gmail.com')")
        ->toContain("->bcc('solrodriguez@tudrencasa.com')")
        ->not->toContain("->cc('solrodriguez@tudrencasa.com')");
})->with([
    'agents individual' => 'app/Filament/Agents/Resources/IndividualQuotes/Tables/IndividualQuotesTable.php',
    'general individual' => 'app/Filament/General/Resources/IndividualQuotes/Tables/IndividualQuotesTable.php',
    'master individual' => 'app/Filament/Master/Resources/IndividualQuotes/Tables/IndividualQuotesTable.php',
    'agents corporate' => 'app/Filament/Agents/Resources/CorporateQuotes/Tables/CorporateQuotesTable.php',
    'general corporate' => 'app/Filament/General/Resources/CorporateQuotes/Tables/CorporateQuotesTable.php',
    'master corporate' => 'app/Filament/Master/Resources/CorporateQuotes/Tables/CorporateQuotesTable.php',
]);
