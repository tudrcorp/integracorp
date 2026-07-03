<?php

declare(strict_types=1);

use App\Support\AffiliationCorporates\CorporateAffiliateVoucherIlsUpdater;
use Carbon\Carbon;

it('calcula los dias de vigencia entre fechas en formato d/m/Y', function (): void {
    expect(CorporateAffiliateVoucherIlsUpdater::calculateNumberDays('01/01/2026', '01/07/2026'))->toBe(181);
});

it('calcula los dias de vigencia cuando el date picker devuelve carbon', function (): void {
    $dateInit = Carbon::parse('2026-01-01');
    $dateEnd = Carbon::parse('2026-07-01');

    expect(CorporateAffiliateVoucherIlsUpdater::calculateNumberDays($dateInit, $dateEnd))->toBe(181);
});

it('centraliza la logica de voucher ils para afiliados corporativos', function (): void {
    $updater = file_get_contents(dirname(__DIR__, 2).'/app/Support/AffiliationCorporates/CorporateAffiliateVoucherIlsUpdater.php');
    $relationManager = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/AffiliationCorporates/RelationManagers/CorporateAffiliatesRelationManager.php');

    expect($updater)
        ->toContain('resolveDocumentPath')
        ->toContain('formatDateForStorage')
        ->toContain('numberDays');

    expect($relationManager)
        ->toContain('CorporateAffiliateVoucherIlsUpdater::save')
        ->toContain("->directory('vauches')");
});
