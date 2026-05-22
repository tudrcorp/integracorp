<?php

declare(strict_types=1);

it('define ventana de renovación de 30 días y actor del sistema', function (): void {
    $path = dirname(__DIR__, 2).'/app/Jobs/PrepareAffiliationRenovations.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain('AFFILIATION_STATUS_ACTIVE = \'ACTIVA\'')
        ->toContain("->where('status', self::AFFILIATION_STATUS_ACTIVE)")
        ->toContain('RENEWAL_PERIOD_DAYS = 30')
        ->toContain('STATUS_VIGENTE')
        ->toContain('STATUS_RENOVATION_PERIOD')
        ->toContain('remaining_days')
        ->toContain('$daysUntilRenewal <= self::RENEWAL_PERIOD_DAYS')
        ->not->toContain('Sin afiliados elegibles')
        ->toContain('PRE-APROBADA')
        ->toContain('updateOrCreate')
        ->toContain('is_negotiation_candidate');
});

it('programa la tarea diaria a las 6:00', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/routes/console.php');

    expect($source)
        ->toContain('PrepareAffiliationRenovations')
        ->toContain("dailyAt('6:00')");
});
