<?php

declare(strict_types=1);

use App\Enums\IdentityDocumentType;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\Collection;
use App\Support\Affiliations\AffiliationDocumentPrefix;
use App\Support\Affiliations\AffiliationDocumentTypeColumns;
use App\Support\Collections\CollectionReceivableReport;

uses(Tests\TestCase::class);

afterEach(fn () => AffiliationDocumentTypeColumns::flush());

it('antepone la letra y conserva los ceros a la izquierda', function (?string $number, ?IdentityDocumentType $type, string $expected): void {
    expect(AffiliationDocumentPrefix::format($number, $type))->toBe($expected);
})->with([
    'cédula' => ['12345678', IdentityDocumentType::V, 'V-12345678'],
    'rif con cero' => ['075363191', IdentityDocumentType::J, 'J-075363191'],
    'gubernamental' => ['200000001', IdentityDocumentType::G, 'G-200000001'],
    'ya trae letra' => ['j075363191', IdentityDocumentType::V, 'J-075363191'],
    'ya trae letra y guion' => ['E-84322352', IdentityDocumentType::V, 'E-84322352'],
    'vacío' => [null, IdentityDocumentType::V, ''],
    'puntos' => ['...', IdentityDocumentType::J, '...'],
    'solo ceros' => ['000000000', IdentityDocumentType::V, '000000000'],
    'sin tipo' => ['123', null, '123'],
]);

it('deduce E para cédulas de extranjeros y V para el resto', function (string $number, IdentityDocumentType $expected): void {
    expect(AffiliationDocumentPrefix::inferPerson($number))->toBe($expected);
})->with([
    'venezolano' => ['5207513', IdentityDocumentType::V],
    'extranjero' => ['84322352', IdentityDocumentType::E],
    'límite' => ['80000000', IdentityDocumentType::E],
    'nueve dígitos no es extranjero' => ['843223521', IdentityDocumentType::V],
]);

it('reconoce a un pagador empresa por su nombre', function (string $name, bool $expected): void {
    expect(AffiliationDocumentPrefix::looksLikeCompany($name))->toBe($expected);
})->with([
    ['SABRA C. A.', true],
    ['SABRA C A', true],
    ['VENEZOLANA DE EMPAQUES FLEXIBLES FLEXOVEN, C A', true],
    ['UNIDAD OFTALMOLÓGICA GONZALEZ SIRIT', true],
    ['Inversiones Delta S.R.L.', true],
    ['MARIA MIGUELINA SEQUERA', false],
    ['CARLOS ALBERTO DA CAMARA DOS PASSO', false],
    ['ISABEL CASA', false],
]);

it('lo elegido en el formulario manda sobre lo deducido', function (): void {
    expect(AffiliationDocumentPrefix::forPerson('E', '5207513'))->toBe(IdentityDocumentType::E)
        ->and(AffiliationDocumentPrefix::forPayer('V', '1', 'SABRA C.A.'))->toBe(IdentityDocumentType::V)
        ->and(AffiliationDocumentPrefix::forCompany('G'))->toBe(IdentityDocumentType::G)
        ->and(AffiliationDocumentPrefix::forCompany('x'))->toBe(IdentityDocumentType::J)
        ->and(AffiliationDocumentPrefix::forPayer(null, '306451234', 'SABRA C A'))->toBe(IdentityDocumentType::J);
});

it('en el reporte una corporativa lleva J en titular y tomador, o la letra elegida', function (): void {
    $corporate = new AffiliationCorporate;
    $corporate->forceFill(['rif' => '075363191', 'name_corporate' => 'FLEXOVEN']);

    $record = new Collection;
    $record->forceFill(['affiliation_code' => 'TDEC-COR-X', 'affiliate_ci_rif' => '075363191', 'type' => 'AFILIACION CORPORATIVA']);
    $record->setRelation('affiliationCorporateByCode', $corporate);
    $record->setRelation('affiliationByCode', null);

    expect(CollectionReceivableReport::isCorporate($record))->toBeTrue();

    expect(CollectionReceivableReport::holderDocumentWithPrefix($record))->toBe('J-075363191')
        ->and(CollectionReceivableReport::payerDocumentWithPrefix($record))->toBe('J-075363191');

    $corporate->setAttribute('document_type_rif', 'G');

    expect(CollectionReceivableReport::payerDocumentWithPrefix($record))->toBe('G-075363191');
});

it('en el reporte una individual usa la letra del titular y deduce la del pagador', function (): void {
    $affiliation = new Affiliation;
    $affiliation->forceFill(['full_name_payer' => 'UNIDAD MEDICA DANKOR SALUD, C.A.', 'nro_identificacion_payer' => '312407913', 'document_type_ti' => 'E']);

    $record = new Collection;
    $record->forceFill(['affiliation_code' => 'TDEC-IND-X', 'affiliate_ci_rif' => '5207513']);
    $record->setRelation('affiliationByCode', $affiliation);
    $record->setRelation('affiliationCorporateByCode', null);

    expect(CollectionReceivableReport::holderDocumentWithPrefix($record))->toBe('E-5207513')
        ->and(CollectionReceivableReport::payerDocumentWithPrefix($record))->toBe('J-312407913');
});

it('sin la migración el reporte no pide las columnas nuevas', function (): void {
    $property = new ReflectionProperty(AffiliationDocumentTypeColumns::class, 'available');
    $property->setValue(null, false);

    $loads = implode('|', CollectionReceivableReport::eagerLoads());

    expect($loads)->not->toContain('document_type_');

    $property->setValue(null, true);

    expect(implode('|', CollectionReceivableReport::eagerLoads()))
        ->toContain('document_type_ti,document_type_payer')
        ->toContain('document_type_rif');
});

it('sin la migración el select no se muestra ni se guarda', function (): void {
    $property = new ReflectionProperty(AffiliationDocumentTypeColumns::class, 'available');
    $property->setValue(null, false);

    $select = App\Filament\Shared\Affiliations\DocumentTypeSelect::holder();

    expect($select->isDehydrated())->toBeFalse()
        ->and(AffiliationDocumentTypeColumns::copyHolderTypeToPayer(['nro_identificacion_ti' => '1']))
        ->not->toHaveKey('document_type_payer');
});

it('copia el tipo del titular al pagador cuando el titular paga', function (): void {
    expect(AffiliationDocumentTypeColumns::copyHolderTypeToPayer(['document_type_ti' => 'E']))
        ->toBe(['document_type_ti' => 'E', 'document_type_payer' => 'E']);
});

it('las altas de los cuatro paneles copian el tipo al pagador', function (string $panel): void {
    expect(file_get_contents(app_path("Filament/{$panel}/Resources/Affiliations/Pages/CreateAffiliation.php")))
        ->toContain('AffiliationDocumentTypeColumns::copyHolderTypeToPayer($data)');
})->with(['Business', 'Agents', 'Master', 'General']);
