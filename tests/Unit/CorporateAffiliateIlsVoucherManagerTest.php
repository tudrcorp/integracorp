<?php

declare(strict_types=1);

use App\Filament\Business\Resources\AffiliationCorporates\Pages\ViewAffiliationCorporate;
use App\Filament\Business\Resources\AffiliationCorporates\RelationManagers\CorporateAffiliatesRelationManager;
use App\Models\AffiliateCorporate;
use App\Models\AffiliateCorporateIlsVoucher;
use App\Models\AffiliationCorporate;
use App\Models\Benefit;
use App\Models\BenefitCoverage;
use App\Models\Coverage;
use App\Models\Plan;
use App\Models\User;
use App\Support\AffiliationCorporates\CorporateAffiliateIlsVoucherManager;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Vouchers ILS por beneficio/cobertura de los afiliados corporativos.
 *
 * Plan de prueba con dos coberturas (3K y 10K) y tres beneficios: «Accidentes»
 * y «Funerario» con tope en USD en ambas, y «Telemedicina» sin tope, que no
 * debe pedir voucher.
 *
 * Todo lo que escribe va dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    CorporateAffiliateIlsVoucherManager::flush();
    Storage::fake('public');

    Filament::setCurrentPanel('business');

    $analista = User::query()
        ->where('email', 'like', '%@tudrencasa.com')
        ->where('status', 'ACTIVO')
        ->get()
        ->first(fn (User $user): bool => in_array('NEGOCIOS', (array) ($user->departament ?? []), true)
            || in_array('SUPERADMIN', (array) ($user->departament ?? []), true));

    if ($analista === null) {
        $this->markTestSkipped('No hay un analista de Negocios activo en la base para montar el panel.');
    }

    // Solo en memoria: la acción por fila exige administrador de Negocios.
    $analista->is_business_admin = 1;
    $this->analista = $analista;
    $this->actingAs($analista);

    $this->plan = persistirIls(new Plan, [
        'code' => 'PEST-ILS-'.uniqid(),
        'description' => 'PLAN ILS PEST',
        'created_by' => 'PEST',
    ]);

    $this->cov3k = coberturaIls($this->plan, 3000);
    $this->cov10k = coberturaIls($this->plan, 10000);

    $this->accidentes = beneficioIls('ASISTENCIA MÉDICA POR ACCIDENTES');
    $this->funerario = beneficioIls('GASTOS FUNERARIOS');
    $this->telemedicina = beneficioIls('TELEMEDICINA');

    foreach ([$this->cov3k, $this->cov10k] as $coverage) {
        relacionIls($this->plan, $this->accidentes, $coverage, (float) $coverage->price);
        relacionIls($this->plan, $this->funerario, $coverage, 2000);
        relacionIls($this->plan, $this->telemedicina, $coverage, null);
    }

    $this->afiliacion = persistirIls(new AffiliationCorporate, [
        'code' => 'PEST-ILS-'.uniqid(),
        'corporate_quote_id' => 0,
        'owner_code' => 'TDG-100',
        'code_agency' => 'TDG-100',
        'name_corporate' => 'EMPRESA ILS PEST',
        'rif' => 'J-00000000-0',
        'address' => 'CARACAS',
        'city_id' => 1,
        'country_id' => 1,
        'region_id' => 1,
        'phone' => '02120000000',
        'email' => 'pest@example.com',
        'full_name_contact' => 'CONTACTO PEST',
        'nro_identificacion_contact' => '1234567',
        'phone_contact' => '04120000000',
        'email_contact' => 'contacto@example.com',
        'payment_frequency' => 'ANUAL',
        'fee_anual' => 0,
        'total_amount' => 0,
        'created_by' => 'PEST',
        'status' => 'PRE-APROBADA',
    ]);

    Storage::disk('public')->put('vauches/accidentes.pdf', 'pdf');
    Storage::disk('public')->put('vauches/funerario.pdf', 'pdf');
});

afterEach(function (): void {
    DB::rollBack();
    CorporateAffiliateIlsVoucherManager::flush();
});

/**
 * Guarda sin observers: el modelo se arma a mano y no debe disparar jobs.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @param  TModel  $model
 * @param  array<string, mixed>  $attributes
 * @return TModel
 */
function persistirIls(\Illuminate\Database\Eloquent\Model $model, array $attributes): \Illuminate\Database\Eloquent\Model
{
    $model->forceFill($attributes)->saveQuietly();

    return $model;
}

function coberturaIls(Plan $plan, float $price): Coverage
{
    return persistirIls(new Coverage, [
        'plan_id' => $plan->getKey(),
        'price' => $price,
        'status' => 'ACTIVO',
        'created_by' => 'PEST',
    ]);
}

function beneficioIls(string $description): Benefit
{
    return persistirIls(new Benefit, [
        'description' => $description,
        'created_by' => 'PEST',
    ]);
}

function relacionIls(Plan $plan, Benefit $benefit, Coverage $coverage, ?float $limit): BenefitCoverage
{
    return persistirIls(new BenefitCoverage, [
        'plan_id' => $plan->getKey(),
        'benefit_id' => $benefit->getKey(),
        'coverage_id' => $coverage->getKey(),
        'limit' => $limit,
        'benefit_description' => $benefit->description,
        'coverage_price' => $coverage->price,
        'price' => 0,
    ]);
}

function afiliadoIls(AffiliationCorporate $afiliacion, Plan $plan, ?Coverage $coverage, string $apellido, string $status = 'PRE-APROBADA'): AffiliateCorporate
{
    return persistirIls(new AffiliateCorporate, [
        'affiliation_corporate_id' => $afiliacion->getKey(),
        'first_name' => 'PRUEBA',
        'last_name' => $apellido,
        'nro_identificacion' => (string) random_int(10000000, 99999999),
        'age' => '30',
        'plan_id' => $coverage === null ? null : $plan->getKey(),
        'coverage_id' => $coverage?->getKey(),
        'status' => $status,
    ]);
}

/**
 * @return array<string, mixed>
 */
function bloqueIls(string $codigo, string $documento, string $desde = '2026-10-01', string $hasta = '2027-09-30'): array
{
    return [
        'voucher_code' => $codigo,
        'date_init' => $desde,
        'date_end' => $hasta,
        'document_path' => $documento,
    ];
}

it('solo pide voucher para los beneficios con tope en USD de la cobertura', function (): void {
    $eligible = CorporateAffiliateIlsVoucherManager::eligibleFor($this->plan->getKey(), $this->cov3k->getKey());

    expect(array_column($eligible, 'benefit'))->toBe(['ASISTENCIA MÉDICA POR ACCIDENTES', 'GASTOS FUNERARIOS'])
        ->and(array_column($eligible, 'limit'))->toBe([3000.0, 2000.0])
        ->and(array_unique(array_column($eligible, 'coverage_id')))->toBe([$this->cov3k->getKey()]);

    expect(CorporateAffiliateIlsVoucherManager::eligibleFor(null, $this->cov3k->getKey()))->toBe([]);
});

it('rechaza una selección que mezcla coberturas', function (): void {
    $a = afiliadoIls($this->afiliacion, $this->plan, $this->cov3k, 'UNO');
    $b = afiliadoIls($this->afiliacion, $this->plan, $this->cov10k, 'DOS');

    expect(fn () => CorporateAffiliateIlsVoucherManager::resolveSelection(collect([$a, $b])))
        ->toThrow(InvalidArgumentException::class, 'La selección mezcla coberturas');
});

it('rechaza afiliados sin cobertura o inactivos', function (): void {
    $sinCobertura = afiliadoIls($this->afiliacion, $this->plan, null, 'SINCOB');
    $inactivo = afiliadoIls($this->afiliacion, $this->plan, $this->cov3k, 'BAJA', 'INACTIVO');

    expect(fn () => CorporateAffiliateIlsVoucherManager::resolveSelection(collect([$sinCobertura])))
        ->toThrow(InvalidArgumentException::class, 'no tienen plan o cobertura asignada (SINCOB PRUEBA)');

    expect(fn () => CorporateAffiliateIlsVoucherManager::resolveSelection(collect([$inactivo])))
        ->toThrow(InvalidArgumentException::class, 'inactivos o excluidos (BAJA PRUEBA)');
});

it('avisa cuando la cobertura no tiene beneficios con tope', function (): void {
    $sinTope = coberturaIls($this->plan, 500);
    relacionIls($this->plan, $this->telemedicina, $sinTope, null);
    $a = afiliadoIls($this->afiliacion, $this->plan, $sinTope, 'UNO');

    expect(fn () => CorporateAffiliateIlsVoucherManager::resolveSelection(collect([$a])))
        ->toThrow(InvalidArgumentException::class, 'no tiene beneficios con límite en USD');
});

it('guarda un voucher por beneficio para cada afiliado y deja intactos los bloques vacíos', function (): void {
    $a = afiliadoIls($this->afiliacion, $this->plan, $this->cov3k, 'UNO');
    $b = afiliadoIls($this->afiliacion, $this->plan, $this->cov3k, 'DOS');

    $result = CorporateAffiliateIlsVoucherManager::save($this->afiliacion, [$a->id, $b->id], [
        'b'.$this->accidentes->id => bloqueIls('ILS-ACC-1', 'vauches/accidentes.pdf'),
        'b'.$this->funerario->id => ['voucher_code' => '', 'date_init' => null, 'date_end' => null, 'document_path' => []],
    ], $this->analista->getKey());

    $vouchers = AffiliateCorporateIlsVoucher::query()->whereIn('affiliate_corporate_id', [$a->id, $b->id])->get();

    expect($result['benefits'])->toBe(['ASISTENCIA MÉDICA POR ACCIDENTES'])
        ->and($result['vouchers'])->toBe(2)
        ->and($vouchers)->toHaveCount(2)
        ->and($vouchers->pluck('benefit_id')->unique()->all())->toBe([$this->accidentes->id])
        ->and($vouchers->pluck('coverage_id')->unique()->all())->toBe([$this->cov3k->id])
        ->and((float) $vouchers->first()->limit)->toBe(3000.0)
        ->and($vouchers->first()->date_end->toDateString())->toBe('2027-09-30')
        ->and($vouchers->first()->number_days)->toBe(364)
        ->and($vouchers->first()->created_by)->toBe($this->analista->getKey());

    $status = CorporateAffiliateIlsVoucherManager::statusFor($a->fresh());

    expect($status['expected'])->toBe(2)
        ->and($status['loaded'])->toBe(1)
        ->and(implode(' ', $status['lines']))->toContain('ILS-ACC-1')->toContain('GASTOS FUNERARIOS (US$ 2.000,00): pendiente');
});

it('recargar un beneficio reemplaza su voucher sin duplicarlo', function (): void {
    $a = afiliadoIls($this->afiliacion, $this->plan, $this->cov3k, 'UNO');
    $bloque = 'b'.$this->accidentes->id;

    CorporateAffiliateIlsVoucherManager::save($this->afiliacion, [$a->id], [$bloque => bloqueIls('VIEJO', 'vauches/accidentes.pdf')], null);
    CorporateAffiliateIlsVoucherManager::save($this->afiliacion, [$a->id], [$bloque => bloqueIls('NUEVO', 'vauches/accidentes.pdf')], null);

    expect(AffiliateCorporateIlsVoucher::query()->where('affiliate_corporate_id', $a->id)->pluck('voucher_code')->all())
        ->toBe(['NUEVO']);
});

it('no guarda nada si un bloque quedó a medias', function (string $campo, string $mensaje): void {
    $a = afiliadoIls($this->afiliacion, $this->plan, $this->cov3k, 'UNO');
    $incompleto = bloqueIls('ILS-1', 'vauches/funerario.pdf');
    $incompleto[$campo] = $campo === 'document_path' ? [] : '';

    expect(fn () => CorporateAffiliateIlsVoucherManager::save($this->afiliacion, [$a->id], [
        'b'.$this->accidentes->id => bloqueIls('ILS-ACC', 'vauches/accidentes.pdf'),
        'b'.$this->funerario->id => $incompleto,
    ], null))->toThrow(InvalidArgumentException::class, 'GASTOS FUNERARIOS: falta '.$mensaje);

    // Todo o nada: tampoco se guardó el bloque completo.
    expect(AffiliateCorporateIlsVoucher::query()->where('affiliate_corporate_id', $a->id)->count())->toBe(0);
})->with([
    'sin número' => ['voucher_code', 'el número de voucher'],
    'sin desde' => ['date_init', 'la fecha desde'],
    'sin hasta' => ['date_end', 'la fecha hasta'],
    'sin comprobante' => ['document_path', 'el comprobante'],
]);

it('rechaza vigencias invertidas y comprobantes inexistentes', function (): void {
    $a = afiliadoIls($this->afiliacion, $this->plan, $this->cov3k, 'UNO');
    $bloque = 'b'.$this->accidentes->id;

    expect(fn () => CorporateAffiliateIlsVoucherManager::save($this->afiliacion, [$a->id], [
        $bloque => bloqueIls('ILS-1', 'vauches/accidentes.pdf', '2027-01-01', '2026-01-01'),
    ], null))->toThrow(InvalidArgumentException::class, 'no puede ser anterior');

    expect(fn () => CorporateAffiliateIlsVoucherManager::save($this->afiliacion, [$a->id], [
        $bloque => bloqueIls('ILS-1', 'vauches/no-existe.pdf'),
    ], null))->toThrow(InvalidArgumentException::class, 'el comprobante no se encontró');
});

it('exige al menos un bloque cargado', function (): void {
    $a = afiliadoIls($this->afiliacion, $this->plan, $this->cov3k, 'UNO');

    expect(fn () => CorporateAffiliateIlsVoucherManager::save($this->afiliacion, [$a->id], [], null))
        ->toThrow(InvalidArgumentException::class, 'No cargó ningún voucher');
});

it('no escribe en afiliados de otra afiliación', function (): void {
    $otra = persistirIls($this->afiliacion->replicate(), ['code' => 'PEST-OTRA-'.uniqid()]);
    $ajeno = afiliadoIls($otra, $this->plan, $this->cov3k, 'AJENO');

    expect(fn () => CorporateAffiliateIlsVoucherManager::save($this->afiliacion, [$ajeno->id], [
        'b'.$this->accidentes->id => bloqueIls('ILS-1', 'vauches/accidentes.pdf'),
    ], null))->toThrow(InvalidArgumentException::class, 'ya no pertenecen a esta afiliación');

    expect(AffiliateCorporateIlsVoucher::query()->where('affiliate_corporate_id', $ajeno->id)->count())->toBe(0);
});

it('precarga el voucher solo si todos los seleccionados comparten el mismo', function (): void {
    $a = afiliadoIls($this->afiliacion, $this->plan, $this->cov3k, 'UNO');
    $b = afiliadoIls($this->afiliacion, $this->plan, $this->cov3k, 'DOS');
    $bloque = 'b'.$this->accidentes->id;

    CorporateAffiliateIlsVoucherManager::save($this->afiliacion, [$a->id], [$bloque => bloqueIls('SOLO-UNO', 'vauches/accidentes.pdf')], null);

    $soloA = CorporateAffiliateIlsVoucherManager::resolveSelection(collect([$a->fresh()]));
    $ambos = CorporateAffiliateIlsVoucherManager::resolveSelection(collect([$a->fresh(), $b->fresh()]));

    expect(CorporateAffiliateIlsVoucherManager::formDefaults($soloA)[$bloque]['voucher_code'])->toBe('SOLO-UNO')
        ->and(CorporateAffiliateIlsVoucherManager::formDefaults($ambos)[$bloque]['voucher_code'])->toBeNull()
        ->and(CorporateAffiliateIlsVoucherManager::existingCounts($ambos)[$bloque])->toBe(1);
});

it('la tabla carga vouchers en lote con un bloque por beneficio', function (): void {
    $a = afiliadoIls($this->afiliacion, $this->plan, $this->cov3k, 'UNO');
    $b = afiliadoIls($this->afiliacion, $this->plan, $this->cov3k, 'DOS');

    Livewire::test(CorporateAffiliatesRelationManager::class, [
        'ownerRecord' => $this->afiliacion,
        'pageClass' => ViewAffiliationCorporate::class,
    ])
        ->assertOk()
        ->assertSee('0 de 2')
        ->selectTableRecords([$a->getKey(), $b->getKey()])
        ->mountAction(TestAction::make('asigned_vaucher_ils')->table()->bulk())
        // Un bloque por beneficio con tope; el de telemedicina (sin tope) no.
        ->assertSet('mountedActions.0.data.vouchers', fn (array $vouchers): bool => array_keys($vouchers) === [
            'b'.$this->accidentes->id,
            'b'.$this->funerario->id,
        ])
        ->set('mountedActions.0.data.vouchers.b'.$this->funerario->id.'.voucher_code', 'ILS-FUN-9')
        ->set('mountedActions.0.data.vouchers.b'.$this->funerario->id.'.date_init', '2026-10-01')
        ->set('mountedActions.0.data.vouchers.b'.$this->funerario->id.'.date_end', '2027-10-01')
        ->set('mountedActions.0.data.vouchers.b'.$this->funerario->id.'.document_path', ['f' => 'vauches/funerario.pdf'])
        ->callMountedAction()
        ->assertHasNoErrors()
        ->assertNotified('Vouchers ILS guardados');

    expect(AffiliateCorporateIlsVoucher::query()->whereIn('affiliate_corporate_id', [$a->id, $b->id])->pluck('voucher_code')->all())
        ->toBe(['ILS-FUN-9', 'ILS-FUN-9']);
});

it('la tabla no abre el modal si la selección mezcla coberturas', function (): void {
    $a = afiliadoIls($this->afiliacion, $this->plan, $this->cov3k, 'UNO');
    $b = afiliadoIls($this->afiliacion, $this->plan, $this->cov10k, 'DOS');

    Livewire::test(CorporateAffiliatesRelationManager::class, [
        'ownerRecord' => $this->afiliacion,
        'pageClass' => ViewAffiliationCorporate::class,
    ])
        ->selectTableRecords([$a->getKey(), $b->getKey()])
        ->mountAction(TestAction::make('asigned_vaucher_ils')->table()->bulk())
        ->assertNotified('No se pueden cargar vouchers para esta selección')
        ->assertActionNotMounted(TestAction::make('asigned_vaucher_ils')->table()->bulk());
});

it('un bloque empezado y sin comprobante marca el campo en el modal', function (): void {
    $a = afiliadoIls($this->afiliacion, $this->plan, $this->cov3k, 'UNO');

    Livewire::test(CorporateAffiliatesRelationManager::class, [
        'ownerRecord' => $this->afiliacion,
        'pageClass' => ViewAffiliationCorporate::class,
    ])
        ->mountAction(TestAction::make('upload_info_ils')->table($a))
        ->set('mountedActions.0.data.vouchers.b'.$this->accidentes->id.'.voucher_code', 'ILS-1')
        ->callMountedAction()
        ->assertHasErrors();

    expect(AffiliateCorporateIlsVoucher::query()->where('affiliate_corporate_id', $a->id)->count())->toBe(0);
});
