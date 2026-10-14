<?php

declare(strict_types=1);

use App\Enums\CompanyAssociateStatus;
use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\CompanyAssociate;
use App\Support\Operations\OperationsAffiliateHeader;
use Carbon\CarbonImmutable;

uses(Tests\TestCase::class);

/**
 * Solo lectura: los modelos no se guardan y la única consulta es la búsqueda del
 * paciente de telemedicina por una cédula que no existe.
 */
const HEADER_TEST_MISSING_DOCUMENT = 'ZZ-HEADER-TEST-999';

it('calcula la vigencia anual igual que las renovaciones', function (): void {
    $today = CarbonImmutable::create(2026, 10, 13);

    expect(OperationsAffiliateHeader::coverage('05/10/2026', 'ACTIVO', $today))
        ->toBe(['label' => 'Desde 05/10/2026 · renueva 05/10/2027', 'hint' => null, 'tone' => 'success']);
});

it('avisa en ámbar dentro de la ventana de renovación', function (): void {
    $today = CarbonImmutable::create(2026, 10, 13);

    expect(OperationsAffiliateHeader::coverage('2025-10-25', 'ACTIVO', $today))
        ->toMatchArray(['hint' => 'Renueva en 12 días', 'tone' => 'warning'])
        ->and(OperationsAffiliateHeader::coverage('13/10/2025', 'ACTIVO', $today))
        ->toMatchArray(['hint' => 'Renueva hoy', 'tone' => 'warning']);
});

it('marca en rojo la renovación pendiente y no alarma si el afiliado no está activo', function (): void {
    $today = CarbonImmutable::create(2026, 10, 13);

    expect(OperationsAffiliateHeader::coverage('12/10/2025', 'ACTIVO', $today))
        ->toMatchArray(['hint' => 'Renovación pendiente hace 1 día', 'tone' => 'danger'])
        ->and(OperationsAffiliateHeader::coverage('12/10/2025', 'EXCLUIDO', $today))
        ->toMatchArray(['hint' => null, 'tone' => 'gray']);
});

it('omite la vigencia cuando la fecha falta o no se puede leer', function (?string $value): void {
    expect(OperationsAffiliateHeader::coverage($value, 'ACTIVO'))->toBeNull();
})->with(['nula' => null, 'vacía' => '', 'basura' => 'no es fecha']);

it('arma las iniciales con el primer y último nombre', function (?string $name, string $expected): void {
    expect(OperationsAffiliateHeader::initials($name))->toBe($expected);
})->with([
    'completo' => ['erika inmaculada mora vanegas', 'EV'],
    'una palabra' => ['Ñangara', 'Ñ'],
    'espacios' => ['  ana   pérez ', 'AP'],
    'vacío' => [null, '?'],
]);

it('renderiza la ficha individual con estatus, datos clave y aviso de paciente', function (): void {
    $affiliate = new Affiliate([
        'full_name' => 'erika  mora',
        'nro_identificacion' => HEADER_TEST_MISSING_DOCUMENT,
        'birth_date' => now()->subYears(39)->subDays(10)->format('d/m/Y'),
        'sex' => 'FEMENINO',
        'phone' => '04141234567',
        'status' => 'ACTIVO',
        'relationship' => 'TITULAR',
    ]);
    $affiliate->setRelation('affiliation', null);
    $affiliate->setRelation('plan', null);

    $html = OperationsAffiliateHeader::forAffiliate($affiliate)->toHtml();

    expect($html)
        ->toContain('Ficha del afiliado · Individual')
        ->toContain('ERIKA MORA')
        ->toMatch('/>\s*EM\s*</')
        ->toContain('ACTIVO')
        ->toContain('Titular')
        ->toContain(HEADER_TEST_MISSING_DOCUMENT)
        ->toContain('39 años')
        ->toContain('FEMENINO')
        ->not->toContain('paciente de telemedicina')
        ->not->toContain('04141234567')
        ->not->toContain('Agente')
        ->not->toContain('renueva');
});

it('renderiza la ficha corporativa con nombre y apellido', function (): void {
    $affiliate = new AffiliateCorporate([
        'first_name' => 'OREANA ALESSANDRA',
        'last_name' => 'GUTIERREZ',
        'nro_identificacion' => HEADER_TEST_MISSING_DOCUMENT,
        'status' => 'EXCLUIDO',
    ]);
    $affiliate->setRelation('affiliationCorporate', null);
    $affiliate->setRelation('plan', null);

    expect(OperationsAffiliateHeader::forAffiliateCorporate($affiliate)->toHtml())
        ->toContain('Ficha del afiliado · Corporativo')
        ->toContain('OREANA ALESSANDRA GUTIERREZ')
        ->toContain('EXCLUIDO')
        ->toContain('#dc2626')
        ->not->toContain('paciente de telemedicina');
});

it('renderiza el asociado anulado con la razón de anulación', function (): void {
    $associate = new CompanyAssociate([
        'full_name' => 'Sinai Ramirez',
        'identity_card' => HEADER_TEST_MISSING_DOCUMENT,
        'status' => CompanyAssociateStatus::Anulado,
        'annulment_reason' => 'No pudo viajar',
    ]);
    $associate->setRelation('company', null);
    $associate->setRelation('responsible', null);

    expect(OperationsAffiliateHeader::forCompanyAssociate($associate)->toHtml())
        ->toContain('Ficha del asociado · Nuevos Negocios')
        ->toContain('SINAI RAMIREZ')
        ->toContain('ANULADO')
        ->toContain('Razón de anulación')
        ->toContain('No pudo viajar');
});

it('las tres fichas de Operaciones usan el encabezado común', function (): void {
    $base = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/';

    expect(file_get_contents($base.'Affiliates/Pages/ViewAffiliate.php'))
        ->toContain('OperationsAffiliateHeader::forAffiliate($affiliate)')
        ->and(file_get_contents($base.'AffiliateCorporates/Pages/ViewAffiliateCorporate.php'))
        ->toContain('OperationsAffiliateHeader::forAffiliateCorporate($affiliate)')
        ->and(file_get_contents($base.'CompanyAssociates/Pages/ViewCompanyAssociate.php'))
        ->toContain('OperationsAffiliateHeader::forCompanyAssociate($associate)');
});
