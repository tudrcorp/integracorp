<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\Affiliations\Pages\AffiliationPayments as AdministrationAffiliationPayments;
use App\Filament\Business\Resources\Affiliations\AffiliationResource as BusinessAffiliationResource;
use App\Filament\Business\Resources\Affiliations\Pages\AffiliationPayments as BusinessAffiliationPayments;
use App\Filament\Business\Resources\Affiliations\Pages\ViewAffiliation as BusinessViewAffiliation;
use App\Filament\Shared\Affiliations\ManageAffiliationPayments;
use App\Models\Affiliation;
use App\Models\PaidMembership;
use App\Models\User;
use App\Support\Affiliations\PaymentVoucherFile;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    Storage::fake('public');
});

afterEach(fn () => DB::rollBack());

function actingAsPaymentsAnalyst(array $departments = ['SUPERADMIN', 'NEGOCIOS', 'ADMINISTRACION']): User
{
    $usuario = User::factory()->create([
        'email' => 'qa.pagos.afiliacion.'.uniqid().'@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => $departments,
    ]);

    test()->actingAs($usuario);

    return $usuario;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function pestPayment(int $affiliationId, array $attributes = []): PaidMembership
{
    return PaidMembership::query()->forceCreate([
        'affiliation_id' => $affiliationId,
        'status' => 'APROBADO',
        'pay_amount_usd' => 327,
        'pay_amount_ves' => 0,
        'total_amount' => 327,
        'payment_method' => 'ZELLE',
        'payment_method_usd' => 'ZELLE',
        'bank_usd' => 'BANK OF AMERICA',
        'reference_payment_usd' => 'REF-QA-'.uniqid(),
        'payment_frequency' => 'TRIMESTRAL',
        'payment_date' => '08/10/2026',
        'prox_payment_date' => '08/01/2027',
        'document_usd' => 'N/A',
        'document_ves' => 'N/A',
        'aproved_by' => 'ANALISTA QA',
        ...$attributes,
    ]);
}

/*
 * ---------------------------------------------------------------------------
 * Archivo del comprobante
 * ---------------------------------------------------------------------------
 */

it('normaliza la ruta del comprobante y trata N/A y vacío como sin archivo', function (?string $stored, ?string $expected): void {
    $payment = new PaidMembership(['document_usd' => $stored]);

    expect(PaymentVoucherFile::path($payment, PaymentVoucherFile::CURRENCY_USD))->toBe($expected);
})->with([
    'archivo' => ['01K6NZAG6M43V8F7BY3ZJY2514.jpg', '01K6NZAG6M43V8F7BY3ZJY2514.jpg'],
    'N/A' => ['N/A', null],
    'n/a con espacios' => [' n/a ', null],
    'vacío' => ['', null],
    'nulo' => [null, null],
    'ruta pública vieja' => ['/storage/vouchers/pago.pdf', 'vouchers/pago.pdf'],
]);

it('distingue imagen, PDF y otros formatos para la vista previa', function (): void {
    expect(PaymentVoucherFile::kind('a.JPG'))->toBe('image')
        ->and(PaymentVoucherFile::kind('a.jfif'))->toBe('image')
        ->and(PaymentVoucherFile::kind('a.pdf'))->toBe('pdf')
        ->and(PaymentVoucherFile::kind('a.docx'))->toBe('other')
        ->and(PaymentVoucherFile::kind(null))->toBe('other');
});

it('no acepta rutas que salgan del disco', function (): void {
    Storage::disk('public')->put('ok.png', 'x');

    expect(PaymentVoucherFile::exists('ok.png'))->toBeTrue()
        ->and(PaymentVoucherFile::exists('../.env'))->toBeFalse()
        ->and(PaymentVoucherFile::exists(null))->toBeFalse();
});

it('resume referencias y comprobantes por moneda', function (): void {
    $payment = new PaidMembership([
        'reference_payment_usd' => 'Z-1',
        'reference_payment_ves' => 'N/A',
        'document_usd' => 'a.png',
        'document_ves' => 'b.pdf',
    ]);

    expect(ManageAffiliationPayments::references($payment))->toBe('US$: Z-1')
        ->and(ManageAffiliationPayments::voucherSummary($payment))->toBe('Cargado (US$ y Bs.)')
        ->and(ManageAffiliationPayments::voucherSummary(new PaidMembership(['document_usd' => 'N/A'])))->toBe('Sin comprobante')
        ->and(ManageAffiliationPayments::statusColor('PENDIENTE'))->toBe('warning')
        ->and(ManageAffiliationPayments::statusColor('aprobado'))->toBe('success');
});

/*
 * ---------------------------------------------------------------------------
 * Página «Pagos realizados»
 * ---------------------------------------------------------------------------
 */

it('lista todos los pagos de la afiliación, de cualquier estatus, y solo los suyos', function (): void {
    actingAsPaymentsAnalyst();
    Filament::setCurrentPanel('business');

    $aprobado = pestPayment(436);
    $pendiente = pestPayment(436, ['status' => 'PENDIENTE', 'aproved_by' => null]);
    $ajeno = pestPayment(183);

    Livewire::test(BusinessAffiliationPayments::class, ['record' => 436])
        ->assertOk()
        ->assertSee('Pagos realizados')
        ->assertSee('TDEC-IND-000436')
        ->assertCanSeeTableRecords([$aprobado, $pendiente])
        ->assertCanNotSeeTableRecords([$ajeno])
        ->filterTable('status', 'PENDIENTE')
        ->assertCanSeeTableRecords([$pendiente])
        ->assertCanNotSeeTableRecords([$aprobado]);
});

it('es solo lectura: no ofrece crear, editar, aprobar ni borrar pagos', function (): void {
    actingAsPaymentsAnalyst();
    Filament::setCurrentPanel('business');
    $pago = pestPayment(436);

    $componente = Livewire::test(BusinessAffiliationPayments::class, ['record' => 436]);

    foreach (['create', 'edit', 'delete', 'approve'] as $accion) {
        $componente->assertActionDoesNotExist(TestAction::make($accion)->table($pago));
    }
});

it('muestra las acciones del comprobante solo para la moneda que lo tiene', function (): void {
    actingAsPaymentsAnalyst();
    Filament::setCurrentPanel('business');

    $soloUsd = pestPayment(436, ['document_usd' => 'voucher-usd.png']);
    $sinArchivo = pestPayment(436);

    Livewire::test(BusinessAffiliationPayments::class, ['record' => 436])
        ->assertTableActionVisible('viewVoucherUsd', $soloUsd)
        ->assertTableActionVisible('downloadVoucherUsd', $soloUsd)
        ->assertTableActionHidden('viewVoucherVes', $soloUsd)
        ->assertTableActionHidden('downloadVoucherVes', $soloUsd)
        ->assertTableActionHidden('viewVoucherUsd', $sinArchivo);
});

it('descarga el comprobante con un nombre que identifica el pago', function (): void {
    actingAsPaymentsAnalyst();
    Filament::setCurrentPanel('business');
    Storage::disk('public')->put('voucher-usd.png', UploadedFile::fake()->image('v.png')->getContent());
    $pago = pestPayment(436, ['document_usd' => 'voucher-usd.png']);

    $respuesta = ManageAffiliationPayments::download($pago, PaymentVoucherFile::CURRENCY_USD, 'TDEC-IND-000436');

    expect($respuesta)->not->toBeNull()
        ->and($respuesta->headers->get('content-disposition'))->toContain('voucher-TDEC-IND-000436-pago-'.$pago->id.'-usd.png');

    Livewire::test(BusinessAffiliationPayments::class, ['record' => 436])
        ->callTableAction('downloadVoucherUsd', $pago)
        ->assertFileDownloaded('voucher-TDEC-IND-000436-pago-'.$pago->id.'-usd.png');
});

it('si el archivo ya no existe avisa en español en vez de fallar', function (): void {
    actingAsPaymentsAnalyst();
    Filament::setCurrentPanel('business');
    $pago = pestPayment(436, ['document_ves' => 'ya-no-existe.pdf']);

    Livewire::test(BusinessAffiliationPayments::class, ['record' => 436])
        ->callTableAction('downloadVoucherVes', $pago)
        ->assertNotified('No se encontró el comprobante');

    Livewire::test(BusinessAffiliationPayments::class, ['record' => 436])
        ->mountTableAction('viewVoucherVes', $pago)
        ->assertMountedActionModalSee('No se encontró el archivo de este comprobante');
});

it('la vista previa muestra la imagen del comprobante', function (): void {
    actingAsPaymentsAnalyst();
    Filament::setCurrentPanel('business');
    Storage::disk('public')->put('voucher-usd.png', 'x');
    $pago = pestPayment(436, ['document_usd' => 'voucher-usd.png']);

    Livewire::test(BusinessAffiliationPayments::class, ['record' => 436])
        ->mountTableAction('viewVoucherUsd', $pago)
        ->assertMountedActionModalSeeHtml('alt="Comprobante de pago"')
        ->assertMountedActionModalSee('Archivo: voucher-usd.png');
});

it('quien no tiene acceso a afiliaciones no entra a los pagos', function (): void {
    actingAsPaymentsAnalyst(['MARKETING']);
    Filament::setCurrentPanel('business');

    expect(BusinessAffiliationPayments::canAccess())->toBeFalse();

    Livewire::test(BusinessAffiliationPayments::class, ['record' => 436])->assertForbidden();
});

it('también existe en Administración con su propia URL', function (): void {
    actingAsPaymentsAnalyst();
    Filament::setCurrentPanel('administration');
    $pago = pestPayment(436);

    Livewire::test(AdministrationAffiliationPayments::class, ['record' => 436])
        ->assertOk()
        ->assertCanSeeTableRecords([$pago]);

    expect(App\Filament\Administration\Resources\Affiliations\AffiliationResource::getUrl('payments', ['record' => 436]))
        ->toContain('/administration/affiliations/436/pagos');
});

/*
 * ---------------------------------------------------------------------------
 * Acceso desde la ficha
 * ---------------------------------------------------------------------------
 */

it('la ficha de la afiliación lleva a los pagos desde «Plan y pagos» y desde Acciones', function (): void {
    actingAsPaymentsAnalyst();
    Filament::setCurrentPanel('business');

    $url = BusinessAffiliationResource::getUrl('payments', ['record' => 436]);

    Livewire::test(BusinessViewAffiliation::class, ['record' => 436])
        ->assertOk()
        ->assertActionVisible(TestAction::make('viewPayments')->schemaComponent('plan-pagos.planAndPayments', schema: 'infolist'))
        ->assertActionHasUrl(TestAction::make('viewPayments')->schemaComponent('plan-pagos.planAndPayments', schema: 'infolist'), $url)
        ->assertActionHasUrl('goToPayments', $url);
});

it('resume los pagos: cuenta por estatus y solo suma lo aprobado', function (): void {
    $antes = ManageAffiliationPayments::paymentsSummary(Affiliation::query()->findOrFail(436));

    pestPayment(436, ['pay_amount_usd' => 100, 'pay_amount_ves' => 3650]);
    pestPayment(436, ['status' => 'PAGADO', 'pay_amount_usd' => 50, 'pay_amount_ves' => 0]);
    pestPayment(436, ['status' => 'PENDIENTE', 'pay_amount_usd' => 999, 'pay_amount_ves' => 999]);

    $resumen = ManageAffiliationPayments::paymentsSummary(Affiliation::query()->findOrFail(436));

    expect($resumen['count'] - $antes['count'])->toBe(3)
        ->and($resumen['approved'] - $antes['approved'])->toBe(2)
        ->and($resumen['pending'] - $antes['pending'])->toBe(1)
        ->and(round($resumen['approved_usd'] - $antes['approved_usd'], 2))->toBe(150.0)
        ->and(round($resumen['approved_ves'] - $antes['approved_ves'], 2))->toBe(3650.0)
        ->and($resumen['last_at'])->toBe(now()->format('d/m/Y'));
});

it('el encabezado muestra titular, código, plan y el resumen de pagos', function (): void {
    actingAsPaymentsAnalyst();
    Filament::setCurrentPanel('business');
    pestPayment(436, ['status' => 'PENDIENTE']);

    $afiliacion = Affiliation::query()->findOrFail(436);

    Livewire::test(BusinessAffiliationPayments::class, ['record' => 436])
        ->assertSee('Pagos realizados · '.$afiliacion->code)
        ->assertSee($afiliacion->full_name_ti)
        ->assertSee('Pagos registrados')
        ->assertSee('pendiente')
        ->assertSee('Volver a la afiliación');
});

/*
 * ---------------------------------------------------------------------------
 * Afiliaciones corporativas (Administración)
 * ---------------------------------------------------------------------------
 */

/**
 * @param  array<string, mixed>  $attributes
 */
function pestCorporatePayment(int $affiliationCorporateId, array $attributes = []): App\Models\PaidMembershipCorporate
{
    return App\Models\PaidMembershipCorporate::query()->forceCreate([
        'affiliation_corporate_id' => $affiliationCorporateId,
        'status' => 'APROBADO',
        'pay_amount_usd' => 1181.5,
        'pay_amount_ves' => 0,
        'total_amount' => 1181.5,
        'payment_method' => 'TRANSFERENCIA VES',
        'reference_payment_usd' => 'REF-QA-COR-'.uniqid(),
        'payment_frequency' => 'TRIMESTRAL',
        'payment_date' => '08/10/2026',
        'prox_payment_date' => '08/01/2027',
        'document_usd' => 'N/A',
        'document_ves' => 'N/A',
        ...$attributes,
    ]);
}

it('lista los pagos de la corporativa con la empresa en el encabezado', function (): void {
    actingAsPaymentsAnalyst();
    Filament::setCurrentPanel('administration');

    $propio = pestCorporatePayment(58, ['status' => 'PENDIENTE']);
    $ajeno = pestCorporatePayment(57);
    $empresa = App\Models\AffiliationCorporate::query()->findOrFail(58);

    Livewire::test(App\Filament\Administration\Resources\AffiliationCorporates\Pages\AffiliationCorporatePayments::class, ['record' => 58])
        ->assertOk()
        ->assertSee('Pagos realizados · '.$empresa->code)
        ->assertSee($empresa->name_corporate)
        ->assertSee('RIF')
        ->assertCanSeeTableRecords([$propio])
        ->assertCanNotSeeTableRecords([$ajeno]);
});

it('descarga el comprobante corporativo con el código de la afiliación', function (): void {
    actingAsPaymentsAnalyst();
    Filament::setCurrentPanel('administration');
    Storage::disk('public')->put('voucher-cor.pdf', '%PDF-1.4');
    $pago = pestCorporatePayment(58, ['document_ves' => 'voucher-cor.pdf']);

    Livewire::test(App\Filament\Administration\Resources\AffiliationCorporates\Pages\AffiliationCorporatePayments::class, ['record' => 58])
        ->assertTableActionHidden('viewVoucherUsd', $pago)
        ->callTableAction('downloadVoucherVes', $pago)
        ->assertFileDownloaded('voucher-TDEC-COR-00058-pago-'.$pago->id.'-ves.pdf');
});

it('resume los pagos corporativos sobre su propia tabla', function (): void {
    $empresa = App\Models\AffiliationCorporate::query()->findOrFail(58);
    $antes = App\Filament\Shared\Affiliations\ManageAffiliationCorporatePayments::paymentsSummary($empresa);

    pestCorporatePayment(58, ['pay_amount_usd' => 200]);
    pestCorporatePayment(58, ['status' => 'PENDIENTE', 'pay_amount_usd' => 999]);

    $despues = App\Filament\Shared\Affiliations\ManageAffiliationCorporatePayments::paymentsSummary($empresa);

    expect($despues['count'] - $antes['count'])->toBe(2)
        ->and($despues['pending'] - $antes['pending'])->toBe(1)
        ->and(round($despues['approved_usd'] - $antes['approved_usd'], 2))->toBe(200.0);
});

it('la ficha corporativa de Administración lleva a los pagos desde «Pagos» y desde Acciones', function (): void {
    actingAsPaymentsAnalyst();
    Filament::setCurrentPanel('administration');

    $url = App\Filament\Administration\Resources\AffiliationCorporates\AffiliationCorporateResource::getUrl('payments', ['record' => 58]);

    expect($url)->toContain('/administration/affiliation-corporates/58/pagos');

    Livewire::test(App\Filament\Administration\Resources\AffiliationCorporates\Pages\ViewAffiliationCorporate::class, ['record' => 58])
        ->assertOk()
        ->assertActionHasUrl(TestAction::make('viewPayments')->schemaComponent('pagos.corporatePayments', schema: 'infolist'), $url)
        ->assertActionHasUrl('goToPayments', $url);
});

it('en Negocios la ficha corporativa no muestra el botón: allí no hay página de pagos', function (): void {
    actingAsPaymentsAnalyst();
    Filament::setCurrentPanel('business');

    Livewire::test(App\Filament\Business\Resources\AffiliationCorporates\Pages\ViewAffiliationCorporate::class, ['record' => 58])
        ->assertOk()
        ->assertDontSee('Ver pagos realizados');
});

/*
 * ---------------------------------------------------------------------------
 * Generar Factura desde un pago (solo Administración, solo aprobados)
 * ---------------------------------------------------------------------------
 */

afterEach(function (): void {
    foreach (glob(public_path('storage/facturas/FACT-PEST-PAGO-*')) ?: [] as $file) {
        @unlink($file);
    }
});

/**
 * @param  array<string, mixed>  $attributes
 */
function pestPaymentSale(string $affiliationCode, string $receipt, array $attributes = []): App\Models\Sale
{
    return App\Models\Sale::query()->forceCreate([
        'date_activation' => '08/10/2026',
        'owner_code' => 'TDG-100',
        'code_agency' => 'TDG-100',
        'invoice_number' => $receipt,
        'persons' => '1',
        'type' => 'AFILIACION INDIVIDUAL',
        'affiliation_code' => $affiliationCode,
        'affiliate_full_name' => 'TITULAR QA',
        'affiliate_ci_rif' => '12345678',
        'payment_method' => 'TRANSFERENCIA VES',
        'payment_frequency' => 'TRIMESTRAL',
        'reference_payment' => '52958',
        'pay_amount_usd' => 0,
        'pay_amount_ves' => 722023.66,
        'total_amount' => 842.27,
        ...$attributes,
    ]);
}

function adminIndividualPaymentsPage(): \Livewire\Features\SupportTesting\Testable
{
    return Livewire::test(AdministrationAffiliationPayments::class, ['record' => 436]);
}

it('Generar Factura solo existe en Administración y solo para pagos aprobados', function (): void {
    actingAsPaymentsAnalyst();

    $aprobado = pestPayment(436, ['invoice_number' => 'PEST-REC-'.uniqid()]);
    $pendiente = pestPayment(436, ['status' => 'PENDIENTE']);

    Filament::setCurrentPanel('administration');
    adminIndividualPaymentsPage()
        ->assertTableActionVisible('generateInvoice', $aprobado)
        ->assertTableActionHidden('generateInvoice', $pendiente);

    Filament::setCurrentPanel('business');
    Livewire::test(BusinessAffiliationPayments::class, ['record' => 436])
        ->assertTableActionHidden('generateInvoice', $aprobado);
});

it('se bloquea con el motivo cuando el pago no tiene venta, o tiene más de una', function (): void {
    actingAsPaymentsAnalyst();
    Filament::setCurrentPanel('administration');

    $sinRecibo = pestPayment(436, ['invoice_number' => null]);
    $sinVenta = pestPayment(436, ['invoice_number' => 'PEST-SIN-VENTA-'.uniqid()]);
    $recibo = 'PEST-DOBLE-'.uniqid();
    $ambiguo = pestPayment(436, ['invoice_number' => $recibo]);
    pestPaymentSale('TDEC-IND-000436', $recibo);
    pestPaymentSale('TDEC-IND-000436', $recibo);

    adminIndividualPaymentsPage()
        ->assertTableActionDisabled('generateInvoice', $sinRecibo)
        ->assertTableActionDisabled('generateInvoice', $sinVenta)
        ->assertTableActionDisabled('generateInvoice', $ambiguo);

    $resolver = new App\Support\Sales\PaymentSaleResolver('TDEC-IND-000436');

    expect($resolver->resolve($sinRecibo)['status'])->toBe(App\Support\Sales\PaymentSaleResolver::NO_RECEIPT)
        ->and($resolver->resolve($sinVenta)['status'])->toBe(App\Support\Sales\PaymentSaleResolver::NOT_FOUND)
        ->and($resolver->resolve($ambiguo)['status'])->toBe(App\Support\Sales\PaymentSaleResolver::AMBIGUOUS);
});

it('no confunde la venta de otra afiliación con el mismo recibo', function (): void {
    $recibo = 'PEST-COMPARTIDO-'.uniqid();
    pestPaymentSale('TDEC-IND-000183', $recibo);
    $propia = pestPaymentSale('TDEC-IND-000436', $recibo);
    $pago = pestPayment(436, ['invoice_number' => $recibo]);

    $resultado = (new App\Support\Sales\PaymentSaleResolver('TDEC-IND-000436'))->resolve($pago);

    expect($resultado['status'])->toBe(App\Support\Sales\PaymentSaleResolver::OK)
        ->and($resultado['sale']->is($propia))->toBeTrue();
});

it('factura en bolívares la cuota del pago por la tasa del analista y la registra en la venta', function (): void {
    actingAsPaymentsAnalyst();
    Filament::setCurrentPanel('administration');

    $recibo = 'PEST-REC-'.uniqid();
    $venta = pestPaymentSale('TDEC-IND-000436', $recibo);
    $pago = pestPayment(436, ['invoice_number' => $recibo, 'total_amount' => 842.27, 'pay_amount_usd' => 0]);
    $numero = 'PEST-PAGO-'.uniqid();

    adminIndividualPaymentsPage()
        ->mountTableAction('generateInvoice', $pago)
        ->assertMountedActionModalSee('US$ 842,27')
        ->set('mountedActions.0.data.invoice_number', $numero)
        ->set('mountedActions.0.data.date', '2026-10-15 00:00:00')
        ->set('mountedActions.0.data.tasa_bcv', '857.24')
        ->assertMountedActionModalSee('Bs. 722.027,53')
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors()
        ->assertFileDownloaded('FACT-'.$numero.'.pdf');

    $venta->refresh();

    expect($venta->invoice_generated)->toBe($numero)
        ->and($venta->invoice_snapshot['date'])->toBe('15/10/2026')
        ->and($venta->invoice_snapshot['tasa_bcv'])->toBe(857.24)
        ->and($venta->invoice_snapshot['base_usd'])->toBe(842.27)
        ->and($venta->invoice_snapshot['total_ves'])->toBe(722027.53)
        ->and($venta->invoice_snapshot['source_payment'])->toEqual(['table' => 'paid_memberships', 'id' => $pago->id])
        ->and(is_file(App\Services\SaleInvoicePdfService::pdfPath($numero)))->toBeTrue();
});

it('exige la tasa BCV y rechaza un número de factura ya emitido', function (): void {
    actingAsPaymentsAnalyst();
    Filament::setCurrentPanel('administration');

    $usado = 'PEST-PAGO-'.uniqid();
    pestPaymentSale('TDEC-IND-000183', 'PEST-OTRO-'.uniqid(), ['invoice_generated' => $usado]);
    $recibo = 'PEST-REC-'.uniqid();
    $venta = pestPaymentSale('TDEC-IND-000436', $recibo);
    $pago = pestPayment(436, ['invoice_number' => $recibo]);

    adminIndividualPaymentsPage()
        ->mountTableAction('generateInvoice', $pago)
        ->set('mountedActions.0.data.invoice_number', $usado)
        ->set('mountedActions.0.data.date', '2026-10-15 00:00:00')
        ->set('mountedActions.0.data.tasa_bcv', null)
        ->callMountedTableAction()
        ->assertHasTableActionErrors(['invoice_number', 'tasa_bcv']);

    expect($venta->refresh()->invoice_generated)->toBeNull();
});

it('si la venta ya tiene factura la regenera con la tasa nueva, mismo número y fecha', function (): void {
    actingAsPaymentsAnalyst();
    Filament::setCurrentPanel('administration');

    $recibo = 'PEST-REC-'.uniqid();
    $numero = 'PEST-PAGO-'.uniqid();
    $venta = pestPaymentSale('TDEC-IND-000436', $recibo, [
        'invoice_generated' => $numero,
        'invoice_snapshot' => ['date' => '01/10/2026', 'invoice_in_name_of' => 'titular', 'tasa_bcv' => 800, 'issued_at' => '2026-10-01T09:00:00-04:00'],
    ]);
    $pago = pestPayment(436, ['invoice_number' => $recibo, 'total_amount' => 100]);

    adminIndividualPaymentsPage()
        ->mountTableAction('generateInvoice', $pago)
        ->assertMountedActionModalSee('Regenerar factura N° '.$numero)
        ->assertSet('mountedActions.0.data.tasa_bcv', 800)
        ->set('mountedActions.0.data.tasa_bcv', '900')
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors()
        ->assertFileDownloaded('FACT-'.$numero.'.pdf');

    $venta->refresh();

    expect($venta->invoice_generated)->toBe($numero)
        ->and($venta->invoice_snapshot['date'])->toBe('01/10/2026')
        ->and($venta->invoice_snapshot['total_ves'])->toEqual(90000)
        ->and($venta->invoice_snapshot)->toHaveKey('regenerated_at');
});

it('factura un pago corporativo con la plantilla corporativa aunque el tipo venga con tilde', function (): void {
    actingAsPaymentsAnalyst();
    Filament::setCurrentPanel('administration');

    $recibo = 'PEST-REC-COR-'.uniqid();
    $venta = pestPaymentSale('TDEC-COR-00058', $recibo, ['type' => 'AFILIACIÓN CORPORATIVA']);
    $pago = pestCorporatePayment(58, ['invoice_number' => $recibo, 'total_amount' => 1181.5]);
    $numero = 'PEST-PAGO-'.uniqid();

    Livewire::test(App\Filament\Administration\Resources\AffiliationCorporates\Pages\AffiliationCorporatePayments::class, ['record' => 58])
        ->assertTableActionVisible('generateInvoice', $pago)
        ->mountTableAction('generateInvoice', $pago)
        ->set('mountedActions.0.data.invoice_number', $numero)
        ->set('mountedActions.0.data.date', '2026-10-08 00:00:00')
        ->set('mountedActions.0.data.tasa_bcv', '874.2298')
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $venta->refresh();

    expect(App\Services\SaleInvoicePdfService::isCorporate($venta))->toBeTrue()
        ->and($venta->invoice_snapshot['total_ves'])->toBe(round(1181.5 * 874.2298, 2))
        ->and($venta->invoice_snapshot['period_from'])->not->toBeNull()
        ->and($venta->invoice_snapshot['source_payment']['table'])->toBe('paid_membership_corporates');
});

it('el servicio calcula cuota × tasa y no factura una cuota en cero', function (): void {
    $venta = new App\Models\Sale(['type' => 'AFILIACION INDIVIDUAL', 'pay_amount_usd' => 0, 'pay_amount_ves' => 5]);

    expect(App\Services\SaleInvoicePdfService::totalVes($venta, 857.24, 842.27))->toBe(722027.53)
        ->and(App\Services\SaleInvoicePdfService::totalVes($venta, null))->toBe(5.0)
        ->and(fn () => app(App\Services\SaleInvoicePdfService::class)->build($venta, ['invoice_number' => '1', 'date' => '08/10/2026', 'invoice_base_usd' => 0, 'tasa_bcv' => 800], now()))
        ->toThrow(RuntimeException::class, 'no tiene una cuota')
        ->and(fn () => app(App\Services\SaleInvoicePdfService::class)->build($venta, ['invoice_number' => '1', 'date' => '08/10/2026', 'invoice_base_usd' => 100], now()))
        ->toThrow(RuntimeException::class, 'Indique la tasa BCV');
});
