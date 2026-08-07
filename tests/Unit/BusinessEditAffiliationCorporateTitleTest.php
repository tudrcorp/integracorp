<?php

declare(strict_types=1);

it('personaliza el título de edición corporativa sin la palabra Editar', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/Pages/EditAffiliationCorporate.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain('public function getTitle(): string|Htmlable')
        ->toContain('name_corporate')
        ->toContain('RIF/CI:')
        ->toContain('Plan(es):')
        ->toContain('afiliados corporativos')
        ->toContain('affiliationCorporatePlans')
        ->toContain('corporateAffiliates()')
        ->toContain('plan.description')
        ->not->toContain("'Editar")
        ->not->toContain('"Editar');
});
