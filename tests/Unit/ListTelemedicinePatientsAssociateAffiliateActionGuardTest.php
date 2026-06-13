<?php

declare(strict_types=1);

it('asociar afiliado usa find y evita acceso a índice 0 cuando no hay filas', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicinePatients/Pages/ListTelemedicinePatients.php';
    $contents = file_get_contents($path);
    expect($contents)->not->toBeFalse();

    expect($contents)
        ->not->toContain('->get()->toArray();')
        ->and($contents)->toContain('Affiliate::query()')
        ->and($contents)->toContain("->with('affiliation')")
        ->and($contents)->toContain("->find(\$data['affiliate_id'] ?? null)")
        ->and($contents)->toContain('AssociateAffiliateWithTelemedicinePatientService::run')
        ->and($contents)->toContain('if ($affiliate === null)')
        ->and($contents)->toContain('AffiliateCorporate::query()')
        ->and($contents)->toContain("->with('affiliationCorporate')")
        ->and($contents)->toContain("->find(\$data['affiliate_corporate_id'] ?? null)")
        ->and($contents)->toContain('AssociateAffiliateCorporateWithTelemedicinePatientService::run')
        ->and($contents)->toContain('if ($member === null)');
});
