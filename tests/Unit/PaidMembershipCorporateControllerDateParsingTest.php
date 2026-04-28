<?php

declare(strict_types=1);

it('normaliza fechas de próximo pago con formatos slash y guion', function (): void {
    $path = dirname(__DIR__, 2).'/app/Http/Controllers/PaidMembershipCorporateController.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('private static function parseDateForStorage(string $value): Carbon')
        ->and($contents)->toContain("['d/m/Y', 'd-m-Y', 'Y-m-d']")
        ->and($contents)->toContain('self::parseDateForStorage((string) $collections->next_payment_date)->format(\'Y-m-d\')');
});
