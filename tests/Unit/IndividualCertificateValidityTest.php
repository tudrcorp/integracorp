<?php

declare(strict_types=1);

use App\Support\Affiliations\Certificates\IndividualCertificateValidity;
use Carbon\CarbonImmutable;

function validityToday(): CarbonImmutable
{
    return CarbonImmutable::create(2026, 11, 3);
}

it('usa emisión → vigencia en el primer año cuando la vigencia es el fin del año', function (): void {
    expect(IndividualCertificateValidity::resolve('30/10/2026', '30/10/2027', renewed: false, today: validityToday()))
        ->toBe(['desde' => '30/10/2026', 'hasta' => '30/10/2027']);
});

it('respeta la vigencia de la tabla aunque no caiga justo a un año de la emisión', function (): void {
    expect(IndividualCertificateValidity::resolve('15/10/2026', '30/10/2027', renewed: false, today: validityToday()))
        ->toBe(['desde' => '15/10/2026', 'hasta' => '30/10/2027']);
});

it('en una afiliación renovada toma la vigencia como inicio del ciclo y suma un año', function (): void {
    expect(IndividualCertificateValidity::resolve('12/03/2023', '12/03/2026', renewed: true, today: validityToday()))
        ->toBe(['desde' => '12/03/2026', 'hasta' => '12/03/2027']);
});

it('si la vigencia es igual a la emisión la trata como inicio, no como un día de cobertura', function (): void {
    expect(IndividualCertificateValidity::resolve('30/10/2026', '30/10/2026', renewed: false, today: validityToday()))
        ->toBe(['desde' => '30/10/2026', 'hasta' => '30/10/2027']);
});

it('si la vigencia está corrida pocos días tras la emisión la trata como inicio', function (): void {
    expect(IndividualCertificateValidity::resolve('28/10/2026', '02/11/2026', renewed: false, today: validityToday()))
        ->toBe(['desde' => '02/11/2026', 'hasta' => '02/11/2027']);
});

it('si la vigencia ya pasó sin renovación registrada la toma como inicio del ciclo actual', function (): void {
    expect(IndividualCertificateValidity::resolve('04/02/2025', '04/02/2026', renewed: false, today: validityToday()))
        ->toBe(['desde' => '04/02/2026', 'hasta' => '04/02/2027']);
});

it('el último día del primer año todavía usa emisión → vigencia', function (): void {
    expect(IndividualCertificateValidity::resolve('03/11/2025', '03/11/2026', renewed: false, today: validityToday()))
        ->toBe(['desde' => '03/11/2025', 'hasta' => '03/11/2026']);
});

it('sin fecha de emisión usa la vigencia como inicio', function (?string $issued): void {
    expect(IndividualCertificateValidity::resolve($issued, '30/10/2027', renewed: false, today: validityToday()))
        ->toBe(['desde' => '30/10/2027', 'hasta' => '30/10/2028']);
})->with([
    'nula' => [null],
    'vacía' => [''],
    'basura' => ['no es fecha'],
]);

it('sin vigencia válida deja los dos campos vacíos', function (?string $effective): void {
    expect(IndividualCertificateValidity::resolve('30/10/2026', $effective, renewed: false, today: validityToday()))
        ->toBe(['desde' => '', 'hasta' => '']);
})->with([
    'nula' => [null],
    'vacía' => [''],
    'basura' => ['31/02/xx'],
]);

it('acepta los otros formatos de fecha que hay en la base', function (): void {
    expect(IndividualCertificateValidity::resolve('2026-10-30', '30-10-2027', renewed: false, today: validityToday()))
        ->toBe(['desde' => '30/10/2026', 'hasta' => '30/10/2027']);
});

it('cierra un 29 de febrero sin desbordar a marzo', function (): void {
    expect(IndividualCertificateValidity::resolve(null, '29/02/2028', renewed: true, today: validityToday()))
        ->toBe(['desde' => '29/02/2028', 'hasta' => '28/02/2029']);
});

it('el certificado y las tarjetas toman la vigencia de la misma regla', function (string $path): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/'.$path))
        ->toContain('IndividualCertificateValidity::forAffiliation($record)');
})->with([
    'certificado' => ['app/Http/Controllers/AffiliationController.php'],
    'tarjetas' => ['app/Services/AffiliationBusinessDocumentsService.php'],
]);
