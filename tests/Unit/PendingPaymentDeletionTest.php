<?php

declare(strict_types=1);

use App\Models\Commission;
use App\Models\CreditReconciliation;
use App\Models\DeletedPaymentVoucher;
use App\Models\PaidMembership;
use App\Models\PaidMembershipCorporate;
use App\Models\Sale;
use App\Models\User;
use App\Support\Affiliations\PendingPaymentDeletion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

/**
 * Escriben en la base y en disco: transacción revertida y discos simulados.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Storage::fake('public');
    Storage::fake('local');
});

afterEach(function (): void {
    DB::rollBack();
});

function pendingPaymentActor(): User
{
    return User::factory()->make(['id' => 987800, 'name' => 'Analista Pagos Pest']);
}

function pendingPaymentNote(): string
{
    return 'Comprobante duplicado: el mismo pago ya fue aprobado antes.';
}

/**
 * Pago individual PENDIENTE nuevo, clonado de uno real para no adivinar columnas.
 */
function makePendingIndividualPayment(?string $documentVes = null): PaidMembership
{
    $base = PaidMembership::query()->whereNotNull('affiliation_id')->firstOrFail();
    $payment = $base->replicate();
    $documentVes ??= 'pest-'.uniqid().'.png';

    $payment->forceFill([
        'status' => 'PENDIENTE',
        'invoice_number' => null,
        'aproved_by' => null,
        'document_ves' => $documentVes,
        'document_usd' => 'N/A',
        'created_by' => 'pest',
    ])->save();

    Storage::disk('public')->put($documentVes, 'contenido-del-comprobante');

    return $payment->fresh();
}

function makePendingCorporatePayment(): PaidMembershipCorporate
{
    $base = PaidMembershipCorporate::query()->whereNotNull('affiliation_corporate_id')->firstOrFail();
    $payment = $base->replicate();
    $document = 'pest-corp-'.uniqid().'.pdf';

    $payment->forceFill([
        'status' => 'PENDIENTE',
        'invoice_number' => null,
        'aproved_by' => null,
        'document_ves' => 'N/A',
        'document_usd' => $document,
    ])->save();

    Storage::disk('public')->put($document, 'pdf');

    return $payment->fresh();
}

it('un pago pendiente sin rastro se puede eliminar; uno aprobado no', function (): void {
    $payment = makePendingIndividualPayment();

    expect(PendingPaymentDeletion::blockers($payment))->toBe([]);

    $payment->forceFill(['status' => 'APROBADO'])->save();

    expect(PendingPaymentDeletion::blockers($payment->fresh()))->toHaveCount(1)
        ->and(PendingPaymentDeletion::blockers($payment->fresh())[0])->toContain('solo se eliminan pagos PENDIENTES');
});

it('bloquea si el recibo tiene venta, cobranza, comisión o conciliación en esa afiliación', function (string $trace): void {
    $payment = makePendingIndividualPayment();
    $code = (string) $payment->affiliation->code;
    $invoice = 'PEST-'.uniqid();
    $payment->forceFill(['invoice_number' => $invoice])->save();

    // Recibo sin nada detrás: no bloquea.
    expect(PendingPaymentDeletion::blockers($payment->fresh()))->toBe([]);

    match ($trace) {
        'venta' => tap(Sale::query()->firstOrFail()->replicate(), fn (Sale $sale) => $sale->forceFill(['invoice_number' => $invoice, 'affiliation_code' => $code])->save()),
        'cobranza' => tap(App\Models\Collection::query()->firstOrFail()->replicate(), fn (App\Models\Collection $collection) => $collection->forceFill(['collection_invoice_number' => $invoice, 'affiliation_code' => $code, 'sale_id' => null])->save()),
        'comision' => tap(Commission::query()->firstOrFail()->replicate(), fn (Commission $commission) => $commission->forceFill(['code' => $invoice, 'affiliation_code' => $code])->save()),
        'conciliacion' => tap(CreditReconciliation::query()->firstOrFail()->replicate(), fn (CreditReconciliation $row) => $row->forceFill(['paid_membership_id' => $payment->id])->save()),
    };

    $blockers = PendingPaymentDeletion::blockers($payment->fresh());

    expect($blockers)->not->toBe([])
        ->and(fn () => PendingPaymentDeletion::delete($payment->fresh(), pendingPaymentNote(), pendingPaymentActor()))->toThrow(InvalidArgumentException::class);

    expect(PaidMembership::query()->whereKey($payment->id)->exists())->toBeTrue()
        ->and(Storage::disk('public')->exists($payment->document_ves))->toBeTrue()
        ->and(Storage::disk('local')->allFiles())->toBe([])
        ->and(DeletedPaymentVoucher::query()->where('payment_id', $payment->id)->exists())->toBeFalse();
})->with(['venta', 'cobranza', 'comision', 'conciliacion']);

it('un recibo igual en otra afiliación no bloquea', function (): void {
    $payment = makePendingIndividualPayment();
    $invoice = 'PEST-'.uniqid();
    $payment->forceFill(['invoice_number' => $invoice])->save();

    tap(Sale::query()->firstOrFail()->replicate(), fn (Sale $sale) => $sale->forceFill(['invoice_number' => $invoice, 'affiliation_code' => 'OTRA-AFILIACION-PEST'])->save());

    expect(PendingPaymentDeletion::blockers($payment->fresh()))->toBe([]);
});

it('elimina el pago, resguarda el comprobante en disco privado y deja la foto completa en la traza', function (): void {
    $payment = makePendingIndividualPayment();
    $snapshot = $payment->getAttributes();
    $path = (string) $payment->document_ves;

    $result = PendingPaymentDeletion::delete($payment, pendingPaymentNote(), pendingPaymentActor(), 'business');

    $trace = DeletedPaymentVoucher::query()->findOrFail($result['trace_id']);
    $archived = 'comprobantes-eliminados/individual/'.$payment->id.'/'.basename($path);

    expect(PaidMembership::query()->whereKey($payment->id)->exists())->toBeFalse()
        ->and($result['archived'])->toBe([$path])
        ->and(Storage::disk('public')->exists($path))->toBeFalse()
        ->and(Storage::disk('local')->get($archived))->toBe('contenido-del-comprobante')
        ->and($trace->payment_type)->toBe('INDIVIDUAL')
        ->and((int) $trace->payment_id)->toBe((int) $payment->id)
        ->and($trace->affiliation_code)->toBe($payment->affiliation->code)
        ->and($trace->note)->toBe(pendingPaymentNote())
        ->and($trace->panel)->toBe('business')
        ->and($trace->actor_name)->toBe('Analista Pagos Pest')
        ->and((int) $trace->snapshot['id'])->toBe((int) $snapshot['id'])
        ->and($trace->snapshot['document_ves'])->toBe($path)
        ->and($trace->snapshot['status'])->toBe('PENDIENTE')
        ->and($trace->files[0]['archived_path'])->toBe($archived)
        ->and(DB::table('logs')->where('action', 'AUDIT_AFFILIATION_PENDING_PAYMENT_DELETED')->where('response', 'like', '%"trace_id":'.$trace->id.'%')->exists())->toBeTrue();
});

it('no mueve un comprobante que otro pago también usa', function (): void {
    $shared = 'pest-compartido-'.uniqid().'.png';
    $twin = makePendingIndividualPayment($shared);
    $twin->forceFill(['status' => 'APROBADO'])->save();
    $payment = makePendingIndividualPayment($shared);

    $result = PendingPaymentDeletion::delete($payment, pendingPaymentNote(), pendingPaymentActor());

    expect($result['kept'])->toBe([$shared])
        ->and($result['archived'])->toBe([])
        ->and(Storage::disk('public')->exists($shared))->toBeTrue()
        ->and(PaidMembership::query()->whereKey($twin->id)->exists())->toBeTrue()
        ->and(DeletedPaymentVoucher::query()->findOrFail($result['trace_id'])->files[0]['kept_reason'])->toBe('Otro pago usa el mismo archivo');
});

it('exige la nota y no toca nada si falta', function (): void {
    $payment = makePendingIndividualPayment();

    expect(fn () => PendingPaymentDeletion::delete($payment, '  corta ', pendingPaymentActor()))
        ->toThrow(InvalidArgumentException::class, 'al menos 15');

    expect(PaidMembership::query()->whereKey($payment->id)->exists())->toBeTrue()
        ->and(Storage::disk('public')->exists($payment->document_ves))->toBeTrue();
});

it('elimina también pagos corporativos pendientes', function (): void {
    $payment = makePendingCorporatePayment();
    $path = (string) $payment->document_usd;

    $result = PendingPaymentDeletion::delete($payment, pendingPaymentNote(), pendingPaymentActor(), 'administration');

    expect(PaidMembershipCorporate::query()->whereKey($payment->id)->exists())->toBeFalse()
        ->and(Storage::disk('local')->exists('comprobantes-eliminados/corporativo/'.$payment->id.'/'.basename($path)))->toBeTrue()
        ->and(DeletedPaymentVoucher::query()->findOrFail($result['trace_id'])->payment_type)->toBe('CORPORATIVO');
});

it('lista los pendientes bloqueados para revisarlos con Administración', function (): void {
    $rows = PendingPaymentDeletion::blockedPendingPayments();

    expect($rows->every(fn (array $row): bool => $row['motivos'] !== ''))->toBeTrue()
        ->and($rows->pluck('pago_id')->unique()->count())->toBe($rows->count());
});

function pendingPaymentUser(array $departments): User
{
    return User::factory()->make([
        'id' => 987801,
        'name' => 'Usuario Pagos Pest',
        'email' => 'pagos-pest-'.uniqid().'@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => $departments,
    ]);
}

it('desde el historial de pagos se elimina un pendiente con nota, y un aprobado no ofrece la acción', function (): void {
    Filament\Facades\Filament::setCurrentPanel('business');

    $payment = makePendingIndividualPayment();
    $approved = PaidMembership::query()->where('affiliation_id', $payment->affiliation_id)->where('status', 'APROBADO')->first();
    $action = Filament\Actions\Testing\TestAction::make('deletePendingPayment')->table($payment);

    $component = Livewire\Livewire::actingAs(pendingPaymentUser(['SUPERADMIN', 'NEGOCIOS']))
        ->test(App\Filament\Business\Resources\Affiliations\Pages\AffiliationPayments::class, ['record' => $payment->affiliation_id])
        ->assertOk()
        ->assertActionVisible($action);

    if ($approved !== null) {
        $component->assertActionHidden(Filament\Actions\Testing\TestAction::make('deletePendingPayment')->table($approved));
    }

    $component
        ->mountAction($action)
        ->set('mountedActions.0.data.note', 'corta')
        ->callMountedAction()
        ->assertHasActionErrors(['note']);

    expect(PaidMembership::query()->whereKey($payment->id)->exists())->toBeTrue();

    $component
        ->set('mountedActions.0.data.note', pendingPaymentNote())
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('Pago pendiente eliminado');

    expect(PaidMembership::query()->whereKey($payment->id)->exists())->toBeFalse();
});

it('un analista sin el permiso no ve la acción', function (): void {
    Filament\Facades\Filament::setCurrentPanel('business');

    $payment = makePendingIndividualPayment();

    Illuminate\Support\Facades\Auth::guard()->setUser(pendingPaymentUser(['NEGOCIOS']));

    expect(App\Support\Filament\BusinessFilamentActionAccess::userCan(App\Support\Filament\BusinessFilamentActionPermissionRegistry::DELETE_PENDING_PAYMENT))->toBeFalse();

    Illuminate\Support\Facades\Auth::guard()->setUser(pendingPaymentUser(['SUPERADMIN']));

    expect(App\Support\Filament\BusinessFilamentActionAccess::userCan(App\Support\Filament\BusinessFilamentActionPermissionRegistry::DELETE_PENDING_PAYMENT))->toBeTrue()
        ->and(PendingPaymentDeletion::blockers($payment))->toBe([]);
});
