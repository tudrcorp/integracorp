<?php

declare(strict_types=1);

use App\Models\Affiliation;
use App\Models\AffiliationDateCorrection;
use App\Models\AffiliationRenovationHistory;
use App\Models\Collection;
use App\Models\CollectionAdjustment;
use App\Models\PaidMembership;
use App\Models\Renovation;
use App\Models\User;
use App\Support\Affiliations\DateCorrection\AffiliationDateCorrectionRollback;
use App\Support\Affiliations\DateCorrection\AffiliationDateCorrector;
use App\Support\Affiliations\DateCorrection\DateCorrectionSubject;
use App\Support\Audit\TableRowSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

/**
 * Corrección de vigencia de afiliaciones individuales: misma regla que las corporativas sobre
 * `affiliations`, `renovations`, `paid_memberships` y su historial. Escribe dentro de una
 * transacción que siempre se revierte, sobre una copia de prueba (TEST-AIDC-…).
 */
beforeEach(function (): void {
    DB::beginTransaction();

    $template = Affiliation::query()->first();
    $collectionTemplate = Collection::query()->first();
    $renovationTemplate = Renovation::query()->first();
    $paymentTemplate = PaidMembership::query()->first();

    if (! $template || ! $collectionTemplate || ! $renovationTemplate || ! $paymentTemplate) {
        $this->markTestSkipped('Faltan filas reales para clonar.');
    }

    $this->affiliation = $template->replicate();
    $this->affiliation->code = 'TEST-AIDC-'.Str::upper(Str::random(8));
    $this->affiliation->status = 'ACTIVA';
    $this->affiliation->payment_frequency = 'SEMESTRAL';
    $this->affiliation->activated_at = '05/03/2026';
    $this->affiliation->effective_date = '05/03/2027';
    $this->affiliation->save();

    $this->collection = $collectionTemplate->replicate();
    $this->collection->affiliation_code = $this->affiliation->code;
    $this->collection->include_date = '05/03/2026';
    $this->collection->next_payment_date = '05/09/2026';
    $this->collection->status = 'POR PAGAR';
    $this->collection->total_amount = 250;
    $this->collection->payment_frequency = 'SEMESTRAL';
    $this->collection->collection_invoice_number = 'T-'.Str::upper(Str::random(6));
    $this->collection->created_at = now()->subDays(10);
    $this->collection->save();

    $this->renovation = $renovationTemplate->replicate();
    $this->renovation->affiliation_id = $this->affiliation->id;
    $this->renovation->date_renewal = '2028-03-05';
    $this->renovation->status = 'VIGENTE';
    $this->renovation->save();

    $this->payment = $paymentTemplate->replicate();
    $this->payment->affiliation_id = $this->affiliation->id;
    $this->payment->status = 'APROBADO';
    $this->payment->prox_payment_date = '05/09/2026';
    $this->payment->renewal_date = '05/03/2027';
    $this->payment->created_at = '2026-03-05 10:00:00';
    $this->payment->save();

    $this->actor = User::factory()->make(['name' => 'Analista de prueba', 'departament' => ['SUPERADMIN']]);
});

afterEach(function (): void {
    DB::rollBack();
});

it('corrige una individual en su primer año y normaliza la vigencia guardada como fecha de fin', function (): void {
    $plan = AffiliationDateCorrector::plan($this->affiliation, '12/03/2026');

    expect($plan['type'])->toBe(DateCorrectionSubject::TYPE_INDIVIDUAL)
        ->and($plan['blockers'])->toBe([])
        ->and($plan['collections'][0])->toMatchArray(['installment' => '2 de 2', 'old' => '05/09/2026', 'new' => '12/09/2026'])
        ->and($plan['after']['effective_date'])->toBe('12/03/2026')
        ->and($plan['renovations'][0])->toMatchArray(['action' => 'move', 'old' => '05/03/2028', 'new' => '12/03/2027'])
        ->and(implode(' ', $plan['warnings']))->toContain('fecha de FIN del año');

    AffiliationDateCorrector::apply($this->affiliation, '12/03/2026', 'Emisión real según el primer pago aprobado', $this->actor);

    expect($this->affiliation->fresh()->activated_at)->toBe('12/03/2026')
        ->and($this->affiliation->fresh()->effective_date)->toBe('12/03/2026')
        ->and($this->collection->fresh()->next_payment_date)->toBe('12/09/2026')
        ->and((float) $this->collection->fresh()->total_amount)->toBe(250.0)
        ->and((string) $this->renovation->fresh()->getRawOriginal('date_renewal'))->toStartWith('2027-03-12')
        ->and($this->payment->fresh()->prox_payment_date)->toBe('12/09/2026')
        ->and($this->payment->fresh()->renewal_date)->toBe('12/03/2027')
        ->and(CollectionAdjustment::query()->where('affiliation_code', $this->affiliation->code)->where('mode', CollectionAdjustment::MODE_EFFECTIVE_DATE)->count())->toBe(1);
});

it('en una individual renovada no toca la emisión original', function (): void {
    $history = AffiliationRenovationHistory::query()->first();

    if (! $history instanceof AffiliationRenovationHistory) {
        $this->markTestSkipped('No hay historial de renovaciones individuales para clonar.');
    }

    $copy = $history->replicate();
    $copy->affiliation_id = $this->affiliation->id;
    $copy->save();

    $plan = AffiliationDateCorrector::plan($this->affiliation, '12/03/2026');

    expect($plan['scope'])->toBe(AffiliationDateCorrector::SCOPE_CURRENT_CYCLE)
        ->and($plan['after']['activated_at'])->toBe('05/03/2026')
        ->and(implode(' ', $plan['warnings']))->toContain('Póliza renovada');
});

it('revierte una corrección individual exactamente a la foto previa', function (): void {
    $correction = AffiliationDateCorrector::apply($this->affiliation, '12/03/2026', 'Emisión real según el primer pago aprobado', $this->actor);
    $columns = DateCorrectionSubject::individual()->columns();
    $before = $correction->snapshot_before;

    AffiliationDateCorrectionRollback::execute($correction, $this->actor, 'Se aplicó sobre la póliza equivocada');

    expect(TableRowSnapshot::differences($columns, $before, TableRowSnapshot::capture($columns, TableRowSnapshot::idsOf($columns, $before))))->toBe([])
        ->and($this->affiliation->fresh()->effective_date)->toBe('05/03/2027')
        ->and($correction->fresh()->status)->toBe(AffiliationDateCorrection::STATUS_ROLLED_BACK);
});

it('no revierte si entró un pago después de corregir', function (): void {
    $correction = AffiliationDateCorrector::apply($this->affiliation, '12/03/2026', 'Emisión real según el primer pago aprobado', $this->actor);

    $newPayment = $this->payment->replicate();
    $newPayment->created_at = now()->addMinute();
    $newPayment->save();

    expect(implode(' ', AffiliationDateCorrectionRollback::blockers($correction)))->toContain('pago(s) después de corregir');
});

it('bloquea con un comprobante individual por aprobar y exige el permiso de individuales', function (): void {
    $proof = $this->payment->replicate();
    $proof->status = 'PENDIENTE';
    $proof->save();

    expect(implode(' ', AffiliationDateCorrector::plan($this->affiliation, '12/03/2026')['blockers']))->toContain('comprobante(s) de pago por aprobar');

    $withoutPermission = User::factory()->make(['departament' => ['ADMINISTRACION', 'NEGOCIOS']]);

    expect(fn () => AffiliationDateCorrector::apply($this->affiliation, '12/03/2026', 'Motivo suficientemente largo', $withoutPermission))
        ->toThrow(RuntimeException::class, 'afiliación individual');
});
