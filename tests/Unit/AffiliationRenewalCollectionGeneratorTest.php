<?php

declare(strict_types=1);

use App\Services\AffiliationRenewalCollectionGenerator;
use Carbon\Carbon;

it('calcula cuatro fechas trimestrales desde la nueva vigencia', function (): void {
    $effectiveDate = Carbon::create(2026, 6, 19)->startOfDay();

    $dates = AffiliationRenewalCollectionGenerator::upcomingPaymentDates($effectiveDate, 'TRIMESTRAL');

    expect($dates)->toHaveCount(4)
        ->and($dates[0]->format('d/m/Y'))->toBe('19/09/2026')
        ->and($dates[1]->format('d/m/Y'))->toBe('19/12/2026')
        ->and($dates[2]->format('d/m/Y'))->toBe('19/03/2027')
        ->and($dates[3]->format('d/m/Y'))->toBe('19/06/2027');
});

it('calcula fechas segun frecuencia de pago', function (): void {
    $effectiveDate = Carbon::create(2026, 1, 15)->startOfDay();

    expect(AffiliationRenewalCollectionGenerator::upcomingPaymentDates($effectiveDate, 'ANUAL'))
        ->toHaveCount(1)
        ->and(AffiliationRenewalCollectionGenerator::upcomingPaymentDates($effectiveDate, 'ANUAL')[0]->format('d/m/Y'))
        ->toBe('15/01/2027');

    expect(AffiliationRenewalCollectionGenerator::upcomingPaymentDates($effectiveDate, 'SEMESTRAL'))
        ->toHaveCount(2)
        ->and(AffiliationRenewalCollectionGenerator::upcomingPaymentDates($effectiveDate, 'SEMESTRAL')[0]->format('d/m/Y'))
        ->toBe('15/07/2026')
        ->and(AffiliationRenewalCollectionGenerator::upcomingPaymentDates($effectiveDate, 'SEMESTRAL')[1]->format('d/m/Y'))
        ->toBe('15/01/2027');

    expect(AffiliationRenewalCollectionGenerator::upcomingPaymentDates($effectiveDate, 'MENSUAL'))
        ->toHaveCount(12)
        ->and(AffiliationRenewalCollectionGenerator::upcomingPaymentDates($effectiveDate, 'MENSUAL')[0]->format('d/m/Y'))
        ->toBe('15/02/2026');
});

it('integra generador de cobranzas al aceptar renovacion', function (): void {
    $acceptService = file_get_contents(dirname(__DIR__, 2).'/app/Services/AcceptAffiliationRenovationsService.php');
    $generator = file_get_contents(dirname(__DIR__, 2).'/app/Services/AffiliationRenewalCollectionGenerator.php');

    expect($acceptService)
        ->toContain('AffiliationRenewalCollectionGenerator')
        ->toContain('createPendingCollectionsForRenewal');

    expect($generator)
        ->toContain('upcomingPaymentDates')
        ->toContain("'POR PAGAR'")
        ->toContain('addMonthsNoOverflow');
});
