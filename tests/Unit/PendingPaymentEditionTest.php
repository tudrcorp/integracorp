<?php

declare(strict_types=1);

use App\Models\PaidMembership;
use App\Models\PaidMembershipCorporate;
use App\Models\PaymentEditLog;
use App\Models\Permission;
use App\Models\User;
use App\Support\Affiliations\PendingPaymentEdition;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

/**
 * Siempre en sqlite `:memory:` con un esquema mínimo propio: nunca toca la base
 * de desarrollo, esté o no cacheada la config.
 */
beforeEach(function (): void {
    bootAffiliationPaymentsSqliteSchema();
    Storage::fake('public');
});

function paymentEditionActor(): User
{
    return User::factory()->make(['id' => 987900, 'name' => 'Analista Administración Pest']);
}

function paymentEditionNote(): string
{
    return 'La referencia cargada no coincide con el comprobante del banco.';
}

/**
 * @param  array<string, mixed>  $overrides
 */
function makeEditablePayment(array $overrides = []): PaidMembership
{
    $affiliationId = DB::table('affiliations')->insertGetId(['code' => 'TDEC-IND-000777']);
    Storage::disk('public')->put('voucher-ves.jpeg', 'img');

    $id = DB::table('paid_memberships')->insertGetId(array_merge([
        'affiliation_id' => $affiliationId,
        'status' => 'PENDIENTE',
        'payment_method' => 'PAGO MOVIL VES',
        'payment_method_usd' => 'N/A',
        'payment_method_ves' => 'N/A',
        'name_ti_usd' => 'N/A',
        'bank_usd' => 'N/A',
        'bank_ves' => 'BANCAMIGA(VES)',
        'reference_payment_usd' => 'N/A',
        'reference_payment_ves' => '123456',
        'document_usd' => 'N/A',
        'document_ves' => 'voucher-ves.jpeg',
        'date_payment_voucher' => '10/11/2026',
        'observations_payment' => 'N/A',
        'total_amount' => 100,
        'pay_amount_usd' => 0,
        'pay_amount_ves' => 25000,
        'tasa_bcv' => 250,
        'created_by' => 'Agente Pest',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides));

    return PaidMembership::query()->findOrFail($id);
}

/**
 * @param  array<string, mixed>  $changes
 * @return array<string, mixed>
 */
function editData(PaidMembership|PaidMembershipCorporate $payment, array $changes): array
{
    return array_merge(PendingPaymentEdition::formState($payment), $changes);
}

it('edita un pago pendiente en bolívares, recalcula la tasa y deja la traza campo por campo', function (): void {
    $payment = makeEditablePayment();
    $fingerprint = PendingPaymentEdition::fingerprint($payment);

    $result = PendingPaymentEdition::update(
        $payment,
        editData($payment, ['reference_payment_ves' => '654321', 'pay_amount_ves' => '26000']),
        paymentEditionNote(),
        paymentEditionActor(),
        'administration',
        $fingerprint,
    );

    $payment->refresh();
    $trace = PaymentEditLog::query()->findOrFail($result['trace_id']);

    expect($result['changed'])->toEqualCanonicalizing(['reference_payment_ves', 'pay_amount_ves', 'tasa_bcv'])
        ->and($payment->reference_payment_ves)->toBe('654321')
        ->and((float) $payment->pay_amount_ves)->toBe(26000.0)
        ->and((float) $payment->tasa_bcv)->toBe(260.0)
        ->and((float) $payment->total_amount)->toBe(100.0)
        ->and($payment->status)->toBe('PENDIENTE')
        ->and($trace->payment_type)->toBe('INDIVIDUAL')
        ->and($trace->affiliation_code)->toBe('TDEC-IND-000777')
        ->and($trace->note)->toBe(paymentEditionNote())
        ->and($trace->actor_name)->toBe('Analista Administración Pest')
        ->and($trace->changes['reference_payment_ves'])->toBe(['old' => '123456', 'new' => '654321'])
        ->and($trace->snapshot['reference_payment_ves'])->toBe('123456')
        ->and(DB::table('logs')->where('action', 'AUDIT_AFFILIATION_PENDING_PAYMENT_EDITED')->count())->toBe(1)
        ->and($payment->editLogs()->count())->toBe(1);
});

it('nunca cambia el total aunque llegue en los datos', function (): void {
    $payment = makeEditablePayment();

    PendingPaymentEdition::update($payment, editData($payment, ['total_amount' => 1, 'reference_payment_ves' => '111111']), paymentEditionNote(), paymentEditionActor());

    expect((float) $payment->refresh()->total_amount)->toBe(100.0);
});

it('no edita un pago aprobado', function (): void {
    $payment = makeEditablePayment(['status' => 'APROBADO']);

    expect(PendingPaymentEdition::blockers($payment))->not->toBe([])
        ->and(fn () => PendingPaymentEdition::update($payment, editData($payment, ['reference_payment_ves' => '999999']), paymentEditionNote(), paymentEditionActor()))
        ->toThrow(InvalidArgumentException::class, 'solo se editan pagos PENDIENTES');

    expect($payment->refresh()->reference_payment_ves)->toBe('123456')
        ->and(PaymentEditLog::query()->count())->toBe(0);
});

it('no edita un pendiente que ya tiene venta de aprobación', function (): void {
    $payment = makeEditablePayment(['invoice_number' => 'REC-1']);
    DB::table('sales')->insert(['affiliation_code' => 'TDEC-IND-000777', 'invoice_number' => 'REC-1']);

    expect(fn () => PendingPaymentEdition::update($payment, editData($payment, ['reference_payment_ves' => '999999']), paymentEditionNote(), paymentEditionActor()))
        ->toThrow(InvalidArgumentException::class, 'Tiene la venta REC-1');

    expect($payment->refresh()->reference_payment_ves)->toBe('123456');
});

it('no pisa el cambio de otro usuario hecho mientras el formulario estaba abierto', function (): void {
    $payment = makeEditablePayment();
    $fingerprint = PendingPaymentEdition::fingerprint($payment);

    DB::table('paid_memberships')->where('id', $payment->id)->update(['reference_payment_ves' => '222222']);

    expect(fn () => PendingPaymentEdition::update($payment, editData($payment, ['reference_payment_ves' => '333333']), paymentEditionNote(), paymentEditionActor(), null, $fingerprint))
        ->toThrow(InvalidArgumentException::class, 'Otro usuario modificó este pago');

    expect($payment->refresh()->reference_payment_ves)->toBe('222222');
});

it('exige motivo y rechaza guardar sin cambios', function (): void {
    $payment = makeEditablePayment();

    expect(fn () => PendingPaymentEdition::update($payment, editData($payment, ['reference_payment_ves' => '444444']), 'corto', paymentEditionActor()))
        ->toThrow(InvalidArgumentException::class, 'al menos 15 caracteres')
        ->and(fn () => PendingPaymentEdition::update($payment, editData($payment, []), paymentEditionNote(), paymentEditionActor()))
        ->toThrow(InvalidArgumentException::class, 'No hay cambios');

    expect(PaymentEditLog::query()->count())->toBe(0);
});

it('detecta un cambio de ceros a la izquierda en la referencia', function (): void {
    $payment = makeEditablePayment(['reference_payment_ves' => '1234']);

    $result = PendingPaymentEdition::update($payment, editData($payment, ['reference_payment_ves' => '001234']), paymentEditionNote(), paymentEditionActor());

    expect($result['changed'])->toBe(['reference_payment_ves']);
});

it('al pasar a Zelle exige comprobante en dólares y deja el pago con las convenciones de la carga', function (): void {
    $payment = makeEditablePayment();
    $zelle = ['payment_method' => 'ZELLE', 'name_ti_usd' => 'Ana Pérez', 'reference_payment_usd' => 'ZL-998877'];

    expect(fn () => PendingPaymentEdition::update($payment, editData($payment, $zelle), paymentEditionNote(), paymentEditionActor()))
        ->toThrow(InvalidArgumentException::class, 'Adjunte el comprobante en dólares');

    Storage::disk('public')->put('nuevo-zelle.png', 'img');
    PendingPaymentEdition::update($payment, editData($payment, [...$zelle, 'document_usd' => 'nuevo-zelle.png']), paymentEditionNote(), paymentEditionActor());

    $payment->refresh();
    $trace = PaymentEditLog::query()->latest('id')->firstOrFail();

    expect($payment->payment_method)->toBe('ZELLE')
        ->and((float) $payment->pay_amount_usd)->toBe(100.0)
        ->and((float) $payment->pay_amount_ves)->toBe(0.0)
        ->and($payment->tasa_bcv)->toBeNull()
        ->and($payment->document_usd)->toBe('nuevo-zelle.png')
        ->and($payment->document_ves)->toBe('N/A')
        ->and($payment->bank_ves)->toBe('N/A')
        ->and($payment->reference_payment_ves)->toBe('N/A')
        ->and($trace->changes['document_ves']['old'])->toBe('voucher-ves.jpeg')
        ->and(Storage::disk('public')->exists('voucher-ves.jpeg'))->toBeTrue();
});

it('valida el pago múltiple: el tramo en dólares debe ser menor que el total', function (): void {
    $payment = makeEditablePayment();
    $multiple = [
        'payment_method' => 'MULTIPLE',
        'payment_method_usd' => 'EFECTIVO US$',
        'payment_method_ves' => 'PAGO MOVIL VES',
        'bank_ves' => 'BANCAMIGA - VES',
        'reference_payment_ves' => '555555',
        'pay_amount_ves' => '10000',
    ];

    expect(fn () => PendingPaymentEdition::update($payment, editData($payment, [...$multiple, 'pay_amount_usd' => '100']), paymentEditionNote(), paymentEditionActor()))
        ->toThrow(InvalidArgumentException::class, 'debe ser menor que el total');

    PendingPaymentEdition::update($payment, editData($payment, [...$multiple, 'pay_amount_usd' => '60']), paymentEditionNote(), paymentEditionActor());

    $payment->refresh();

    expect((float) $payment->pay_amount_usd)->toBe(60.0)
        ->and((float) $payment->tasa_bcv)->toBe(250.0)
        ->and($payment->payment_method_usd)->toBe('EFECTIVO US$');
});

it('rechaza un comprobante con ruta sospechosa o que no se subió', function (string $path): void {
    $payment = makeEditablePayment();

    expect(fn () => PendingPaymentEdition::update($payment, editData($payment, ['document_ves' => $path]), paymentEditionNote(), paymentEditionActor()))
        ->toThrow(InvalidArgumentException::class, 'no terminó de subirse');
})->with([
    'salida de carpeta' => '../.env',
    'ruta absoluta' => '/etc/passwd',
    'archivo inexistente' => 'no-existe.png',
]);

it('rechaza fechas futuras, métodos y bancos fuera del catálogo', function (): void {
    $payment = makeEditablePayment();

    expect(fn () => PendingPaymentEdition::normalize($payment, editData($payment, ['date_payment_voucher' => now()->addDays(3)->format('d/m/Y')])))
        ->toThrow(InvalidArgumentException::class, 'no puede ser futura')
        ->and(fn () => PendingPaymentEdition::normalize($payment, editData($payment, ['payment_method' => 'BITCOIN'])))
        ->toThrow(InvalidArgumentException::class, 'método de pago válido')
        ->and(fn () => PendingPaymentEdition::normalize($payment, editData($payment, ['bank_ves' => 'BANCO INVENTADO'])))
        ->toThrow(InvalidArgumentException::class, 'no es una opción válida');
});

it('edita también los pagos corporativos', function (): void {
    $ownerId = DB::table('affiliation_corporates')->insertGetId(['code' => 'TDEC-COR-000055']);
    Storage::disk('public')->put('corp.pdf', 'pdf');
    $id = DB::table('paid_membership_corporates')->insertGetId([
        'affiliation_corporate_id' => $ownerId,
        'status' => 'PENDIENTE',
        'payment_method' => 'TRANSFERENCIA US$',
        'name_ti_usd' => 'Empresa Pest',
        'bank_usd' => 'CHASE BANK',
        'document_usd' => 'corp.pdf',
        'document_ves' => 'N/A',
        'date_payment_voucher' => '05/11/2026',
        'total_amount' => 500,
        'pay_amount_usd' => 500,
    ]);
    $payment = PaidMembershipCorporate::query()->findOrFail($id);

    PendingPaymentEdition::update($payment, editData($payment, ['bank_usd' => 'BANK OF AMERICA']), paymentEditionNote(), paymentEditionActor());

    $trace = PaymentEditLog::query()->latest('id')->firstOrFail();

    expect($payment->refresh()->bank_usd)->toBe('BANK OF AMERICA')
        ->and($trace->payment_type)->toBe('CORPORATIVO')
        ->and($trace->affiliation_code)->toBe('TDEC-COR-000055')
        ->and($payment->editLogs()->count())->toBe(1);
});

it('el permiso es asignable solo en Administración y se evalúa contra el usuario', function (): void {
    Filament\Facades\Filament::setCurrentPanel('administration');
    $slug = BusinessFilamentActionPermissionRegistry::EDIT_PENDING_PAYMENT;

    expect(BusinessFilamentActionPermissionRegistry::all()[$slug]['modules'])->toBe(['ADMINISTRACION']);

    $analyst = User::factory()->make(['id' => 987901, 'status' => 'ACTIVO', 'departament' => ['ADMINISTRACION']]);
    $analyst->setRelation('permissions', new Illuminate\Database\Eloquent\Collection);
    Auth::guard()->setUser($analyst);

    expect(BusinessFilamentActionAccess::userCan($slug))->toBeFalse();

    $analyst->setRelation('permissions', new Illuminate\Database\Eloquent\Collection([
        new Permission(['slug' => $slug, 'module' => 'ADMINISTRACION']),
    ]));

    expect(BusinessFilamentActionAccess::userCan($slug))->toBeTrue();
});

it('ambas tablas de Pagos registrados ofrecen editar e historial, y la acción revalida el permiso en el servidor', function (): void {
    $root = dirname(__DIR__, 2);
    $actions = (string) file_get_contents($root.'/app/Filament/Shared/Affiliations/PendingPaymentEditActions.php');

    foreach ([
        'app/Filament/Administration/Resources/Affiliations/RelationManagers/PaidMembershipsRelationManager.php',
        'app/Filament/Administration/Resources/AffiliationCorporates/RelationManagers/PaidMembershipCorporatesRelationManager.php',
    ] as $file) {
        expect((string) file_get_contents($root.'/'.$file))
            ->toContain('PendingPaymentEditActions::edit()')
            ->toContain('PendingPaymentEditActions::history()')
            ->toContain('PendingPaymentEditActions::withEditCount($query)');
    }

    expect($actions)
        ->toContain('abort_unless(')
        ->toContain('BusinessFilamentActionPermissionRegistry::EDIT_PENDING_PAYMENT')
        ->not->toContain("TextInput::make('total_amount')");
});
