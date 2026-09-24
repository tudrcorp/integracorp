<?php

declare(strict_types=1);

use App\Enums\StatusPago;
use App\Enums\StatusVaucher;
use App\Filament\Administration\Pages\CompensacionVaucher;
use App\Models\Permission;
use App\Models\TdevReport;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Renderiza la página real del panel. Todo lo que escribe (vouchers de prueba, bitácora)
 * va dentro de una transacción que siempre se revierte: la base de desarrollo no cambia.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Filament::setCurrentPanel('administration');
});

afterEach(function (): void {
    DB::rollBack();
});

function compensacionUser(): User
{
    $user = User::factory()->make(['id' => 999998, 'name' => 'Analista Compensación', 'status' => 'ACTIVO']);
    $user->setRawAttributes([...$user->getAttributes(), 'departament' => json_encode(['ADMINISTRACION'])], true);
    $user->setRelation('permissions', collect([
        new Permission(['slug' => 'compensacion-vaucher', 'module' => 'ADMINISTRACION', 'name' => 'Compensación de voucher']),
    ]));

    return $user;
}

function compensacionVoucher(string $vaucher, string $pasajero, float $pvp, array $overrides = []): TdevReport
{
    return TdevReport::query()->create([
        'agencia' => 'AGENCIA DE PRUEBA',
        'agente_emisor' => 'AGENTE PRUEBA',
        'categoria_del_plan' => 'PRUEBA',
        'descripcion_del_plan' => 'Plan de prueba',
        'estatus_vaucher' => StatusVaucher::Activo->value,
        'fecha' => '2026-09-01',
        'nro_documento' => 'V-00000000',
        'pasajero' => $pasajero,
        'regreso' => '2026-09-10',
        'salida' => '2026-09-02',
        'vaucher' => $vaucher,
        'monto_pvp_precio_de_venta' => $pvp,
        'precio_upgrade' => 0,
        ...$overrides,
    ]);
}

it('muestra el estado vacío antes de buscar', function (): void {
    $this->actingAs(compensacionUser());

    Livewire::test(CompensacionVaucher::class)
        ->assertOk()
        ->assertSeeHtml('/image/logo-tdev.png')
        ->assertSee('Busque un voucher para comenzar')
        ->assertDontSee('Vouchers encontrados');
});

it('exige un término de al menos tres caracteres', function (): void {
    $this->actingAs(compensacionUser());

    Livewire::test(CompensacionVaucher::class)
        ->set('searchData.term', 'TD')
        ->call('searchVouchers')
        ->assertHasErrors(['searchData.term'])
        ->assertSet('resultRecordIds', []);
});

it('avisa cuando no hay coincidencias', function (): void {
    $this->actingAs(compensacionUser());

    Livewire::test(CompensacionVaucher::class)
        ->set('searchData.term', 'ZZ-NO-EXISTE-QA-999')
        ->call('searchVouchers')
        ->assertNotified('Sin resultados')
        ->assertSet('resultRecordIds', []);
});

it('trata los comodines de LIKE como texto literal', function (): void {
    $this->actingAs(compensacionUser());
    compensacionVoucher('QA-COMP-XY1', 'PASAJERO UNO', 10);

    Livewire::test(CompensacionVaucher::class)
        ->set('searchData.term', 'QA-COMP-%')
        ->call('searchVouchers')
        ->assertNotified('Sin resultados');
});

it('encuentra vouchers, totaliza y actualiza el pago de todos en bloque', function (): void {
    $this->actingAs(compensacionUser());
    $a = compensacionVoucher('QA-COMP-001', 'PASAJERO UNO', 8.55);
    $b = compensacionVoucher('QA-COMP-001', 'PASAJERO DOS', 8.55, ['entidad_bancaria_receptora' => 'OTRO BANCO']);

    $page = Livewire::test(CompensacionVaucher::class)
        ->set('searchData.term', 'QA-COMP-001')
        ->call('searchVouchers')
        ->assertSee('Vouchers encontrados')
        ->assertSee('Los vouchers tienen datos de pago distintos')
        ->assertCanSeeTableRecords([$a, $b])
        ->assertSet('resultTotalMontoPvp', 17.1)
        ->assertSee('1 voucher · 1 agencia')
        ->assertSee('Promedio US$ 8,55 por pasajero')
        ->assertSee('0 de 2 pagados')
        ->assertSee('2 sin estatus');

    expect($page->get('resultRecordIds'))->toEqualCanonicalizing([$a->id, $b->id]);

    $page
        ->set('paymentData.forma_pago', array_key_first(\App\Enums\FormaPago::options()))
        ->set('paymentData.estatus_pago', StatusPago::Pendiente->value)
        ->set('paymentData.entidad_bancaria_receptora', 'BANCO QA')
        ->set('paymentData.referencia_bancaria_pago_vaucher_credito', 'REF-123')
        ->set('paymentData.tasa_bcv', 40)
        ->set('paymentData.monto_abonado_en_cuenta_vaucher_credito', 684)
        ->set('paymentData.fecha_pago_vaucher_credito', '2026-09-20')
        ->call('savePaymentTab')
        ->assertHasNoErrors()
        ->assertNotified('Pago actualizado')
        ->assertSet('paymentDataIsMixed', false);

    foreach ([$a, $b] as $record) {
        $record->refresh();
        expect($record->entidad_bancaria_receptora)->toBe('BANCO QA')
            ->and($record->referencia_bancaria_pago_vaucher_credito)->toBe('REF-123')
            ->and($record->estatus_pago)->toBe(StatusPago::Pendiente);
    }
});

it('marca el pago como pagado al cargar un comprobante nuevo', function (): void {
    Storage::fake('public');
    $this->actingAs(compensacionUser());
    $a = compensacionVoucher('QA-COMP-008', 'PASAJERO UNO', 10, ['estatus_pago' => StatusPago::Pendiente->value]);

    Livewire::test(CompensacionVaucher::class)
        ->set('searchData.term', 'QA-COMP-008')
        ->call('searchVouchers')
        ->set('paymentData.comprobante_pago', [UploadedFile::fake()->image('comprobante.png')])
        ->set('paymentData.estatus_pago', StatusPago::Pendiente->value)
        ->call('savePaymentTab')
        ->assertHasNoErrors()
        ->assertNotified('Pago actualizado');

    $a->refresh();
    expect($a->estatus_pago)->toBe(StatusPago::Pagado)
        ->and($a->comprobante_pago_path)->toStartWith('tdev-reports/compensacion-vaucher/comprobantes/');
    Storage::disk('public')->assertExists($a->comprobante_pago_path);
});

it('sugiere el monto en bolívares a partir de la tasa BCV', function (): void {
    $this->actingAs(compensacionUser());
    compensacionVoucher('QA-COMP-002', 'PASAJERO UNO', 10);

    $page = Livewire::test(CompensacionVaucher::class)
        ->set('searchData.term', 'QA-COMP-002')
        ->call('searchVouchers');

    expect($page->instance()->suggestedAmountVes(36.5))->toBe(365.0)
        ->and($page->instance()->suggestedAmountVes(0))->toBeNull()
        ->and($page->instance()->suggestedAmountVes('abc'))->toBeNull();
});

it('recalcula la comisión de todos los vouchers con el porcentaje indicado', function (): void {
    $this->actingAs(compensacionUser());
    $a = compensacionVoucher('QA-COMP-003', 'PASAJERO UNO', 100, ['precio_upgrade' => 20]);
    $b = compensacionVoucher('QA-COMP-003', 'PASAJERO DOS', 50);

    $page = Livewire::test(CompensacionVaucher::class)
        ->set('searchData.term', 'QA-COMP-003')
        ->call('searchVouchers');

    expect($page->instance()->projectedCommission(10))->toBe(17.0);

    $page
        ->set('commissionData.porcentaje_comision', 10)
        ->call('saveCommissionTab')
        ->assertHasNoErrors()
        ->assertNotified('Comisión recalculada')
        ->assertSet('resultTotalMontoComision', 17.0);

    expect((float) $a->refresh()->monto_comision)->toBe(12.0)
        ->and((float) $b->refresh()->monto_comision)->toBe(5.0);
});

it('resume el cobro y el porcentaje efectivo de comisión en las tarjetas', function (): void {
    $this->actingAs(compensacionUser());
    compensacionVoucher('QA-COMP-009', 'PASAJERO UNO', 100, ['estatus_pago' => StatusPago::Pagado->value, 'monto_comision' => 10, 'agencia' => 'AGENCIA A']);
    compensacionVoucher('QA-COMP-009', 'PASAJERO DOS', 100, ['estatus_pago' => StatusPago::Pendiente->value, 'monto_comision' => 10, 'agencia' => 'AGENCIA B']);
    compensacionVoucher('QA-COMP-0091', 'PASAJERO TRES', 100, ['estatus_pago' => StatusPago::Anulado->value, 'monto_comision' => 10, 'precio_upgrade' => 50, 'agencia' => 'agencia b ']);

    $page = Livewire::test(CompensacionVaucher::class)
        ->set('searchData.term', 'QA-COMP-009')
        ->call('searchVouchers')
        ->assertSee('2 vouchers · 2 agencias')
        ->assertSee('1 de 3 pagados')
        ->assertSee('1 pendiente · 1 anulado')
        ->assertSee('upgrades US$ 50,00');

    expect($page->instance()->effectiveCommissionRate())->toBe(8.57);
});

it('rechaza porcentajes de comisión fuera de rango', function (): void {
    $this->actingAs(compensacionUser());
    $a = compensacionVoucher('QA-COMP-004', 'PASAJERO UNO', 100);

    Livewire::test(CompensacionVaucher::class)
        ->set('searchData.term', 'QA-COMP-004')
        ->call('searchVouchers')
        ->set('commissionData.porcentaje_comision', 150)
        ->call('saveCommissionTab')
        ->assertHasErrors(['commissionData.porcentaje_comision']);

    expect($a->refresh()->monto_comision)->toBeNull();
});

it('no anula sin motivo y anula en cascada tras confirmar con motivo', function (): void {
    $this->actingAs(compensacionUser());
    $a = compensacionVoucher('QA-COMP-005', 'PASAJERO UNO', 10);

    $page = Livewire::test(CompensacionVaucher::class)
        ->set('searchData.term', 'QA-COMP-005')
        ->call('searchVouchers')
        ->set('statusData.estatus_vaucher', StatusVaucher::Anulado->value)
        ->set('statusData.observacion_anulacion', '')
        ->call('requestStatusSave')
        ->assertHasErrors(['statusData.observacion_anulacion'])
        ->assertActionNotMounted('saveStatus');

    expect($a->refresh()->estatus_vaucher)->toBe(StatusVaucher::Activo);

    $page
        ->set('statusData.observacion_anulacion', '<p>Viaje cancelado por el cliente</p>')
        ->call('requestStatusSave')
        ->assertHasNoErrors()
        ->assertActionMounted('saveStatus');

    expect($a->refresh()->estatus_vaucher)->toBe(StatusVaucher::Activo);

    $page
        ->callMountedAction()
        ->assertNotified('Estatus actualizado');

    $a->refresh();
    expect($a->estatus_vaucher)->toBe(StatusVaucher::Anulado)
        ->and($a->estatus_pago)->toBe(StatusPago::Anulado);
});

it('cambia a un estatus no destructivo sin pedir confirmación', function (): void {
    $this->actingAs(compensacionUser());
    $a = compensacionVoucher('QA-COMP-007', 'PASAJERO UNO', 10, ['estatus_vaucher' => StatusVaucher::Expirado->value]);

    Livewire::test(CompensacionVaucher::class)
        ->set('searchData.term', 'QA-COMP-007')
        ->call('searchVouchers')
        ->set('statusData.estatus_vaucher', StatusVaucher::Activo->value)
        ->call('requestStatusSave')
        ->assertActionNotMounted('saveStatus')
        ->assertNotified('Estatus actualizado');

    expect($a->refresh()->estatus_vaucher)->toBe(StatusVaucher::Activo);
});

it('abre con el voucher del enlace ya buscado y con coincidencia exacta', function (): void {
    $this->actingAs(compensacionUser());
    $exact = compensacionVoucher('QA-COMP-LNK-1', 'PASAJERO UNO', 10);
    $sameVoucher = compensacionVoucher('QA-COMP-LNK-1', 'PASAJERO DOS', 10);
    $similar = compensacionVoucher('QA-COMP-LNK-10', 'PASAJERO TRES', 10);

    $page = Livewire::withQueryParams(['vaucher' => 'QA-COMP-LNK-1'])
        ->test(CompensacionVaucher::class)
        ->assertSet('searchData.term', 'QA-COMP-LNK-1')
        ->assertSee('Vouchers encontrados')
        ->assertCanSeeTableRecords([$exact, $sameVoucher])
        ->assertCanNotSeeTableRecords([$similar]);

    expect($page->get('resultRecordIds'))->toEqualCanonicalizing([$exact->id, $sameVoucher->id]);
});

it('ignora un voucher de enlace vacío o demasiado corto', function (string $value): void {
    $this->actingAs(compensacionUser());

    Livewire::withQueryParams(['vaucher' => $value])
        ->test(CompensacionVaucher::class)
        ->assertOk()
        ->assertSet('resultRecordIds', [])
        ->assertSee('Busque un voucher para comenzar');
})->with(['vacío' => '', 'corto' => 'TD', 'espacios' => '   ']);

it('arma la URL de compensación con el voucher como parámetro', function (): void {
    $url = CompensacionVaucher::getUrlForVoucher('TD-0J1EM0');

    expect($url)->toContain('/administration/compensacion-vaucher')
        ->and($url)->toContain('vaucher=TD-0J1EM0');
});

it('enlaza el número de voucher del reporte TDEV a la compensación', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/TdevReports/Tables/TdevReportsTable.php');

    expect($src)->toContain('CompensacionVaucher::getUrlForVoucher((string) $record->vaucher)')
        ->and($src)->toContain('CompensacionVaucher::canAccess()');
});

it('limpia la búsqueda y vuelve al estado vacío', function (): void {
    $this->actingAs(compensacionUser());
    compensacionVoucher('QA-COMP-006', 'PASAJERO UNO', 10);

    Livewire::test(CompensacionVaucher::class)
        ->set('searchData.term', 'QA-COMP-006')
        ->call('searchVouchers')
        ->assertSee('Vouchers encontrados')
        ->callAction('clearResults')
        ->assertSet('resultRecordIds', [])
        ->assertSee('Busque un voucher para comenzar');
});

it('no permite que el cliente altere los ids sobre los que se guarda', function (): void {
    $this->actingAs(compensacionUser());

    Livewire::test(CompensacionVaucher::class)
        ->set('resultRecordIds', [1, 2, 3]);
})->throws(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);
