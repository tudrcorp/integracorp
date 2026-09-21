<?php

declare(strict_types=1);

it('incluye el banco mercantil panama y encore bank en los selects internacionales de negocios, agentes, general y master', function (string $relativePath): void {
    $path = dirname(__DIR__, 2).'/'.$relativePath;

    expect($path)->toBeFile();

    $source = file_get_contents($path);

    expect($source)
        ->toContain("'EL BANCO MERCANTIL PANAMÁ' => 'EL BANCO MERCANTIL PANAMÁ'")
        ->toContain("'ENCORE BANK' => 'ENCORE BANK'");
})->with([
    'app/Filament/Business/Resources/Agents/Schemas/AgentForm.php',
    'app/Filament/Business/Resources/Agencies/Schemas/AgencyForm.php',
    'app/Filament/Business/Resources/TravelAgencies/Schemas/TravelAgencyForm.php',
    'app/Filament/Business/Resources/Affiliations/Tables/AffiliationsTable.php',
    'app/Filament/Business/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php',
    'app/Filament/Agents/Resources/Agents/Schemas/AgentForm.php',
    'app/Filament/Agents/Resources/Affiliations/Tables/AffiliationsTable.php',
    'app/Filament/Agents/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php',
    'app/Filament/General/Resources/Agents/Schemas/AgentForm.php',
    'app/Filament/General/Resources/Agencies/Schemas/AgencyForm.php',
    'app/Filament/General/Resources/Affiliations/Tables/AffiliationsTable.php',
    'app/Filament/General/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php',
    'app/Filament/General/Resources/Sales/Tables/SalesTable.php',
    'app/Filament/Master/Resources/Agents/Schemas/AgentForm.php',
    'app/Filament/Master/Resources/Agencies/Schemas/AgencyForm.php',
    'app/Filament/Master/Resources/Affiliations/Tables/AffiliationsTable.php',
    'app/Filament/Master/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php',
]);
