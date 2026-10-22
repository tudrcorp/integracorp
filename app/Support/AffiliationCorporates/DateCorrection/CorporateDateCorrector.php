<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates\DateCorrection;

use App\Models\AffiliateClinicalServiceUsage;
use App\Models\AffiliationCorporate;
use App\Models\AffiliationCorporateDateCorrection;
use App\Models\AffiliationCorporateRenovationHistory;
use App\Models\AnnualCollection;
use App\Models\Collection;
use App\Models\CollectionAdjustment;
use App\Models\PaidMembershipCorporate;
use App\Models\RenovationCorporate;
use App\Models\User;
use App\Support\AffiliationCorporates\CorporatePaymentFrequency;
use App\Support\Audit\TableRowSnapshot;
use App\Support\Collections\CollectionDueDate;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\UserNavigationAccess;
use App\Support\SecurityAudit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Corrige la fecha de emisión y vigencia de una afiliación corporativa y arrastra todo lo
 * que se calculó con la fecha equivocada.
 *
 * Qué mueve (y nada más):
 * - `activated_at` y `effective_date` de la afiliación.
 * - Avisos POR PAGAR: cada uno conserva su número de cuota (2 de 4 sigue siendo 2 de 4) y pasa
 *   a la fecha que le toca desde la vigencia nueva. Queda rastro en `collection_adjustments`.
 * - Planificación anual POR PAGAR: meses con cuota y próximo pago.
 * - Propuestas de renovación VIGENTE: fecha de renovación y días restantes.
 * - Pagos aprobados del ciclo: solo las fechas informativas (próximo pago y renovación).
 *
 * Qué nunca toca: avisos PAGADOS o ANULADOS, montos, fecha de pago ni estado de los pagos,
 * ventas ni comisiones. El certificado no se regenera solo: es pesado y sale hacia el
 * cliente (ViVEplus); la vista previa lo avisa.
 *
 * Todo va en una transacción con la afiliación y los avisos bloqueados, con foto antes y
 * después para revertir ({@see CorporateDateCorrectionRollback}).
 */
final class CorporateDateCorrector
{
    public const REASON_MIN_LENGTH = 15;

    /** Distancia máxima entre la fecha actual y la corregida. */
    public const MAX_SHIFT_MONTHS = 12;

    public const PENDING_STATUS = 'POR PAGAR';

    public const PAID_STATUS = 'PAGADO';

    public const PENDING_PROOF_STATUS = 'PENDIENTE';

    public const ACTIVE_STATUS = 'ACTIVA';

    public const LOG_ACTION = 'CORRIGIO FECHA DE VIGENCIA';

    /** @var list<string> */
    public const APPROVED_PAYMENT_STATUSES = ['APROBADO', 'PAGADO'];

    /**
     * Columnas que la corrección puede modificar, por tabla.
     *
     * @var array<string, list<string>>
     */
    public const COLUMNS = [
        'affiliation_corporates' => ['activated_at', 'effective_date'],
        'collections' => ['include_date', 'next_payment_date', 'filter_next_payment_date', 'expiration_date', 'status'],
        'annual_collections' => [
            'next_payment_date', 'filter_next_payment_date', 'expiration_date',
            'month_1', 'month_2', 'month_3', 'month_4', 'month_5', 'month_6',
            'month_7', 'month_8', 'month_9', 'month_10', 'month_11', 'month_12',
        ],
        'renovation_corporates' => ['date_renewal', 'remaining_days', 'status', 'updated_by'],
        'paid_membership_corporates' => ['prox_payment_date', 'renewal_date'],
    ];

    /**
     * Calcula la corrección sin escribir nada.
     *
     * @return array{
     *     blockers: list<string>,
     *     warnings: list<string>,
     *     before: array{activated_at: string|null, effective_date: string|null, renewal: string|null},
     *     after: array{date: string|null, renewal: string|null},
     *     frequency: string,
     *     collections: list<array{id: int, invoice: string, installment: string, old: string, new: string, amount: float, was_overdue: bool, is_overdue: bool}>,
     *     annual: list<array{id: int, next: string, months: list<int>}>,
     *     renovations: list<array{id: int, old: string, new: string, remaining_days: int}>,
     *     payments: list<array{id: int, prox_old: string|null, prox_new: string, renewal_old: string|null, renewal_new: string}>
     * }
     */
    public static function plan(AffiliationCorporate $affiliation, mixed $newDate, ?CarbonImmutable $today = null): array
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $blockers = [];
        $warnings = [];

        $oldAnchor = CollectionDueDate::parse($affiliation->activated_at) ?? CollectionDueDate::parse($affiliation->effective_date);
        $newAnchor = CollectionDueDate::parse($newDate);
        $frequency = CorporatePaymentFrequency::normalize((string) $affiliation->payment_frequency);
        $months = $frequency !== null ? CorporatePaymentFrequency::monthsPerInstallment($frequency) : 12;
        $perYear = $frequency !== null ? CorporatePaymentFrequency::installmentsPerYear($frequency) : 1;

        $result = [
            'blockers' => [],
            'warnings' => [],
            'before' => [
                'activated_at' => self::clean($affiliation->activated_at),
                'effective_date' => self::clean($affiliation->effective_date),
                'renewal' => $oldAnchor?->addYearNoOverflow()->format('d/m/Y'),
            ],
            'after' => [
                'date' => $newAnchor?->format('d/m/Y'),
                'renewal' => $newAnchor?->addYearNoOverflow()->format('d/m/Y'),
            ],
            'frequency' => (string) ($frequency ?? $affiliation->payment_frequency),
            'collections' => [],
            'annual' => [],
            'renovations' => [],
            'payments' => [],
        ];

        if (strtoupper(trim((string) $affiliation->status)) !== self::ACTIVE_STATUS) {
            $blockers[] = 'Solo se corrige la vigencia de afiliaciones ACTIVAS (esta está '.($affiliation->status ?: 'sin estatus').').';
        }

        if ($frequency === null) {
            $blockers[] = 'La afiliación no tiene una frecuencia de pago válida; corríjala antes.';
        }

        if ($newAnchor === null) {
            $blockers[] = 'Indique la fecha correcta de emisión y vigencia (dd/mm/aaaa).';
        }

        if ($oldAnchor === null) {
            $blockers[] = 'La afiliación no tiene fecha de emisión ni de vigencia legible: no hay contra qué recalcular los avisos.';
        }

        if ($newAnchor !== null && $oldAnchor !== null) {
            if ($newAnchor->equalTo($oldAnchor) && self::clean($affiliation->effective_date) === $newAnchor->format('d/m/Y')) {
                $blockers[] = 'La afiliación ya tiene esa fecha de emisión y vigencia: no hay nada que corregir.';
            }

            if (abs($oldAnchor->diffInMonths($newAnchor, false)) > self::MAX_SHIFT_MONTHS) {
                $blockers[] = 'La fecha nueva está a más de '.self::MAX_SHIFT_MONTHS.' meses de la actual. Si es correcta, se trata como renovación, no como corrección.';
            }

            if ($newAnchor->greaterThan($today->addMonths(6))) {
                $blockers[] = 'La fecha nueva está a más de 6 meses en el futuro. Revísela.';
            }
        }

        $pendingProofs = PaidMembershipCorporate::query()
            ->where('affiliation_corporate_id', $affiliation->id)
            ->where('status', self::PENDING_PROOF_STATUS)
            ->count();

        if ($pendingProofs > 0) {
            $blockers[] = 'Hay '.$pendingProofs.' comprobante(s) de pago por aprobar. Apruébelos o recházelos antes: al aprobarse generarían avisos con la fecha anterior.';
        }

        $openRenovations = RenovationCorporate::query()
            ->where('affiliation_corporate_id', $affiliation->id)
            ->whereIn('status', ['VIGENTE', 'PERIODO DE RENOVACION'])
            ->orderBy('id')
            ->get();

        if ($openRenovations->contains(fn (RenovationCorporate $renovation): bool => $renovation->status === 'PERIODO DE RENOVACION')) {
            $blockers[] = 'La afiliación está en PERÍODO DE RENOVACIÓN. Corrija la fecha al aceptar la renovación, no aquí.';
        }

        if ($oldAnchor !== null && AffiliationCorporateRenovationHistory::query()
            ->where('affiliation_corporate_id', $affiliation->id)
            ->where('accepted_at', '>=', $oldAnchor->toDateString())
            ->exists()) {
            $warnings[] = 'El ciclo actual viene de una renovación aceptada. Verifique que la fecha corregida sea la de esa renovación.';
        }

        if ($newAnchor === null || $oldAnchor === null || $frequency === null) {
            $result['blockers'] = array_values(array_unique($blockers));
            $result['warnings'] = $warnings;

            return $result;
        }

        $collections = Collection::query()
            ->where('affiliation_code', $affiliation->code)
            ->whereIn('status', [self::PENDING_STATUS, self::PAID_STATUS])
            ->orderBy('id')
            ->get();

        $latestPaid = null;

        foreach ($collections->where('status', self::PAID_STATUS) as $paid) {
            $due = CollectionDueDate::of($paid);

            if ($due !== null && ($latestPaid === null || $due->greaterThan($latestPaid))) {
                $latestPaid = $due;
            }
        }

        foreach ($collections->where('status', self::PENDING_STATUS) as $collection) {
            $invoice = (string) ($collection->collection_invoice_number ?: '#'.$collection->id);
            $due = CollectionDueDate::of($collection);
            $anchor = CollectionDueDate::parse($collection->include_date) ?? $oldAnchor;
            $installment = $due !== null ? self::installmentIndex($anchor, $due, $months) : null;

            if ($installment === null || $installment < 1) {
                $blockers[] = 'El aviso '.$invoice.' ('.($due?->format('d/m/Y') ?? 'sin fecha').') no sigue el calendario de la vigencia actual. Ajústelo primero con «Ajustar cuota» en Gestión de Cobranza.';

                continue;
            }

            $newDue = $newAnchor->addMonthsNoOverflow($installment * $months);

            if ($latestPaid !== null && ! $newDue->greaterThan($latestPaid)) {
                $blockers[] = 'El aviso '.$invoice.' quedaría el '.$newDue->format('d/m/Y').', antes de una cuota ya pagada ('.$latestPaid->format('d/m/Y').').';
            }

            $result['collections'][] = [
                'id' => (int) $collection->id,
                'invoice' => $invoice,
                'installment' => (($installment % $perYear) + 1).' de '.$perYear,
                'old' => $due->format('d/m/Y'),
                'new' => $newDue->format('d/m/Y'),
                'amount' => (float) $collection->total_amount,
                'was_overdue' => $due->lessThan($today),
                'is_overdue' => $newDue->lessThan($today),
            ];
        }

        $nextPending = $result['collections'] !== []
            ? CollectionDueDate::parse($result['collections'][0]['new'])
            : $newAnchor->addMonthsNoOverflow($months);

        $yearMonths = [];

        for ($index = 0; $index < $perYear; $index++) {
            $yearMonths[] = (int) $newAnchor->addMonthsNoOverflow($index * $months)->month;
        }

        foreach (AnnualCollection::query()->where('affiliation_code', $affiliation->code)->where('status', self::PENDING_STATUS)->orderBy('id')->get() as $annual) {
            $result['annual'][] = [
                'id' => (int) $annual->id,
                'next' => $nextPending->format('d/m/Y'),
                'months' => array_values(array_unique($yearMonths)),
            ];
        }

        $newRenewal = $newAnchor->addYearNoOverflow();

        foreach ($openRenovations as $renovation) {
            $taken = RenovationCorporate::query()
                ->where('affiliation_corporate_id', $affiliation->id)
                ->whereKeyNot($renovation->id)
                ->whereDate('date_renewal', $newRenewal->toDateString())
                ->exists();

            if ($taken) {
                $blockers[] = 'Ya existe otra propuesta de renovación para el '.$newRenewal->format('d/m/Y').'. Revísela en Renovaciones antes de corregir.';

                continue;
            }

            $result['renovations'][] = [
                'id' => (int) $renovation->id,
                'old' => CollectionDueDate::parse($renovation->getRawOriginal('date_renewal'))?->format('d/m/Y') ?? (string) $renovation->getRawOriginal('date_renewal'),
                'new' => $newRenewal->format('d/m/Y'),
                'remaining_days' => (int) $today->diffInDays($newRenewal, false),
            ];
        }

        foreach (PaidMembershipCorporate::query()
            ->where('affiliation_corporate_id', $affiliation->id)
            ->whereIn('status', self::APPROVED_PAYMENT_STATUSES)
            ->where('created_at', '>=', $oldAnchor->subDays(7)->toDateString())
            ->orderBy('id')
            ->get() as $payment) {
            $result['payments'][] = [
                'id' => (int) $payment->id,
                'prox_old' => self::clean($payment->prox_payment_date),
                'prox_new' => self::formatLike($payment->prox_payment_date, $nextPending),
                'renewal_old' => self::clean($payment->renewal_date),
                'renewal_new' => self::formatLike($payment->renewal_date, $newRenewal),
            ];
        }

        if ($newAnchor->greaterThan($oldAnchor)) {
            $earlyUsages = AffiliateClinicalServiceUsage::query()
                ->where('affiliation_corporate_id', $affiliation->id)
                ->where('status', AffiliateClinicalServiceUsage::STATUS_CONSUMED)
                ->where('created_at', '<', $newAnchor->toDateString())
                ->count();

            if ($earlyUsages > 0) {
                $warnings[] = $earlyUsages.' consumo(s) de cupo clínico son anteriores a la fecha nueva: pasan a contarse en el período anterior.';
            }
        }

        $warnings[] = 'El certificado y los carnets muestran la vigencia anterior. Regenérelos desde la afiliación cuando corresponda (también se envían a ViVEplus).';

        $result['blockers'] = array_values(array_unique($blockers));
        $result['warnings'] = array_values(array_unique($warnings));

        return $result;
    }

    /**
     * Aplica la corrección. Lanza RuntimeException (sin escribir nada) si algo lo impide.
     */
    public static function apply(AffiliationCorporate $affiliation, mixed $newDate, string $reason, User $actor): AffiliationCorporateDateCorrection
    {
        CorporateDateCorrector::assertCan($actor);
        $reason = trim((string) preg_replace('/\s+/u', ' ', $reason));

        if (mb_strlen($reason) < self::REASON_MIN_LENGTH) {
            throw new RuntimeException('Explique el motivo de la corrección (mínimo '.self::REASON_MIN_LENGTH.' caracteres).');
        }

        return DB::transaction(function () use ($affiliation, $newDate, $reason, $actor): AffiliationCorporateDateCorrection {
            $locked = AffiliationCorporate::query()->whereKey($affiliation->getKey())->lockForUpdate()->firstOrFail();
            Collection::query()->where('affiliation_code', $locked->code)->orderBy('id')->lockForUpdate()->get(['id']);

            $plan = self::plan($locked, $newDate);

            if ($plan['blockers'] !== []) {
                throw new RuntimeException(implode("\n", $plan['blockers']));
            }

            $ids = [
                'affiliation_corporates' => [(int) $locked->id],
                'collections' => array_column($plan['collections'], 'id'),
                'annual_collections' => array_column($plan['annual'], 'id'),
                'renovation_corporates' => array_column($plan['renovations'], 'id'),
                'paid_membership_corporates' => array_column($plan['payments'], 'id'),
            ];
            $before = TableRowSnapshot::capture(self::COLUMNS, $ids);
            $date = (string) $plan['after']['date'];

            $locked->forceFill(['activated_at' => $date, 'effective_date' => $date])->save();

            $batch = (string) Str::uuid();

            foreach ($plan['collections'] as $row) {
                $collection = Collection::query()->findOrFail($row['id']);
                $collection->include_date = $date;
                $collection->next_payment_date = $row['new'];
                $collection->save();

                self::recordAdjustment($collection, $batch, ['next_payment_date' => ['before' => $row['old'], 'after' => $row['new']]], $reason, $actor);
            }

            foreach ($plan['annual'] as $row) {
                $due = CollectionDueDate::parse($row['next']);
                $payload = [
                    'next_payment_date' => $row['next'],
                    'filter_next_payment_date' => $due?->format('Y-m-d'),
                    'expiration_date' => $row['next'],
                ];

                for ($month = 1; $month <= 12; $month++) {
                    $payload['month_'.$month] = in_array($month, $row['months'], true);
                }

                DB::table('annual_collections')->where('id', $row['id'])->update($payload);
            }

            foreach ($plan['renovations'] as $row) {
                DB::table('renovation_corporates')->where('id', $row['id'])->update([
                    'date_renewal' => CollectionDueDate::parse($row['new'])?->toDateString(),
                    'remaining_days' => $row['remaining_days'],
                    'updated_by' => (string) $actor->name,
                    'updated_at' => now(),
                ]);
            }

            foreach ($plan['payments'] as $row) {
                DB::table('paid_membership_corporates')->where('id', $row['id'])->update([
                    'prox_payment_date' => $row['prox_new'],
                    'renewal_date' => $row['renewal_new'],
                    'updated_at' => now(),
                ]);
            }

            $after = TableRowSnapshot::capture(self::COLUMNS, $ids);
            $summary = 'Emisión y vigencia: '.($plan['before']['activated_at'] ?? 'sin fecha').' / '.($plan['before']['effective_date'] ?? 'sin fecha')
                .' → '.$date.'. Renovación: '.($plan['before']['renewal'] ?? '—').' → '.$plan['after']['renewal']
                .'. Avisos reprogramados: '.count($plan['collections']).'. Motivo: '.$reason;

            $correction = AffiliationCorporateDateCorrection::query()->create([
                'public_id' => (string) Str::uuid(),
                'affiliation_corporate_id' => (int) $locked->id,
                'affiliation_code' => (string) $locked->code,
                'status' => AffiliationCorporateDateCorrection::STATUS_APPLIED,
                'activated_at_before' => $plan['before']['activated_at'],
                'effective_date_before' => $plan['before']['effective_date'],
                'new_date' => $date,
                'reason' => $reason,
                'snapshot_before' => $before,
                'snapshot_after' => $after,
                'summary' => [
                    'batch_uuid' => $batch,
                    'renewal_before' => $plan['before']['renewal'],
                    'renewal_after' => $plan['after']['renewal'],
                    'collections' => $plan['collections'],
                    'renovations' => $plan['renovations'],
                    'payments' => $plan['payments'],
                ],
                'applied_by' => $actor->getKey(),
                'applied_by_name' => (string) $actor->name,
                'applied_at' => now(),
            ]);

            $locked->status_log_corporate_affiliations()->create([
                'affiliation_corporate_id' => $locked->id,
                'action' => self::LOG_ACTION,
                'observation' => $summary,
                'updated_by' => (string) $actor->name,
            ]);

            $locked->affiliationCorporateObservations()->create([
                'description' => $summary,
                'created_by' => (string) $actor->getKey(),
            ]);

            SecurityAudit::log('AUDIT_CORPORATE_EFFECTIVE_DATE_CORRECTED', 'administration.affiliation-corporates.correct-effective-date', [
                'correction_id' => $correction->id,
                'affiliation_code' => $locked->code,
                'before' => $plan['before'],
                'after' => $plan['after'],
                'collections' => count($plan['collections']),
                'reason' => $reason,
            ], $actor);

            return $correction;
        });
    }

    /**
     * El servicio revisa el permiso por su cuenta: una llamada directa tampoco pasa sin él.
     */
    public static function assertCan(User $actor): void
    {
        if (! UserNavigationAccess::canPerformModuleAction($actor, 'ADMINISTRACION', BusinessFilamentActionPermissionRegistry::CORRECT_CORPORATE_EFFECTIVE_DATE)) {
            throw new RuntimeException('No tiene permiso para corregir la vigencia de afiliaciones corporativas.');
        }
    }

    /**
     * Número de cuota (0 = la de la emisión) si la fecha cae exactamente en el calendario.
     */
    public static function installmentIndex(CarbonImmutable $anchor, CarbonImmutable $due, int $months): ?int
    {
        if ($months < 1 || $due->lessThan($anchor)) {
            return null;
        }

        $index = intdiv((int) $anchor->diffInMonths($due), $months);

        foreach ([$index, $index + 1] as $candidate) {
            if ($anchor->addMonthsNoOverflow($candidate * $months)->isSameDay($due)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<string, array{before: ?string, after: ?string}>  $changes
     */
    public static function recordAdjustment(Collection $collection, string $batch, array $changes, string $reason, User $actor): void
    {
        CollectionAdjustment::query()->create([
            'collection_id' => $collection->getKey(),
            'affiliation_code' => $collection->affiliation_code,
            'collection_invoice_number' => $collection->collection_invoice_number,
            'batch_uuid' => $batch,
            'mode' => CollectionAdjustment::MODE_EFFECTIVE_DATE,
            'changes' => $changes,
            'reason' => $reason,
            'performed_by_id' => $actor->getKey(),
            'performed_by_name' => $actor->name,
            'ip' => request()?->ip(),
            'user_agent' => Str::limit((string) request()?->userAgent(), 1000, ''),
        ]);
    }

    /**
     * Escribe la fecha con el mismo formato que ya tenía el campo (los pagos usan dd-mm-aaaa).
     */
    private static function formatLike(mixed $existing, CarbonImmutable $date): string
    {
        $existing = trim((string) $existing);

        return match (true) {
            preg_match('/^\d{4}-\d{2}-\d{2}/', $existing) === 1 => $date->format('Y-m-d'),
            preg_match('/^\d{1,2}-\d{1,2}-\d{4}$/', $existing) === 1 => $date->format('d-m-Y'),
            default => $date->format('d/m/Y'),
        };
    }

    private static function clean(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
