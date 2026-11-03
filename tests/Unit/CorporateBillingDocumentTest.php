<?php

declare(strict_types=1);

use App\Models\AffiliationCorporate;
use App\Models\Collection;
use App\Models\CollectionAdjustment;
use App\Support\AffiliationCorporates\CorporateBillingDocument;
use App\Support\AffiliationCorporates\CorporateBillingDocumentBackfill;
use App\Support\Collections\CollectionReceivableReport;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

function billingCorporate(array $attributes): AffiliationCorporate
{
    $corporate = new AffiliationCorporate;
    $corporate->forceFill($attributes);

    return $corporate;
}

it('usa el RIF de la empresa y solo cae en la cédula del contacto si no hay RIF', function (array $attributes, ?string $expected): void {
    expect(CorporateBillingDocument::for(billingCorporate($attributes)))->toBe($expected);
})->with([
    'rif' => [['rif' => '298343427', 'nro_identificacion_contact' => '123546'], '298343427'],
    'rif con espacios' => [['rif' => ' 075363191 ', 'nro_identificacion_contact' => '3664643'], '075363191'],
    'sin rif' => [['rif' => '', 'nro_identificacion_contact' => '123546'], '123546'],
    'nada' => [['rif' => null, 'nro_identificacion_contact' => null], null],
]);

it('acepta una afiliación nula', function (): void {
    expect(CorporateBillingDocument::for(null))->toBeNull();
});

it('los cuatro caminos que crean ventas y cuotas corporativas usan el RIF', function (string $path, string $needle): void {
    $source = file_get_contents(app_path($path));

    expect($source)->toContain($needle)
        ->and($source)->not->toContain('affiliate_ci_rif = $record->affiliation_corporate->nro_identificacion_contact');
})->with([
    'pago corporativo' => ['Http/Controllers/PaidMembershipCorporateController.php', 'CorporateBillingDocument::for($record->affiliation_corporate)'],
    'cuotas faltantes' => ['Services/MissingCollectionsFromSaleGenerator.php', 'CorporateBillingDocument::for($affiliation)'],
    'cambio de frecuencia' => ['Support/AffiliationCorporates/CorporatePaymentFrequencyChanger.php', 'CorporateBillingDocument::for($owner) ?? $template->affiliate_ci_rif'],
    'ajuste por población' => ['Support/AffiliationCorporates/BillingAdjustments/PopulationBillingAdjuster.php', 'CorporateBillingDocument::for($owner) ?? $template->affiliate_ci_rif'],
]);

it('el pago corporativo ya no guarda la cédula del contacto en ningún lugar', function (): void {
    expect(substr_count(file_get_contents(app_path('Http/Controllers/PaidMembershipCorporateController.php')), 'CorporateBillingDocument::for($record->affiliation_corporate)'))->toBe(6);
});

it('el reporte muestra el RIF de la afiliación aunque la cuota guarde otro documento', function (): void {
    $record = new Collection;
    $record->forceFill(['type' => 'AFILIACION CORPORATIVA', 'affiliate_ci_rif' => '123546']);
    $record->setRelation('affiliationCorporateByCode', billingCorporate(['rif' => '298343427']));
    $record->setRelation('affiliationByCode', null);

    expect(CollectionReceivableReport::holderDocument($record))->toBe('298343427');

    $record->setRelation('affiliationCorporateByCode', billingCorporate(['rif' => '']));

    expect(CollectionReceivableReport::holderDocument($record))->toBe('123546');
});

it('la corrección de datos deja todo con el RIF, con traza, y no encuentra nada al repetirse', function (): void {
    $plan = CorporateBillingDocumentBackfill::plan();

    if ($plan['collections'] === [] && $plan['sales'] === []) {
        $this->markTestSkipped('No hay documentos por corregir en esta base.');
    }

    $result = CorporateBillingDocumentBackfill::apply('Prueba');

    expect(CorporateBillingDocumentBackfill::plan())->toBe(['collections' => [], 'sales' => []])
        ->and(CollectionAdjustment::query()->where('batch_uuid', $result['batch_uuid'])->where('mode', CorporateBillingDocumentBackfill::MODE)->count())
        ->toBe(count($plan['collections']));

    foreach ($plan['sales'] as $row) {
        expect(DB::table('sales')->where('id', $row['id'])->value('affiliate_ci_rif'))->toBe($row['after']);
    }
});
