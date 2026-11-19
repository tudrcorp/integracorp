<?php

declare(strict_types=1);

use App\Filament\Shared\Affiliations\ManageAffiliationPayments;
use App\Models\PaidMembership;
use App\Models\PaidMembershipCorporate;
use App\Models\PaymentEditLog;
use App\Models\User;
use App\Support\Affiliations\PaymentApprovalGuard;
use App\Support\Affiliations\PendingPaymentAnnulment;
use App\Support\Affiliations\PendingPaymentEdition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    bootAffiliationPaymentsSqliteSchema();
    Storage::fake('public');
});

function annulmentActor(): User
{
    return User::factory()->make(['id' => 987950, 'name' => 'Analista Anulación Pest']);
}

function annulmentReason(): string
{
    return 'Comprobante duplicado: el mismo pago ya fue aprobado antes.';
}

/**
 * @param  array<string, mixed>  $overrides
 */
function makeAnnullablePayment(array $overrides = []): PaidMembership
{
    $affiliationId = DB::table('affiliations')->insertGetId(['code' => 'TDEC-IND-000399']);
    Storage::disk('public')->put('voucher-anular.jpeg', 'img');

    $id = DB::table('paid_memberships')->insertGetId(array_merge([
        'affiliation_id' => $affiliationId,
        'status' => 'PENDIENTE',
        'payment_method' => 'TRANSFERENCIA VES',
        'reference_payment_ves' => '922939',
        'document_ves' => 'voucher-anular.jpeg',
        'document_usd' => 'N/A',
        'total_amount' => 122.5,
        'pay_amount_ves' => 122.5,
        'tasa_bcv' => 1,
        'created_by' => 'Vive Plus',
    ], $overrides));

    return PaidMembership::query()->findOrFail($id);
}

it('anula un pendiente: la fila se queda con estatus ANULADO, quién, cuándo y por qué', function (): void {
    $payment = makeAnnullablePayment();

    $result = PendingPaymentAnnulment::annul($payment, '  '.annulmentReason().'  ', annulmentActor(), 'business');

    $payment->refresh();
    $trace = PaymentEditLog::query()->findOrFail($result['trace_id']);

    expect(PaidMembership::query()->whereKey($payment->id)->exists())->toBeTrue()
        ->and($payment->status)->toBe('ANULADO')
        ->and($payment->annulled_by_name)->toBe('Analista Anulación Pest')
        ->and((int) $payment->annulled_by_user_id)->toBe(987950)
        ->and($payment->annulment_reason)->toBe(annulmentReason())
        ->and($payment->annulled_at)->not->toBeNull()
        ->and($payment->document_ves)->toBe('voucher-anular.jpeg')
        ->and(Storage::disk('public')->exists('voucher-anular.jpeg'))->toBeTrue()
        ->and((float) $payment->pay_amount_ves)->toBe(122.5)
        ->and($trace->changes)->toBe(['status' => ['old' => 'PENDIENTE', 'new' => 'ANULADO']])
        ->and($trace->snapshot['status'])->toBe('PENDIENTE')
        ->and($trace->note)->toBe(annulmentReason())
        ->and($trace->panel)->toBe('business')
        ->and(DB::table('logs')->where('action', 'AUDIT_AFFILIATION_PENDING_PAYMENT_ANNULLED')->count())->toBe(1);
});

it('no anula dos veces ni anula pagos aprobados', function (): void {
    $payment = makeAnnullablePayment();
    PendingPaymentAnnulment::annul($payment, annulmentReason(), annulmentActor());

    expect(fn () => PendingPaymentAnnulment::annul($payment, annulmentReason(), annulmentActor()))
        ->toThrow(InvalidArgumentException::class, 'ya está ANULADO');

    $approved = makeAnnullablePayment(['status' => 'APROBADO']);

    expect(fn () => PendingPaymentAnnulment::annul($approved, annulmentReason(), annulmentActor()))
        ->toThrow(InvalidArgumentException::class, 'solo se anulan pagos PENDIENTES')
        ->and($approved->refresh()->status)->toBe('APROBADO')
        ->and(PaymentEditLog::query()->count())->toBe(1);
});

it('no anula un pendiente que ya dejó venta, cuotas o conciliación', function (): void {
    $payment = makeAnnullablePayment(['invoice_number' => 'REC-9']);
    DB::table('sales')->insert(['affiliation_code' => 'TDEC-IND-000399', 'invoice_number' => 'REC-9']);

    expect(fn () => PendingPaymentAnnulment::annul($payment, annulmentReason(), annulmentActor()))
        ->toThrow(InvalidArgumentException::class, 'Tiene la venta REC-9');

    $other = makeAnnullablePayment();
    DB::table('credit_reconciliations')->insert(['paid_membership_id' => $other->id]);

    expect(fn () => PendingPaymentAnnulment::annul($other, annulmentReason(), annulmentActor()))
        ->toThrow(InvalidArgumentException::class, 'conciliación')
        ->and($payment->refresh()->status)->toBe('PENDIENTE')
        ->and($other->refresh()->status)->toBe('PENDIENTE');
});

it('exige un motivo de al menos 15 caracteres y no cambia nada si falta', function (string $reason): void {
    $payment = makeAnnullablePayment();

    expect(fn () => PendingPaymentAnnulment::annul($payment, $reason, annulmentActor()))
        ->toThrow(InvalidArgumentException::class, 'al menos 15 caracteres')
        ->and($payment->refresh()->status)->toBe('PENDIENTE')
        ->and(PaymentEditLog::query()->count())->toBe(0);
})->with([
    'vacío' => '',
    'corto' => 'duplicado',
    'solo espacios' => '                    ',
]);

it('anula también pagos corporativos', function (): void {
    $ownerId = DB::table('affiliation_corporates')->insertGetId(['code' => 'TDEC-COR-000012']);
    $id = DB::table('paid_membership_corporates')->insertGetId([
        'affiliation_corporate_id' => $ownerId,
        'status' => 'PENDIENTE',
        'payment_method' => 'ZELLE',
        'total_amount' => 300,
        'pay_amount_usd' => 300,
    ]);
    $payment = PaidMembershipCorporate::query()->findOrFail($id);

    PendingPaymentAnnulment::annul($payment, annulmentReason(), annulmentActor());

    expect($payment->refresh()->status)->toBe('ANULADO')
        ->and(PaymentEditLog::query()->latest('id')->first()?->payment_type)->toBe('CORPORATIVO')
        ->and($payment->editLogs()->count())->toBe(1);
});

it('un pago anulado no se puede aprobar ni editar', function (): void {
    $payment = makeAnnullablePayment();
    PendingPaymentAnnulment::annul($payment, annulmentReason(), annulmentActor());
    $payment->refresh();

    expect(PaymentApprovalGuard::isApprovableStatus($payment->status))->toBeFalse()
        ->and(fn () => DB::transaction(fn () => PaymentApprovalGuard::assertApprovable($payment)))
        ->toThrow(RuntimeException::class, 'ANULADO: no se puede aprobar')
        ->and(PendingPaymentEdition::blockers($payment)[0])->toContain('solo se editan pagos PENDIENTES');
});

it('la guarda de aprobación deja pasar pendientes y corta aprobados o inexistentes', function (): void {
    $pending = makeAnnullablePayment();
    $approved = makeAnnullablePayment(['status' => 'APROBADO']);

    DB::transaction(fn () => PaymentApprovalGuard::assertApprovable($pending));

    expect(PaymentApprovalGuard::isApprovableStatus(' pendiente '))->toBeTrue()
        ->and(PaymentApprovalGuard::isApprovableStatus('PAGADO'))->toBeFalse()
        ->and(PaymentApprovalGuard::isApprovableStatus(null))->toBeFalse()
        ->and(fn () => PaymentApprovalGuard::assertApprovable($approved))->toThrow(RuntimeException::class, 'ya fue aprobado');

    $gone = $pending->replicate();
    $gone->id = 999999;

    expect(fn () => PaymentApprovalGuard::assertApprovable($gone))->toThrow(RuntimeException::class, 'ya no existe');
});

it('el resumen del historial cuenta los anulados aparte y no como pendientes', function (): void {
    $payment = makeAnnullablePayment();
    DB::table('paid_memberships')->insert(['affiliation_id' => $payment->affiliation_id, 'status' => 'PENDIENTE', 'total_amount' => 10]);
    PendingPaymentAnnulment::annul($payment, annulmentReason(), annulmentActor());

    $summary = ManageAffiliationPayments::paymentsSummary($payment->affiliation()->firstOrFail());

    expect($summary['count'])->toBe(2)
        ->and($summary['pending'])->toBe(1)
        ->and($summary['annulled'])->toBe(1)
        ->and($summary['approved'])->toBe(0)
        ->and(ManageAffiliationPayments::statusColor('ANULADO'))->toBe('danger');
});

it('ningún botón Aprobar se ofrece fuera de PENDIENTE y ambas aprobaciones pasan por la guarda', function (): void {
    $root = dirname(__DIR__, 2);

    foreach ([
        'app/Http/Controllers/PaidMembershipController.php',
        'app/Http/Controllers/PaidMembershipCorporateController.php',
    ] as $controller) {
        expect((string) file_get_contents($root.'/'.$controller))->toContain('PaymentApprovalGuard::assertApprovable($record);');
    }

    foreach ([
        'app/Filament/Administration/Resources/Affiliations/RelationManagers/PaidMembershipsRelationManager.php',
        'app/Filament/Administration/Resources/AffiliationCorporates/RelationManagers/PaidMembershipCorporatesRelationManager.php',
        'app/Filament/Business/Resources/Affiliations/RelationManagers/PaidMembershipsRelationManager.php',
        'app/Filament/Business/Resources/AffiliationCorporates/RelationManagers/PaidMembershipCorporatesRelationManager.php',
        'app/Filament/Resources/Affiliations/RelationManagers/PaidMembershipsRelationManager.php',
        'app/Filament/Resources/AffiliationCorporates/RelationManagers/PaidMembershipCorporatesRelationManager.php',
    ] as $relationManager) {
        expect((string) file_get_contents($root.'/'.$relationManager))
            ->toContain('PaymentApprovalGuard::isApprovableStatus($record->status)')
            ->not->toContain("return \$record->status == 'APROBADO';");
    }

    expect((string) file_get_contents($root.'/app/Livewire/Administration/AffiliationWorkspaceModal.php'))
        ->toContain('PaymentApprovalGuard::isApprovableStatus($membership->status)')
        ->and((string) file_get_contents($root.'/resources/views/livewire/administration/affiliation-workspace-modal.blade.php'))
        ->toContain('PaymentApprovalGuard::isApprovableStatus($payment->status)')
        ->and((string) file_get_contents($root.'/app/Filament/Resources/Affiliations/Tables/AffiliationsTable.php'))
        ->toContain("where('status', '!=', \\App\\Support\\Affiliations\\PendingPaymentAnnulment::ANNULLED_STATUS)->count()");
});

it('el historial ofrece anular (no eliminar) y revalida el permiso en el servidor', function (): void {
    $page = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/Affiliations/ManageAffiliationPayments.php');

    expect($page)
        ->toContain('self::annulPendingPaymentAction(),')
        ->toContain('PendingPaymentAnnulment::annul(')
        ->not->toContain('PendingPaymentDeletion::delete(')
        ->not->toContain('deletePendingPaymentAction')
        ->toContain('abort_unless(');
});
