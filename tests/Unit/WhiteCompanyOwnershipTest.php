<?php

declare(strict_types=1);

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\Agency;
use App\Models\User;
use App\Models\WhiteCompany;
use App\Support\AffiliationWhiteCompany;
use App\Support\WhiteCompanies\WhiteCompanyDocumentBrand;
use App\Support\WhiteCompanies\WhiteCompanyOwnership;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    WhiteCompanyOwnership::flush();
});

afterEach(function (): void {
    DB::rollBack();
    WhiteCompanyOwnership::flush();
});

/**
 * Red comercial de prueba: una empresa aliada declarada sobre la agencia
 * MASTER, una agencia GENERAL que cuelga de ella y una subagencia de la
 * GENERAL.
 *
 * @return array{company: WhiteCompany, master: string, general: string, sub: string}
 */
function insertAlliedHierarchyForTest(): array
{
    $company = new WhiteCompany;
    $company->forceFill([
        'name' => 'Aliada de prueba '.uniqid('', true),
        'brand_primary_color' => '#cb61fa',
    ])->save();

    $suffix = mb_substr(uniqid('', false), -6);
    $master = 'TST-M'.$suffix;
    $general = 'TST-G'.$suffix;
    $sub = 'TST-S'.$suffix;

    foreach ([$master => $master, $general => $master, $sub => $general] as $code => $owner) {
        (new Agency)->forceFill([
            'code' => $code,
            'owner_code' => $owner,
            'agency_type_id' => $code === $master ? '1' : '3',
            'email' => mb_strtolower($code).'@example.test',
            'name_corporative' => 'Agencia '.$code,
            'status' => 'ACTIVO',
        ])->save();
    }

    (new User)->forceFill([
        'name' => 'Master aliada '.$suffix,
        'email' => 'master'.$suffix.'@example.test',
        'password' => bcrypt('secret-'.$suffix),
        'code_agency' => $master,
        'agency_type' => 'MASTER',
        'is_agency' => 1,
        'status' => 'ACTIVO',
        'white_company_id' => $company->getKey(),
    ])->save();

    WhiteCompanyOwnership::flush();

    return ['company' => $company, 'master' => $master, 'general' => $general, 'sub' => $sub];
}

it('resuelve la empresa aliada declarada sobre la agencia master', function (): void {
    $red = insertAlliedHierarchyForTest();

    expect(WhiteCompanyOwnership::companyIdForAgencyCode($red['master']))
        ->toBe((int) $red['company']->getKey());
});

it('hereda la empresa aliada en las agencias que cuelgan de la master', function (): void {
    $red = insertAlliedHierarchyForTest();
    $companyId = (int) $red['company']->getKey();

    expect(WhiteCompanyOwnership::companyIdForAgencyCode($red['general']))->toBe($companyId)
        ->and(WhiteCompanyOwnership::companyIdForAgencyCode($red['sub']))->toBe($companyId)
        ->and(WhiteCompanyOwnership::companyIdForAgencyCode(mb_strtolower($red['general'])))->toBe($companyId);
});

it('resuelve la empresa aliada de una afiliacion emitida por una agencia hija', function (): void {
    $red = insertAlliedHierarchyForTest();

    $affiliation = (new Affiliation)->forceFill([
        'code' => 'TDEC-IND-TEST',
        'code_agency' => $red['general'],
    ]);

    expect(WhiteCompanyOwnership::forAffiliation($affiliation)?->getKey())
        ->toBe($red['company']->getKey())
        ->and(AffiliationWhiteCompany::belongsToAlliedCompany($affiliation))->toBeTrue()
        ->and(AffiliationWhiteCompany::recordRowClasses($affiliation))
        ->toBe(AffiliationWhiteCompany::RECORD_ROW_CLASSES);
});

it('ignora un white_company_id que apunta a una empresa inexistente y usa la jerarquia', function (): void {
    $red = insertAlliedHierarchyForTest();

    $affiliation = (new Affiliation)->forceFill([
        'code' => 'TDEC-IND-TEST',
        'code_agency' => $red['general'],
        'white_company_id' => 999999,
    ]);

    expect(WhiteCompanyOwnership::forAffiliation($affiliation)?->getKey())
        ->toBe($red['company']->getKey());
});

it('prefiere el vinculo congelado en la afiliacion cuando la empresa existe', function (): void {
    $red = insertAlliedHierarchyForTest();

    $otra = new WhiteCompany;
    $otra->forceFill(['name' => 'Otra aliada '.uniqid('', true)])->save();
    WhiteCompanyOwnership::flush();

    $affiliation = (new Affiliation)->forceFill([
        'code' => 'TDEC-IND-TEST',
        'code_agency' => $red['general'],
        'white_company_id' => $otra->getKey(),
    ]);

    expect(WhiteCompanyOwnership::forAffiliation($affiliation)?->getKey())->toBe($otra->getKey());
});

it('resuelve tambien las afiliaciones corporativas por jerarquia', function (): void {
    $red = insertAlliedHierarchyForTest();

    $corporate = (new AffiliationCorporate)->forceFill([
        'code' => 'TDEC-COR-TEST',
        'code_agency' => $red['sub'],
    ]);

    expect(WhiteCompanyOwnership::forAffiliation($corporate)?->getKey())
        ->toBe($red['company']->getKey())
        ->and(WhiteCompanyDocumentBrand::forCorporate($corporate)->isAllied())->toBeTrue();
});

it('entrega la marca de la empresa aliada a los documentos de una agencia hija', function (): void {
    $red = insertAlliedHierarchyForTest();

    $affiliation = (new Affiliation)->forceFill([
        'code' => 'TDEC-IND-TEST',
        'code_agency' => $red['general'],
    ]);

    $brand = WhiteCompanyDocumentBrand::forAffiliation($affiliation);

    expect($brand->isAllied())->toBeTrue()
        ->and($brand->company?->getKey())->toBe($red['company']->getKey())
        ->and($brand->primaryColor)->toBe('#cb61fa');
});

it('no inventa empresa aliada para una agencia fuera de la red', function (): void {
    insertAlliedHierarchyForTest();

    $suffix = mb_substr(uniqid('', false), -6);
    (new Agency)->forceFill([
        'code' => 'TST-X'.$suffix,
        'owner_code' => 'TST-X'.$suffix,
        'agency_type_id' => '1',
        'email' => 'x'.$suffix.'@example.test',
        'status' => 'ACTIVO',
    ])->save();
    WhiteCompanyOwnership::flush();

    $affiliation = (new Affiliation)->forceFill([
        'code' => 'TDEC-IND-TEST',
        'code_agency' => 'TST-X'.$suffix,
    ]);

    expect(WhiteCompanyOwnership::forAffiliation($affiliation))->toBeNull()
        ->and(AffiliationWhiteCompany::belongsToAlliedCompany($affiliation))->toBeFalse()
        ->and(WhiteCompanyDocumentBrand::forAffiliation($affiliation)->isAllied())->toBeFalse();
});

it('no se cuelga cuando la jerarquia de agencias tiene un ciclo', function (): void {
    $suffix = mb_substr(uniqid('', false), -6);
    $a = 'TST-A'.$suffix;
    $b = 'TST-B'.$suffix;

    foreach ([$a => $b, $b => $a] as $code => $owner) {
        (new Agency)->forceFill([
            'code' => $code,
            'owner_code' => $owner,
            'agency_type_id' => '3',
            'email' => mb_strtolower($code).'@example.test',
            'status' => 'ACTIVO',
        ])->save();
    }

    WhiteCompanyOwnership::flush();

    expect(WhiteCompanyOwnership::companyIdForAgencyCode($a))->toBeNull();
});

it('lista la agencia raiz y toda su descendencia para reportes y filtros', function (): void {
    $red = insertAlliedHierarchyForTest();

    $codes = WhiteCompanyOwnership::agencyCodesFor($red['company']);

    expect($codes)->toContain($red['master'])
        ->and($codes)->toContain($red['general'])
        ->and($codes)->toContain($red['sub']);
});

it('reconoce el vinculo aunque la ficha de la empresa aliada ya no exista', function (): void {
    $affiliation = (new Affiliation)->forceFill([
        'code' => 'TDEC-IND-TEST',
        'code_agency' => '',
        'white_company_id' => 999999,
    ]);

    expect(WhiteCompanyOwnership::isAllied($affiliation))->toBeTrue()
        ->and(WhiteCompanyOwnership::forAffiliation($affiliation))->toBeNull();
});
