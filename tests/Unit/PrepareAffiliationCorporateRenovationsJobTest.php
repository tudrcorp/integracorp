<?php

declare(strict_types=1);

it('define ventana de renovación de 30 días y actor del sistema para corporativas', function (): void {
    $path = dirname(__DIR__, 2).'/app/Jobs/PrepareAffiliationCorporateRenovations.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain('AFFILIATION_STATUS_ACTIVE = \'ACTIVA\'')
        ->toContain("->where('status', self::AFFILIATION_STATUS_ACTIVE)")
        ->toContain('RENEWAL_PERIOD_DAYS = 30')
        ->toContain('STATUS_VIGENTE')
        ->toContain('STATUS_RENOVATION_PERIOD')
        ->toContain('remaining_days')
        ->toContain('$daysUntilRenewal <= self::RENEWAL_PERIOD_DAYS')
        ->toContain('PRE-APROBADA')
        ->toContain('RenovationCorporate::query()->updateOrCreate')
        ->toContain('is_negotiation_candidate')
        ->toContain('calculateAmountsForPlanCoverageAndAge')
        ->toContain('resolveAffiliateCorporateAgeForRenewal')
        ->toContain('info_renovation')
        ->toContain('solo lectura en el expediente vigente')
        ->not->toContain('$affiliation->save')
        ->not->toContain('applyIdealToSpecialPlanTransition(');
});

it('programa la tarea diaria de renovaciones corporativas a las 6:00', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/routes/console.php');

    expect($source)
        ->toContain('PrepareAffiliationCorporateRenovations')
        ->toContain("dailyAt('6:00')");
});
