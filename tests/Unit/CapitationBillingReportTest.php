<?php

declare(strict_types=1);

use App\Enums\AdministrationReportType;
use App\Enums\OperationReportFormat;
use App\Filament\Administration\Pages\CentroDeReportes;
use App\Models\Affiliate;
use App\Models\AffiliateCapitationOverride;
use App\Models\AffiliateCorporate;
use App\Models\AffiliateStatusMovement;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\CapitationBillingClosing;
use App\Models\CapitationBillingClosingItem;
use App\Models\CapitationPlan;
use App\Models\Supplier;
use App\Models\User;
use App\Observers\AffiliateStatusMovementObserver;
use App\Support\Administration\CapitationBilling\CapitationBillingCalculator;
use App\Support\Administration\CapitationBilling\CapitationBillingClosingService;
use App\Support\Administration\CapitationBilling\CapitationBillingSnapshot;
use App\Support\Administration\CapitationBilling\CapitationBillingWriter;
use App\Support\Business\CapitationPlans\AffiliateCapitationCost;
use App\Support\Business\CapitationPlans\CapitationSubject;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

/**
 * Escribe proveedores, planes, afiliaciones y cierres de prueba: todo dentro de una
 * transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    AffiliateCapitationCost::flush();
    AffiliateStatusMovementObserver::flush();
});

afterEach(function (): void {
    DB::rollBack();
    AffiliateCapitationCost::flush();
});

/**
 * Proveedor Outsourcing con un plan de capitado de US$ 6,00 para el plan TDEC 1.
 *
 * @return array{supplier: Supplier, alias: string}
 */
function capitationBillingSupplier(): array
{
    $alias = 'PESTCAP'.Str::upper(Str::random(5));
    $supplier = Supplier::query()->forceCreate([
        'name' => 'PROVEEDOR PRUEBA '.$alias,
        'integracorp_alias' => $alias,
        'is_outsourcing_medical_department' => true,
    ]);

    $plan = CapitationPlan::query()->forceCreate([
        'provider_type' => CapitationPlan::PROVIDER_SUPPLIER,
        'provider_id' => $supplier->getKey(),
        'name' => 'BASICO PRUEBA',
        'cost_per_affiliate_usd' => 6,
        'payment_frequency' => 'MENSUAL',
        'status' => CapitationPlan::STATUS_ACTIVE,
        'negotiation_type' => 'PRUEBA',
    ]);
    $plan->coveredPlans()->sync([1]);
    AffiliateCapitationCost::flush();

    return ['supplier' => $supplier, 'alias' => $alias];
}

function capitationBillingAffiliation(string $alias, string $status = 'ACTIVA'): Affiliation
{
    return Affiliation::query()->forceCreate([
        'code' => 'PEST-CAP-'.Str::upper(Str::random(8)),
        'code_agency' => 'TDG-100',
        'status' => $status,
        'plan_id' => 1,
        'service_providers' => json_encode([$alias, 'ILS']),
        'full_name_payer' => 'PAGADOR PRUEBA',
        'nro_identificacion_payer' => '12345678',
    ]);
}

function capitationBillingAffiliate(Affiliation $affiliation, string $name, string $createdAt, string $status = 'ACTIVO'): Affiliate
{
    $affiliate = Affiliate::query()->forceCreate([
        'affiliation_id' => $affiliation->getKey(),
        'full_name' => $name,
        'nro_identificacion' => (string) random_int(10_000_000, 30_000_000),
        'relationship' => 'TITULAR',
        'sex' => 'F',
        'birth_date' => '15/04/1980',
        'status' => $status,
        'plan_id' => 1,
    ]);
    $affiliate->forceFill(['created_at' => $createdAt])->saveQuietly();

    return $affiliate->refresh();
}

function capitationBillingUser(): User
{
    return User::factory()->make(['id' => 999_999_201, 'name' => 'QA CAPITADO', 'email' => 'qa.capitado@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['SUPERADMIN', 'ADMINISTRACION']]);
}

it('el prorrateo cobra la tarifa ÷ 30 por los días entre el alta y el corte del mes anterior', function (): void {
    $cutoff = CarbonImmutable::parse('2026-09-30');

    expect(CapitationBillingCalculator::prorate(4.45, CarbonImmutable::parse('2026-09-08'), $cutoff))->toBe(['days' => 22, 'amount' => 3.26])
        ->and(CapitationBillingCalculator::prorate(4.45, CarbonImmutable::parse('2026-09-30'), $cutoff))->toBe(['days' => 0, 'amount' => 0.0])
        ->and(CapitationBillingCalculator::prorate(6.0, CarbonImmutable::parse('2026-09-02'), $cutoff))->toBe(['days' => 28, 'amount' => 5.6])
        ->and(CapitationBillingCalculator::prorate(6.0, CarbonImmutable::parse('2026-10-05'), $cutoff)['days'])->toBe(0)
        ->and(CapitationBillingCalculator::rateDate(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-05 10:00'))->toDateString())->toBe('2026-11-05')
        ->and(CapitationBillingCalculator::rateDate(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-11-05'))->toDateString())->toBe('2026-10-31');
});

it('lee las fechas y los nombres como vienen guardados sin inventar datos', function (): void {
    expect(CapitationBillingCalculator::date('10/9/2013')?->toDateString())->toBe('2013-09-10')
        ->and(CapitationBillingCalculator::date('2026-10-08')?->toDateString())->toBe('2026-10-08')
        ->and(CapitationBillingCalculator::date('...'))->toBeNull()
        ->and(CapitationBillingCalculator::date('31/02/2026'))->toBeNull()
        ->and(CapitationBillingCalculator::date(''))->toBeNull()
        ->and(CapitationBillingCalculator::personName('MARIBEL COROMOTO ...'))->toBe('MARIBEL COROMOTO')
        ->and(CapitationBillingCalculator::personName('  '))->toBe('SIN NOMBRE');
});

it('suma por centavos y arma el resumen de pago: mes + prorrateo − descuentos, por la tasa BCV', function (): void {
    $row = fn (string $group, float $amount): array => ['holder_group' => $group, 'amount' => $amount, 'affiliate_name' => 'X', 'subject_id' => 1, 'subject_type' => 'affiliate'];
    $snapshot = new CapitationBillingSnapshot(
        supplierId: 1,
        supplierName: 'ATENMEDI',
        period: CarbonImmutable::parse('2026-10-01'),
        monthRows: [$row('B', 0.1), $row('A', 0.2), $row('B', 4.45)],
        prorateRows: [$row('A', 3.26)],
        retirementRows: [],
        discounts: [['concept' => 'Medicamentos', 'amount' => 2.38]],
        bcvRate: 876.79,
        notes: [],
        closing: null,
        generatedAt: CarbonImmutable::parse('2026-10-13'),
    );

    expect($snapshot->monthAmount())->toBe(4.75)
        ->and($snapshot->totalAmount())->toBe(5.63)
        ->and($snapshot->totalVes())->toBe(round(5.63 * 876.79, 2))
        ->and(array_column($snapshot->groups(), 'group'))->toBe(['A', 'B'])
        ->and($snapshot->groups()[1])->toMatchArray(['count' => 2, 'amount' => 4.55])
        ->and(array_column(CapitationBillingWriter::paymentLines($snapshot), 'amount'))->toBe([4.75, 3.26, -2.38, 5.63])
        ->and(CapitationBillingWriter::paymentLines($snapshot)[1]['label'])->toBe('Prorrateo SEPTIEMBRE 2026 Nuevos Afiliados');
});

it('factura el mes completo a los activos con alta hasta el día 1, prorratea los del mes anterior y deja fuera a los demás', function (): void {
    ['supplier' => $supplier, 'alias' => $alias] = capitationBillingSupplier();
    $affiliation = capitationBillingAffiliation($alias);
    $old = capitationBillingAffiliate($affiliation, 'ANA ANTIGUA', '2026-05-10 09:00:00');
    $new = capitationBillingAffiliate($affiliation, 'BETO NUEVO', '2026-10-09 09:00:00');
    capitationBillingAffiliate($affiliation, 'CARLA RECIEN', '2026-11-03 09:00:00');
    capitationBillingAffiliate($affiliation, 'DORA EXCLUIDA', '2026-01-10 09:00:00', 'EXCLUIDO');
    $negotiated = capitationBillingAffiliate($affiliation, 'ELSA NEGOCIADA', '2026-03-01 09:00:00');

    AffiliateCapitationOverride::query()->forceCreate([
        'subject_type' => CapitationSubject::AFFILIATE,
        'subject_id' => $negotiated->getKey(),
        'amount_usd' => 8,
        'reason' => 'Prueba de tarifa negociada',
        'plan_amount_usd' => 6,
    ]);

    $other = capitationBillingAffiliation('OTRO-PROVEEDOR');
    capitationBillingAffiliate($other, 'FELIX OTRO', '2026-01-10 09:00:00');

    $snapshot = CapitationBillingCalculator::compute($supplier, CarbonImmutable::parse('2026-11-01'), now: CarbonImmutable::parse('2026-11-20 10:00'));
    $names = array_column($snapshot->monthRows, 'affiliate_name');

    expect($names)->toBe(['ANA ANTIGUA', 'BETO NUEVO', 'ELSA NEGOCIADA'])
        ->and(array_column($snapshot->monthRows, 'amount'))->toBe([6.0, 6.0, 8.0])
        ->and($snapshot->monthRows[0])->toMatchArray([
            'holder_group' => 'TDEC - INDIVIDUAL - BASICO PRUEBA',
            'holder_document' => '12345678',
            'sex' => 'FEMENINO',
            'relationship' => 'TITULAR',
            'capitation_plan' => 'BASICO PRUEBA',
        ])
        ->and($snapshot->monthRows[2]['observation'])->toBe('Tarifa negociada para este afiliado.')
        ->and($snapshot->prorateRows)->toHaveCount(1)
        ->and($snapshot->prorateRows[0])->toMatchArray(['affiliate_name' => 'BETO NUEVO', 'prorate_days' => 22, 'amount' => 4.4])
        ->and($snapshot->monthAmount())->toBe(20.0)
        ->and($snapshot->prorateAmount())->toBe(4.4)
        ->and($old->getKey())->toBeInt()
        ->and($new->getKey())->toBeInt();
});

it('los corporativos se agrupan por empresa con su RIF', function (): void {
    ['supplier' => $supplier, 'alias' => $alias] = capitationBillingSupplier();
    $corporate = AffiliationCorporate::query()->forceCreate([
        'code' => 'PEST-COR-'.Str::upper(Str::random(6)), 'code_agency' => 'TDG-100', 'owner_code' => 'TDG-100', 'status' => 'ACTIVA',
        'name_corporate' => 'EMPRESA PRUEBA, C.A.', 'rif' => '410867655', 'document_type_rif' => 'J', 'address' => 'X', 'city_id' => 1, 'country_id' => 1,
        'region_id' => 1, 'corporate_quote_id' => 0, 'created_by' => 'QA', 'email' => 'qa@tudrencasa.com', 'email_contact' => 'qa@tudrencasa.com',
        'fee_anual' => 0, 'full_name_contact' => 'QA', 'nro_identificacion_contact' => '1', 'payment_frequency' => 'MENSUAL', 'phone' => '1', 'phone_contact' => '1',
        'service_providers' => json_encode([$alias]),
    ]);
    $member = AffiliateCorporate::query()->forceCreate([
        'affiliation_corporate_id' => $corporate->getKey(), 'first_name' => 'LUIS', 'last_name' => '...', 'nro_identificacion' => '20111222',
        'sex' => 'M', 'birth_date' => '01/02/1990', 'status' => 'ACTIVO', 'plan_id' => 1,
    ]);
    $member->forceFill(['created_at' => '2026-02-01 08:00:00'])->saveQuietly();

    $snapshot = CapitationBillingCalculator::compute($supplier, CarbonImmutable::parse('2026-11-01'), now: CarbonImmutable::parse('2026-11-20'));

    expect($snapshot->monthRows)->toHaveCount(1)
        ->and($snapshot->monthRows[0])->toMatchArray([
            'holder_group' => 'EMPRESA PRUEBA, C.A.',
            'holder_document_type' => 'J',
            'holder_document' => '410867655',
            'affiliate_name' => 'LUIS',
            'sex' => 'MASCULINO',
            'amount' => 6.0,
        ]);
});

it('cerrar guarda la foto exacta, no se cierra dos veces y el reporte del mes cerrado no cambia aunque cambien los datos', function (): void {
    ['supplier' => $supplier, 'alias' => $alias] = capitationBillingSupplier();
    $affiliation = capitationBillingAffiliation($alias);
    capitationBillingAffiliate($affiliation, 'ANA ANTIGUA', '2026-05-10 09:00:00');
    $leaving = capitationBillingAffiliate($affiliation, 'BETO SALE', '2026-06-10 09:00:00');
    $user = capitationBillingUser();
    $october = CarbonImmutable::parse('2026-10-01');

    $closing = CapitationBillingClosingService::close($supplier, $october, [['concept' => 'Medicamentos', 'amount' => 2.5]], 876.79, $user, CarbonImmutable::parse('2026-10-13 10:00'));

    expect($closing->status)->toBe(CapitationBillingClosing::STATUS_CLOSED)
        ->and($closing->affiliates_count)->toBe(2)
        ->and((float) $closing->monthly_amount)->toBe(12.0)
        ->and((float) $closing->total_amount)->toBe(9.5)
        ->and((float) $closing->total_ves)->toBe(round(9.5 * 876.79, 2))
        ->and($closing->items()->where('section', CapitationBillingClosingItem::SECTION_MONTH)->count())->toBe(2)
        ->and($closing->events()->count())->toBe(1);

    expect(fn () => CapitationBillingClosingService::close($supplier, $october, [], null, $user, CarbonImmutable::parse('2026-10-14')))
        ->toThrow(InvalidArgumentException::class, 'ya está cerrado');

    /** Cambian los datos: el mes cerrado sigue igual; el siguiente refleja el retiro con su fecha. */
    $leaving->update(['status' => 'EXCLUIDO']);
    $frozen = CapitationBillingClosingService::snapshot($supplier, $october, [['concept' => 'Ignorado', 'amount' => 99]], 1.0);

    expect($frozen->isClosed())->toBeTrue()
        ->and(array_column($frozen->monthRows, 'affiliate_name'))->toBe(['ANA ANTIGUA', 'BETO SALE'])
        ->and($frozen->discounts)->toBe([['concept' => 'Medicamentos', 'amount' => 2.5]])
        ->and($frozen->bcvRate)->toBe(876.79)
        ->and($frozen->totalAmount())->toBe(9.5);

    $movement = AffiliateStatusMovement::query()->where('subject_type', CapitationSubject::AFFILIATE)->where('subject_id', $leaving->getKey())->latest('id')->first();

    expect($movement?->event)->toBe(AffiliateStatusMovement::EVENT_BAJA)
        ->and($movement?->from_status)->toBe('ACTIVO')
        ->and($movement?->to_status)->toBe('EXCLUIDO');

    $november = CapitationBillingCalculator::compute($supplier, CarbonImmutable::parse('2026-11-01'), now: CarbonImmutable::parse('2026-11-20'));

    expect(array_column($november->monthRows, 'affiliate_name'))->toBe(['ANA ANTIGUA'])
        ->and(array_column($november->retirementRows, 'affiliate_name'))->toBe(['BETO SALE'])
        ->and($november->retirementRows[0]['retired_on']?->toDateString())->toBe(now()->toDateString())
        ->and($november->notes)->not->toContain('El mes anterior no está cerrado: los retiros salen de la bitácora de bajas (registrada desde el 05/11/2026) y el prorrateo incluye a todos los dados de alta ese mes.');
});

it('un afiliado ya cobrado completo en el cierre anterior no se vuelve a prorratear', function (): void {
    ['supplier' => $supplier, 'alias' => $alias] = capitationBillingSupplier();
    $affiliation = capitationBillingAffiliation($alias);
    capitationBillingAffiliate($affiliation, 'ANA DIA UNO', '2026-10-01 08:00:00');
    capitationBillingAffiliate($affiliation, 'BETO OCTUBRE', '2026-10-15 08:00:00');

    CapitationBillingClosingService::close($supplier, CarbonImmutable::parse('2026-10-01'), [], null, capitationBillingUser(), CarbonImmutable::parse('2026-10-20'));
    $november = CapitationBillingCalculator::compute($supplier, CarbonImmutable::parse('2026-11-01'), now: CarbonImmutable::parse('2026-11-20'));

    expect(array_column($november->monthRows, 'affiliate_name'))->toBe(['ANA DIA UNO', 'BETO OCTUBRE'])
        ->and(array_column($november->prorateRows, 'affiliate_name'))->toBe(['BETO OCTUBRE'])
        ->and($november->prorateRows[0]['prorate_days'])->toBe(16);
});

it('reabrir exige razón, no se permite si el mes siguiente ya está cerrado y deja la bitácora', function (): void {
    ['supplier' => $supplier, 'alias' => $alias] = capitationBillingSupplier();
    capitationBillingAffiliate(capitationBillingAffiliation($alias), 'ANA ANTIGUA', '2026-05-10 09:00:00');
    $user = capitationBillingUser();

    $october = CapitationBillingClosingService::close($supplier, CarbonImmutable::parse('2026-10-01'), [], null, $user, CarbonImmutable::parse('2026-10-20'));
    $november = CapitationBillingClosingService::close($supplier, CarbonImmutable::parse('2026-11-01'), [], null, $user, CarbonImmutable::parse('2026-11-20'));

    expect(fn () => CapitationBillingClosingService::reopen($october, 'corto', $user))->toThrow(InvalidArgumentException::class, 'razón')
        ->and(fn () => CapitationBillingClosingService::reopen($october, 'Corrección del padrón de octubre', $user))->toThrow(InvalidArgumentException::class, 'mes siguiente');

    $reopened = CapitationBillingClosingService::reopen($november, 'Corrección del padrón de noviembre', $user);

    expect($reopened->status)->toBe(CapitationBillingClosing::STATUS_REOPENED)
        ->and($reopened->reopen_reason)->toBe('Corrección del padrón de noviembre')
        ->and($reopened->events()->pluck('action')->all())->toBe(['REABIERTO', 'CERRADO'])
        ->and(CapitationBillingClosingService::snapshot($supplier, CarbonImmutable::parse('2026-11-01'))->isClosed())->toBeFalse()
        ->and(fn () => CapitationBillingClosingService::close($supplier, CarbonImmutable::parse('2026-12-01'), [], null, $user, CarbonImmutable::parse('2026-11-20')))
        ->toThrow(InvalidArgumentException::class, 'todavía no empieza');
});

it('valida los descuentos: concepto obligatorio, monto mayor que cero y filas vacías ignoradas', function (): void {
    expect(CapitationBillingClosingService::normalizeDiscounts([['concept' => ' Medicamentos ', 'amount' => '13.54'], ['concept' => '', 'amount' => ''], 'basura']))
        ->toBe([['concept' => 'Medicamentos', 'amount' => 13.54]])
        ->and(fn () => CapitationBillingClosingService::normalizeDiscounts([['concept' => '', 'amount' => 5]]))->toThrow(InvalidArgumentException::class, 'concepto')
        ->and(fn () => CapitationBillingClosingService::normalizeDiscounts([['concept' => 'X', 'amount' => 0]]))->toThrow(InvalidArgumentException::class, 'mayor que cero');
});

it('escribe el Excel con las cuatro hojas del modelo, el CSV con la sección y el PDF', function (): void {
    ['supplier' => $supplier, 'alias' => $alias] = capitationBillingSupplier();
    $affiliation = capitationBillingAffiliation($alias);
    capitationBillingAffiliate($affiliation, 'ANA ANTIGUA', '2026-05-10 09:00:00');
    capitationBillingAffiliate($affiliation, 'BETO NUEVO', '2026-10-09 09:00:00');
    $snapshot = CapitationBillingCalculator::compute($supplier, CarbonImmutable::parse('2026-11-01'), [['concept' => 'Medicamentos', 'amount' => 1]], 876.79, CarbonImmutable::parse('2026-11-20'));
    $directory = storage_path('framework/testing/capitado-'.Str::random(8));
    mkdir($directory, 0777, true);

    try {
        foreach (OperationReportFormat::cases() as $format) {
            CapitationBillingWriter::write($snapshot, $format, $directory.'/reporte.'.$format->extension());
        }

        $reader = new OpenSpout\Reader\XLSX\Reader;
        $reader->open($directory.'/reporte.xlsx');
        $sheets = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $rows = [];

            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }

            $sheets[$sheet->getName()] = $rows;
        }

        $reader->close();
        $summary = $sheets['RESUMEN NOVIEMBRE 2026'];
        $labels = array_map(fn (array $row): string => (string) ($row[3] ?? ''), $summary);
        $csv = (string) file_get_contents($directory.'/reporte.csv');

        expect(array_keys($sheets))->toBe(['NOVIEMBRE 2026', 'RESUMEN NOVIEMBRE 2026', 'Prorrateo OCTUBRE 2026', 'RETIROS'])
            ->and($sheets['NOVIEMBRE 2026'][3])->toBe(CapitationBillingWriter::MONTH_HEADINGS)
            ->and($labels)->toContain('Total TDEC - INDIVIDUAL - BASICO PRUEBA', 'Total general', 'Monto Total a Pagar', 'Descuento: Medicamentos', 'TC BCV')
            ->and((float) $summary[array_search('Monto Total a Pagar', $labels, true)][15])->toBe(15.4)
            ->and($sheets['Prorrateo OCTUBRE 2026'][4][17])->toBe(22)
            ->and($csv)->toContain('SECCION,NOMTOM')
            ->and($csv)->toContain('PRORRATEO,"TDEC - INDIVIDUAL - BASICO PRUEBA"')
            ->and(file_get_contents($directory.'/reporte.pdf', length: 5))->toBe('%PDF-');
    } finally {
        array_map('unlink', glob($directory.'/*') ?: []);
        @rmdir($directory);
    }
});

it('el reporte tiene permiso propio y no aparece en el Centro de reportes sin él', function (): void {
    expect(BusinessFilamentActionPermissionRegistry::CAPITATION_BILLING_REPORT)->toBe('prefacturacion-capitado')
        ->and(AdministrationReportType::CapitationBilling->isCapitation())->toBeTrue();

    test()->actingAs(User::factory()->make(['id' => 999_999_202, 'name' => 'QA SIN PERMISO', 'email' => 'qa.sinpermiso@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['ADMINISTRACION']]));

    expect(CentroDeReportes::availableTypes())->not->toContain(AdministrationReportType::CapitationBilling);

    test()->actingAs(capitationBillingUser());

    expect(CentroDeReportes::availableTypes())->toContain(AdministrationReportType::CapitationBilling);
});

it('un afiliado con el plan de otro proveedor asignado se cobra solo a ese proveedor', function (): void {
    ['supplier' => $supplier, 'alias' => $alias] = capitationBillingSupplier();
    ['supplier' => $otherSupplier, 'alias' => $otherAlias] = capitationBillingSupplier();

    // La afiliación lleva a los dos proveedores; al afiliado se le asigna el plan del segundo.
    $both = Affiliation::query()->forceCreate([
        'code' => 'PEST-CAP-'.Str::upper(Str::random(8)),
        'code_agency' => 'TDG-100',
        'status' => 'ACTIVA',
        'plan_id' => 1,
        'service_providers' => json_encode([$alias, $otherAlias]),
        'full_name_payer' => 'PAGADOR PRUEBA',
        'nro_identificacion_payer' => '12345678',
    ]);
    $assigned = capitationBillingAffiliate($both, 'IVAN REASIGNADO', '2026-04-10 09:00:00');

    App\Models\AffiliateCapitationAssignment::query()->forceCreate([
        'subject_type' => CapitationSubject::AFFILIATE,
        'subject_id' => $assigned->getKey(),
        'capitation_plan_id' => CapitationPlan::query()->where('provider_id', $otherSupplier->getKey())->value('id'),
        'reason' => 'Asignación a otro proveedor',
    ]);
    AffiliateCapitationCost::flush();

    $first = CapitationBillingCalculator::compute($supplier, CarbonImmutable::parse('2026-11-01'), now: CarbonImmutable::parse('2026-11-20 10:00'));
    $second = CapitationBillingCalculator::compute($otherSupplier, CarbonImmutable::parse('2026-11-01'), now: CarbonImmutable::parse('2026-11-20 10:00'));

    expect(array_column($first->monthRows, 'affiliate_name'))->not->toContain('IVAN REASIGNADO')
        ->and(array_column($second->monthRows, 'affiliate_name'))->toBe(['IVAN REASIGNADO'])
        ->and($second->monthRows[0]['amount'])->toBe(6.0);
});
