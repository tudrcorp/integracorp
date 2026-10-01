<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\AnnualCollections\AnnualCollectionResource;
use App\Filament\Exports\CollectionReceivableExporter;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\Collection;
use App\Models\Plan;
use App\Support\Collections\CollectionReceivableReport;
use Carbon\CarbonImmutable;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

uses(Tests\TestCase::class);

/**
 * Solo lectura: arma modelos en memoria y SQL sin ejecutarlo contra la base.
 */
function receivableCollection(array $attributes = []): Collection
{
    $collection = new Collection([
        'include_date' => '09/07/2026',
        'affiliate_full_name' => 'ROSA PÉREZ',
        'affiliate_ci_rif' => '6896503',
        'affiliate_status' => 'ACTIVA',
        'type' => 'AFILIACION INDIVIDUAL',
        'payment_frequency' => 'TRIMESTRAL',
        'next_payment_date' => '09/10/2026',
        'total_amount' => 147.25,
        'status' => 'POR PAGAR',
        ...$attributes,
    ]);

    $collection->setRelation('affiliationByCode', null);
    $collection->setRelation('affiliationCorporateByCode', null);

    return $collection;
}

it('calcula el estatus de cobro y los días contra la fecha de hoy', function (string $due, string $status, string $label, int $count): void {
    $today = CarbonImmutable::parse('2026-10-01');
    $collection = receivableCollection(['next_payment_date' => $due]);

    expect(CollectionReceivableReport::collectionStatus($collection, $today))->toBe($status)
        ->and(CollectionReceivableReport::daysLabel($collection, $today))->toBe($label)
        ->and(CollectionReceivableReport::daysCount($collection, $today))->toBe($count);
})->with([
    'por vencer' => ['2026-10-09', 'POR PAGAR', 'Faltan 8 días', 8],
    'vence mañana' => ['2026-10-02', 'POR PAGAR', 'Faltan 1 día', 1],
    'vence hoy' => ['2026-10-01', 'POR PAGAR', 'Vence hoy', 0],
    'vencida ayer' => ['2026-09-30', 'VENCIDO', '1 día de atraso', 1],
    'vencida hace tiempo' => ['2023-08-17', 'VENCIDO', '1141 días de atraso', 1141],
]);

it('numera la cuota dentro del año de contrato', function (string $frequency, string $due, ?string $label): void {
    expect(CollectionReceivableReport::installmentLabel(receivableCollection([
        'payment_frequency' => $frequency,
        'next_payment_date' => $due,
    ])))->toBe($label);
})->with([
    'primera trimestral' => ['TRIMESTRAL', '2026-07-09', '1 de 4'],
    'segunda trimestral' => ['TRIMESTRAL', '2026-10-09', '2 de 4'],
    'cuarta trimestral' => ['TRIMESTRAL', '2027-04-09', '4 de 4'],
    'segunda semestral' => ['SEMESTRAL', '2027-01-09', '2 de 2'],
    'anual' => ['ANUAL', '2027-07-09', '1 de 1'],
    'frecuencia desconocida' => ['OTRA', '2026-10-09', null],
]);

it('sin fecha de vencimiento no inventa estatus ni días', function (): void {
    $collection = receivableCollection(['next_payment_date' => null, 'filter_next_payment_date' => null]);

    expect(CollectionReceivableReport::daysLabel($collection))->toBe('Sin fecha')
        ->and(CollectionReceivableReport::daysCount($collection))->toBeNull()
        ->and(CollectionReceivableReport::collectionStatus($collection))->toBe('POR PAGAR')
        ->and(CollectionReceivableReport::installmentLabel($collection))->toBeNull();
});

it('el tomador de una individual es el pagador de la afiliación y cae al titular si falta', function (): void {
    $withPayer = receivableCollection();
    $withPayer->setRelation('affiliationByCode', new Affiliation([
        'full_name_payer' => 'AMANDA ESCALANTE',
        'nro_identificacion_payer' => '11222333',
        'fee_anual' => 589,
        'effective_date' => '09/07/2027',
        'status' => 'excluido',
    ]));

    $withoutAffiliation = receivableCollection();
    $withoutAffiliation->setRelation('affiliationByCode', null);

    expect(CollectionReceivableReport::payerName($withPayer))->toBe('AMANDA ESCALANTE')
        ->and(CollectionReceivableReport::payerDocument($withPayer))->toBe('11222333')
        ->and(CollectionReceivableReport::annualFee($withPayer))->toBe(589.0)
        ->and(CollectionReceivableReport::effectiveDate($withPayer))->toBe('09/07/2027')
        ->and(CollectionReceivableReport::affiliateStatus($withPayer))->toBe('EXCLUIDO')
        ->and(CollectionReceivableReport::payerName($withoutAffiliation))->toBe('ROSA PÉREZ')
        ->and(CollectionReceivableReport::payerDocument($withoutAffiliation))->toBe('6896503')
        ->and(CollectionReceivableReport::affiliateStatus($withoutAffiliation))->toBe('ACTIVA');
});

it('en una corporativa el tomador es la empresa y el plan sale de sus planes', function (): void {
    $plan = new Plan(['description' => 'PLAN IDEAL']);
    $corporatePlan = new AfilliationCorporatePlan;
    $corporatePlan->setRelation('plan', $plan);

    $corporate = new AffiliationCorporate([
        'name_corporate' => 'SEMITECH, C.A.',
        'rif' => 'J-123',
        'fee_anual' => 11988,
    ]);
    $corporate->setRelation('affiliationCorporatePlans', new EloquentCollection([$corporatePlan, $corporatePlan]));

    $collection = receivableCollection(['type' => 'AFILIACIÓN CORPORATIVA', 'affiliate_full_name' => 'SEMITECH']);
    $collection->setRelation('plan', null);
    $collection->setRelation('affiliationCorporateByCode', $corporate);

    expect(CollectionReceivableReport::isCorporate($collection))->toBeTrue()
        ->and(CollectionReceivableReport::payerName($collection))->toBe('SEMITECH, C.A.')
        ->and(CollectionReceivableReport::payerDocument($collection))->toBe('J-123')
        ->and(CollectionReceivableReport::planLabel($collection))->toBe('PLAN IDEAL')
        ->and(CollectionReceivableReport::annualFee($collection))->toBe(11988.0);
});

it('los rangos de vencimiento filtran por la fecha de la cuota', function (string $bucket, array $expectedBindings): void {
    $today = CarbonImmutable::parse('2026-10-01');
    $query = CollectionReceivableReport::applyAging(Collection::query(), $bucket, $today);

    expect($query->toSql())->toContain('collections`.`filter_next_payment_date')
        ->and($query->getBindings())->toBe($expectedBindings);
})->with([
    'próximos 7 días' => [CollectionReceivableReport::AGING_DUE_SOON, ['2026-10-01', '2026-10-08']],
    'por vencer' => [CollectionReceivableReport::AGING_NOT_DUE, ['2026-10-01']],
    '1 a 30' => [CollectionReceivableReport::AGING_OVERDUE_1_30, ['2026-09-01', '2026-09-30']],
    '31 a 60' => [CollectionReceivableReport::AGING_OVERDUE_31_60, ['2026-08-02', '2026-08-31']],
    '61 a 90' => [CollectionReceivableReport::AGING_OVERDUE_61_90, ['2026-07-03', '2026-08-01']],
    'más de 90' => [CollectionReceivableReport::AGING_OVERDUE_90_PLUS, ['2026-07-03']],
]);

it('numera bien la cuota aunque una renovación dejó la fecha de inclusión del año anterior', function (): void {
    expect(CollectionReceivableReport::installmentLabel(receivableCollection([
        'include_date' => '15/04/2025',
        'next_payment_date' => '15/10/2026',
    ])))->toBe('3 de 4');
});

it('usa la frecuencia de la cuota y, si falta, la de la afiliación', function (): void {
    $withoutFrequency = receivableCollection(['payment_frequency' => null]);
    $withoutFrequency->setRelation('affiliationByCode', new Affiliation(['payment_frequency' => 'semestral']));

    expect(CollectionReceivableReport::paymentFrequency(receivableCollection()))->toBe('TRIMESTRAL')
        ->and(CollectionReceivableReport::paymentFrequency($withoutFrequency))->toBe('SEMESTRAL')
        ->and(CollectionReceivableReport::installmentAmount(receivableCollection()))->toBe(147.25);
});

it('Cobranza por mes sale de las cuotas reales: una fila por afiliación con su próxima cuota pendiente', function (): void {
    $query = AnnualCollectionResource::getEloquentQuery();

    expect(AnnualCollectionResource::getModel())->toBe(Collection::class)
        ->and(AnnualCollectionResource::getSlug())->toBe('annual-collections')
        ->and($query->toSql())
        ->toContain('select next_installment.id from collections as next_installment')
        ->toContain('order by next_installment.filter_next_payment_date asc, next_installment.id asc limit 1')
        ->not->toContain('annual_collections')
        ->and($query->getBindings())->toBe(['POR PAGAR', 'POR PAGAR'])
        ->and(array_keys(AnnualCollectionResource::getPages()))->toBe(['index'])
        ->and(AnnualCollectionResource::canCreate())->toBeFalse()
        ->and(AnnualCollectionResource::canDeleteAny())->toBeFalse()
        ->and(AnnualCollectionResource::canEdit(new Collection))->toBeFalse();
});

it('Gestión de Cobranza conserva sus acciones y sus páginas', function (): void {
    $root = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Collections';

    expect(file_get_contents($root.'/Tables/CollectionsTable.php'))
        ->toContain("Action::make('send_email')")
        ->and(is_file($root.'/Pages/EditCollection.php'))->toBeTrue()
        ->and(is_file($root.'/Pages/CreateCollection.php'))->toBeTrue();
});

it('la tabla sigue las columnas del reporte CXC y no tiene acciones por fila ni masivas', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/AnnualCollections/Tables/AnnualCollectionsTable.php');

    $labels = [
        'Fecha de inclusión o emisión', 'Afiliado o titular', 'C.I./R.I.F.', 'Tomador', 'C.I./R.I.F. tomador',
        'Teléfono', 'Email', 'Plan', 'Población', 'Agencia', 'Agente', 'Tarifa anual', 'Fecha de vigencia',
        'Fraccionamiento de cuotas', 'Periodos de pago', 'Fecha de vencimiento', 'Estatus de cobro', 'Días',
        'Estatus del afiliado o titular',
    ];

    $position = 0;

    foreach ($labels as $label) {
        $found = strpos($source, "->label('{$label}')", $position);
        expect($found)->not->toBeFalse("Falta o está fuera de orden la columna «{$label}»");
        $position = (int) $found;
    }

    expect($source)
        ->toContain('->recordActions([])')
        ->toContain('->toolbarActions([])')
        ->toContain('CollectionReceivableReport::agingOptions()')
        ->toContain('public static function runRegeneratePdf(Collection $record): bool')
        ->not->toContain('DeleteBulkAction')
        ->not->toContain('ViewAction')
        ->not->toContain('edit_next_payment_');
});

it('el exportador saca las columnas en el orden del Excel con los valores calculados', function (): void {
    $collection = receivableCollection([
        'affiliate_phone' => '04140000000',
        'affiliate_email' => 'rosa@example.com',
        'persons' => '1',
        'code_agency' => 'TDG-101',
        'next_payment_date' => CarbonImmutable::today()->subDays(3)->format('d/m/Y'),
    ]);
    $collection->setRelation('plan', new Plan(['description' => 'PLAN ESPECIAL']));
    $collection->setRelation('agent', null);
    $collection->setRelation('agencyByCode', null);

    $columnMap = collect(CollectionReceivableExporter::getColumns())
        ->filter(fn ($column): bool => $column->isEnabledByDefault())
        ->mapWithKeys(fn ($column): array => [$column->getName() => $column->getLabel()])
        ->all();

    $row = (new CollectionReceivableExporter(new Export, $columnMap, []))($collection);

    expect(array_values($columnMap))->toBe([
        'FECHA DE INCLUSION O EMISION', 'AFILIADO O TITULAR', 'C.I./R.I.F.', 'TOMADOR', 'C.I./R.I.F. TOMADOR',
        'TELEFONO', 'EMAIL', 'PLAN', 'POBLACION', 'AGENCIA', 'AGENTE', 'TARIFA ANUAL', 'FECHA DE VIGENCIA',
        'FRACCIONAMIENTO DE CUOTAS PARA PAGO', 'PERIODOS DE PAGO', 'FECHA DE VENCIMIENTO', 'ESTATUS DE COBRO',
        'DIAS', 'ESTATUS DEL AFILIADO O TITULAR',
    ])
        ->and($row[3])->toBe('ROSA PÉREZ')
        ->and($row[7])->toBe('PLAN ESPECIAL')
        ->and($row[9])->toBe('TDG-101')
        ->and($row[16])->toBe('VENCIDO')
        ->and($row[17])->toBe('3');
});
