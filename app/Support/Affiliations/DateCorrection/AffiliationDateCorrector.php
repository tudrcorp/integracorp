<?php

declare(strict_types=1);

namespace App\Support\Affiliations\DateCorrection;

use App\Models\AffiliateClinicalServiceUsage;
use App\Models\AffiliationDateCorrection;
use App\Models\Collection;
use App\Models\CollectionAdjustment;
use App\Models\User;
use App\Support\AffiliationCorporates\CorporatePaymentFrequency;
use App\Support\Audit\TableRowSnapshot;
use App\Support\Collections\CollectionDueDate;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\UserNavigationAccess;
use App\Support\SecurityAudit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Corrige la fecha de inicio del ciclo de cobro (emisión y/o vigencia) de una afiliación,
 * individual o corporativa, y arrastra todo lo que se calculó con la fecha equivocada.
 *
 * La fuente de verdad del calendario son los avisos POR PAGAR (su `include_date`), no la
 * vigencia guardada: esa columna pudo editarse a mano o quedar como fecha de fin del año
 * (regla vieja del primer pago), mientras que las renovaciones la leen como inicio.
 *
 * - Primer año (sin renovaciones aceptadas): emisión y vigencia quedan en la fecha nueva.
 * - Póliza renovada: la emisión original no se toca; solo la vigencia del ciclo actual.
 *
 * Mueve: la afiliación, los avisos POR PAGAR (conservan su número de cuota; rastro en
 * `collection_adjustments`), la planificación anual POR PAGAR, las propuestas de renovación
 * abiertas (una queda en la fecha nueva y las demás se anulan) y las fechas informativas
 * del último pago aprobado del ciclo. Nunca toca avisos pagados o anulados, montos, la fecha
 * ni el estado de los pagos, ventas ni comisiones. Todo en una transacción, con foto antes
 * y después para revertir ({@see AffiliationDateCorrectionRollback}).
 */
final class AffiliationDateCorrector
{
    public const REASON_MIN_LENGTH = 15;

    public const MAX_SHIFT_MONTHS = 12;

    public const MAX_FUTURE_MONTHS = 6;

    public const PENDING_STATUS = 'POR PAGAR';

    public const PAID_STATUS = 'PAGADO';

    public const PENDING_PROOF_STATUS = 'PENDIENTE';

    public const ACTIVE_STATUS = 'ACTIVA';

    public const RENOVATION_OPEN = 'VIGENTE';

    public const RENOVATION_PERIOD = 'PERIODO DE RENOVACION';

    public const RENOVATION_CANCELLED = 'ANULADA';

    public const SCOPE_FIRST_YEAR = 'first_year';

    public const SCOPE_CURRENT_CYCLE = 'current_cycle';

    public const LOG_ACTION = 'CORRIGIO FECHA DE VIGENCIA';

    /** @var list<string> */
    public const APPROVED_PAYMENT_STATUSES = ['APROBADO', 'PAGADO'];

    /**
     * Calcula la corrección sin escribir nada.
     *
     * @return array{
     *     type: string,
     *     scope: string,
     *     blockers: list<string>,
     *     warnings: list<string>,
     *     before: array{activated_at: string|null, effective_date: string|null, cycle_start: string|null, renewal: string|null},
     *     after: array{activated_at: string|null, effective_date: string|null, renewal: string|null, anchor?: string},
     *     frequency: string,
     *     collections: list<array{id: int, invoice: string, installment: string, k: int, old: string, new: string, include_old: string|null, amount: float, was_overdue: bool, is_overdue: bool}>,
     *     annual: list<array{id: int, next: string, months: list<int>}>,
     *     renovations: list<array{id: int, action: string, old: string, new: string, remaining_days: int}>,
     *     payments: list<array{id: int, prox_old: string|null, prox_new: string, renewal_old: string|null, renewal_new: string}>
     * }
     */
    public static function plan(Model $affiliation, mixed $newDate, ?CarbonImmutable $today = null): array
    {
        $subject = DateCorrectionSubject::for($affiliation);
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $blockers = [];
        $warnings = [];

        $renewed = DB::table($subject->historiesTable)->where($subject->historiesForeignKey, $affiliation->getKey())->exists();
        $scope = $renewed ? self::SCOPE_CURRENT_CYCLE : self::SCOPE_FIRST_YEAR;
        $frequency = CorporatePaymentFrequency::normalize((string) $affiliation->payment_frequency);
        $months = $frequency !== null ? CorporatePaymentFrequency::monthsPerInstallment($frequency) : 12;
        $perYear = $frequency !== null ? CorporatePaymentFrequency::installmentsPerYear($frequency) : 1;
        $newAnchor = CollectionDueDate::parse($newDate);

        $collections = Collection::query()
            ->where('affiliation_code', $affiliation->code)
            ->whereIn('status', [self::PENDING_STATUS, self::PAID_STATUS])
            ->orderBy('id')
            ->get();
        $pending = $collections->where('status', self::PENDING_STATUS)->values();

        $cycleStart = self::billingAnchor($pending)
            ?? ($renewed ? CollectionDueDate::parse($affiliation->effective_date) : null)
            ?? CollectionDueDate::parse($affiliation->activated_at)
            ?? CollectionDueDate::parse($affiliation->effective_date);

        $result = [
            'type' => $subject->type,
            'scope' => $scope,
            'blockers' => [],
            'warnings' => [],
            'before' => [
                'activated_at' => self::clean($affiliation->activated_at),
                'effective_date' => self::clean($affiliation->effective_date),
                'cycle_start' => $cycleStart?->format('d/m/Y'),
                'renewal' => $cycleStart?->addYearNoOverflow()->format('d/m/Y'),
            ],
            'after' => [
                'activated_at' => $scope === self::SCOPE_FIRST_YEAR ? $newAnchor?->format('d/m/Y') : self::clean($affiliation->activated_at),
                'effective_date' => $newAnchor?->format('d/m/Y'),
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
            $blockers[] = 'Indique la fecha correcta (dd/mm/aaaa).';
        }

        if ($cycleStart === null) {
            $blockers[] = 'La afiliación no tiene avisos ni fechas legibles: no hay un calendario contra el cual corregir.';
        }

        if ($newAnchor !== null && $cycleStart !== null) {
            if (abs($cycleStart->diffInMonths($newAnchor, false)) > self::MAX_SHIFT_MONTHS) {
                $blockers[] = 'La fecha nueva está a más de '.self::MAX_SHIFT_MONTHS.' meses del inicio del ciclo actual ('.$cycleStart->format('d/m/Y').'). Si es correcta, se trata como renovación, no como corrección.';
            }

            if ($newAnchor->greaterThan($today->addMonths(self::MAX_FUTURE_MONTHS))) {
                $blockers[] = 'La fecha nueva está a más de '.self::MAX_FUTURE_MONTHS.' meses en el futuro. Revísela.';
            }
        }

        $pendingProofs = DB::table($subject->paymentsTable)
            ->where($subject->paymentsForeignKey, $affiliation->getKey())
            ->where('status', self::PENDING_PROOF_STATUS)
            ->count();

        if ($pendingProofs > 0) {
            $blockers[] = 'Hay '.$pendingProofs.' comprobante(s) de pago por aprobar. Apruébelos o recházelos antes: al aprobarse generarían avisos con la fecha anterior.';
        }

        $openRenovations = DB::table($subject->renovationsTable)
            ->where($subject->renovationsForeignKey, $affiliation->getKey())
            ->whereIn('status', [self::RENOVATION_OPEN, self::RENOVATION_PERIOD])
            ->orderBy('id')
            ->get(['id', 'date_renewal', 'status']);

        if ($openRenovations->contains(fn (object $renovation): bool => $renovation->status === self::RENOVATION_PERIOD)) {
            $blockers[] = 'La afiliación está en PERÍODO DE RENOVACIÓN. Corrija la fecha al aceptar la renovación, no aquí.';
        }

        if ($newAnchor === null || $cycleStart === null || $frequency === null) {
            return [...$result, 'blockers' => array_values(array_unique($blockers)), 'warnings' => $warnings];
        }

        if ($scope === self::SCOPE_CURRENT_CYCLE) {
            $warnings[] = 'Póliza renovada: la fecha de emisión original ('.($result['before']['activated_at'] ?? '—').') no se toca; se corrige solo la vigencia del ciclo actual.';
        }

        $latestPaid = null;

        foreach ($collections->where('status', self::PAID_STATUS) as $paid) {
            $due = CollectionDueDate::of($paid);

            if ($due !== null && ($latestPaid === null || $due->greaterThan($latestPaid))) {
                $latestPaid = $due;
            }
        }

        foreach ($pending as $collection) {
            $invoice = (string) ($collection->collection_invoice_number ?: '#'.$collection->id);
            $due = CollectionDueDate::of($collection);
            $anchor = CollectionDueDate::parse($collection->include_date) ?? $cycleStart;
            $installment = $due !== null ? self::installmentIndex($anchor, $due, $months) : null;

            if ($installment === null) {
                $blockers[] = 'El aviso '.$invoice.' ('.($due?->format('d/m/Y') ?? 'sin fecha').') no sigue el calendario de su ciclo. Ajústelo primero con «Ajustar cuota» en Gestión de Cobranza.';

                continue;
            }

            $newDue = $newAnchor->addMonthsNoOverflow($installment * $months);

            if ($latestPaid !== null && ! $newDue->greaterThan($latestPaid) && ! $newDue->isSameDay($due)) {
                $blockers[] = 'El aviso '.$invoice.' quedaría el '.$newDue->format('d/m/Y').', antes de una cuota ya pagada ('.$latestPaid->format('d/m/Y').').';
            }

            $result['collections'][] = [
                'id' => (int) $collection->id,
                'invoice' => $invoice,
                'installment' => (($installment % $perYear) + 1).' de '.$perYear,
                'old' => $due->format('d/m/Y'),
                'new' => $newDue->format('d/m/Y'),
                'include_old' => self::clean($collection->include_date),
                'k' => $installment,
                'amount' => (float) $collection->total_amount,
                'was_overdue' => $due->lessThan($today),
                'is_overdue' => $newDue->lessThan($today),
            ];
        }

        // Si los avisos pendientes ya son de un año posterior del mismo calendario (póliza que siguió
        // cobrando sin renovación aceptada), la vigencia es el inicio de ese año, no la emisión.
        $minInstallment = $result['collections'] === [] ? 0 : min(array_column($result['collections'], 'k'));
        $yearsOffset = intdiv($minInstallment, $perYear);
        $currentStart = $cycleStart->addYearsNoOverflow($yearsOffset);
        $newEffective = $newAnchor->addYearsNoOverflow($yearsOffset);
        $result['before']['cycle_start'] = $currentStart->format('d/m/Y');
        $result['before']['renewal'] = $currentStart->addYearNoOverflow()->format('d/m/Y');
        $result['after']['effective_date'] = $newEffective->format('d/m/Y');
        $result['after']['renewal'] = $newEffective->addYearNoOverflow()->format('d/m/Y');
        $result['after']['anchor'] = $newAnchor->format('d/m/Y');

        if ($yearsOffset > 0) {
            $warnings[] = 'Los avisos pendientes son del año '.($yearsOffset + 1).' del calendario que empezó el '.$cycleStart->format('d/m/Y').': la vigencia queda en el inicio de ese año ('.$newEffective->format('d/m/Y').').';
        }

        $storedEffective = CollectionDueDate::parse($affiliation->effective_date);

        if ($storedEffective !== null && ! $storedEffective->isSameDay($currentStart) && $storedEffective->isSameDay($currentStart->addYearNoOverflow())) {
            $warnings[] = 'La vigencia guardada ('.$storedEffective->format('d/m/Y').') estaba como fecha de FIN del año. Queda como INICIO del ciclo, que es como la leen las renovaciones.';
        }

        $firstFuture = null;

        foreach ($result['collections'] as $row) {
            $date = CollectionDueDate::parse($row['new']);

            if ($date !== null && ($firstFuture === null || $date->lessThan($firstFuture))) {
                $firstFuture = $date;
            }
        }

        $nextPending = $firstFuture ?? $newEffective->addMonthsNoOverflow($months);
        $yearMonths = [];

        for ($index = 0; $index < $perYear; $index++) {
            $yearMonths[] = (int) $newEffective->addMonthsNoOverflow($index * $months)->month;
        }

        foreach (DB::table('annual_collections')->where('affiliation_code', $affiliation->code)->where('status', self::PENDING_STATUS)->orderBy('id')->pluck('id') as $annualId) {
            $result['annual'][] = ['id' => (int) $annualId, 'next' => $nextPending->format('d/m/Y'), 'months' => array_values(array_unique($yearMonths))];
        }

        $newRenewal = $newEffective->addYearNoOverflow();
        $keeper = $openRenovations->first(fn (object $renovation): bool => CollectionDueDate::parse($renovation->date_renewal)?->isSameDay($newRenewal) ?? false)
            ?? $openRenovations->first();

        foreach ($openRenovations as $renovation) {
            $old = CollectionDueDate::parse($renovation->date_renewal);
            $isKeeper = (int) $renovation->id === (int) $keeper?->id;

            $result['renovations'][] = [
                'id' => (int) $renovation->id,
                'action' => $isKeeper ? ($old?->isSameDay($newRenewal) ? 'keep' : 'move') : 'annul',
                'old' => $old?->format('d/m/Y') ?? (string) $renovation->date_renewal,
                'new' => $isKeeper ? $newRenewal->format('d/m/Y') : '—',
                'remaining_days' => (int) $today->diffInDays($newRenewal, false),
            ];
        }

        // Solo el último pago aprobado lleva el «próximo pago» vigente; los anteriores son historia.
        foreach (DB::table($subject->paymentsTable)
            ->where($subject->paymentsForeignKey, $affiliation->getKey())
            ->whereIn('status', self::APPROVED_PAYMENT_STATUSES)
            ->where('created_at', '>=', $cycleStart->subDays(7)->toDateString())
            ->orderByDesc('id')
            ->limit(1)
            ->get(['id', 'prox_payment_date', 'renewal_date']) as $payment) {
            $result['payments'][] = [
                'id' => (int) $payment->id,
                'prox_old' => self::clean($payment->prox_payment_date),
                'prox_new' => self::formatLike($payment->prox_payment_date, $nextPending),
                'renewal_old' => self::clean($payment->renewal_date),
                'renewal_new' => self::formatLike($payment->renewal_date, $newRenewal),
            ];
        }

        if ($newEffective->greaterThan($currentStart)) {
            $earlyUsages = AffiliateClinicalServiceUsage::query()
                ->where($subject->usagesForeignKey, $affiliation->getKey())
                ->where('status', AffiliateClinicalServiceUsage::STATUS_CONSUMED)
                ->where('created_at', '>=', $currentStart->toDateString())
                ->where('created_at', '<', $newEffective->toDateString())
                ->count();

            if ($earlyUsages > 0) {
                $warnings[] = $earlyUsages.' consumo(s) de cupo clínico caen antes de la fecha nueva: pasan a contarse en el período anterior.';
            }
        }

        if (! self::hasChanges($result)) {
            $blockers[] = 'La afiliación y sus avisos ya están en esa fecha: no hay nada que corregir.';
        } else {
            $warnings[] = 'El certificado y los carnets muestran la vigencia anterior. Regenérelos cuando corresponda.';
        }

        return [...$result, 'blockers' => array_values(array_unique($blockers)), 'warnings' => array_values(array_unique($warnings))];
    }

    /**
     * Aplica la corrección. Con `$expectedFingerprint` (la huella que vio el analista en la vista
     * previa) se niega a escribir si los datos cambiaron desde entonces.
     */
    public static function apply(Model $affiliation, mixed $newDate, string $reason, User $actor, ?string $expectedFingerprint = null): AffiliationDateCorrection
    {
        $subject = DateCorrectionSubject::for($affiliation);
        self::assertCan($actor, $subject);
        $reason = trim((string) preg_replace('/\s+/u', ' ', $reason));

        if (mb_strlen($reason) < self::REASON_MIN_LENGTH) {
            throw new RuntimeException('Explique el motivo de la corrección (mínimo '.self::REASON_MIN_LENGTH.' caracteres).');
        }

        return DB::transaction(function () use ($affiliation, $subject, $newDate, $reason, $actor, $expectedFingerprint): AffiliationDateCorrection {
            /** @var Model $locked */
            $locked = $subject->modelClass()::query()->whereKey($affiliation->getKey())->lockForUpdate()->firstOrFail();
            Collection::query()->where('affiliation_code', $locked->code)->orderBy('id')->lockForUpdate()->get(['id']);

            $plan = self::plan($locked, $newDate);

            if ($plan['blockers'] !== []) {
                throw new RuntimeException(implode("\n", $plan['blockers']));
            }

            $columns = $subject->columns();
            $ids = [
                $subject->affiliationTable => [(int) $locked->getKey()],
                'collections' => array_column($plan['collections'], 'id'),
                'annual_collections' => array_column($plan['annual'], 'id'),
                $subject->renovationsTable => array_column($plan['renovations'], 'id'),
                $subject->paymentsTable => array_column($plan['payments'], 'id'),
            ];
            $before = TableRowSnapshot::capture($columns, $ids);

            if ($expectedFingerprint !== null && ! hash_equals($expectedFingerprint, self::fingerprint($before))) {
                throw new RuntimeException('Los datos de la afiliación cambiaron desde el resumen que revisó. Vuelva a preparar la reparación para ver el resumen actualizado.');
            }

            $date = (string) $plan['after']['effective_date'];
            $anchor = (string) ($plan['after']['anchor'] ?? $date);

            $locked->forceFill([
                'activated_at' => $plan['after']['activated_at'],
                'effective_date' => $date,
            ])->save();

            $batch = (string) Str::uuid();

            foreach ($plan['collections'] as $row) {
                $collection = Collection::query()->findOrFail($row['id']);
                $collection->include_date = $anchor;
                $collection->next_payment_date = $row['new'];
                $collection->save();

                self::recordAdjustment($collection, $batch, [
                    'next_payment_date' => ['before' => $row['old'], 'after' => $row['new']],
                    'include_date' => ['before' => $row['include_old'], 'after' => $anchor],
                ], $reason, $actor);
            }

            foreach ($plan['annual'] as $row) {
                $payload = [
                    'next_payment_date' => $row['next'],
                    'filter_next_payment_date' => CollectionDueDate::parse($row['next'])?->format('Y-m-d'),
                    'expiration_date' => $row['next'],
                ];

                for ($month = 1; $month <= 12; $month++) {
                    $payload['month_'.$month] = in_array($month, $row['months'], true);
                }

                DB::table('annual_collections')->where('id', $row['id'])->update($payload);
            }

            foreach ($plan['renovations'] as $row) {
                $payload = $row['action'] === 'annul'
                    ? ['status' => self::RENOVATION_CANCELLED]
                    : ['date_renewal' => CollectionDueDate::parse($row['new'])?->toDateString(), 'remaining_days' => $row['remaining_days']];

                DB::table($subject->renovationsTable)->where('id', $row['id'])->update([
                    ...$payload,
                    'updated_by' => (string) $actor->name,
                    'updated_at' => now(),
                ]);
            }

            foreach ($plan['payments'] as $row) {
                DB::table($subject->paymentsTable)->where('id', $row['id'])->update([
                    'prox_payment_date' => $row['prox_new'],
                    'renewal_date' => $row['renewal_new'],
                    'updated_at' => now(),
                ]);
            }

            $after = TableRowSnapshot::capture($columns, $ids);
            $summary = ($plan['scope'] === self::SCOPE_FIRST_YEAR ? 'Emisión y vigencia: ' : 'Vigencia del ciclo (renovada; la emisión no cambia): ')
                .($plan['before']['cycle_start'] ?? 'sin fecha').' → '.$date
                .'. Renovación: '.($plan['before']['renewal'] ?? '—').' → '.$plan['after']['renewal']
                .'. Avisos reprogramados: '.count($plan['collections']).'. Motivo: '.$reason;

            $correction = AffiliationDateCorrection::query()->create([
                'public_id' => (string) Str::uuid(),
                'affiliation_type' => $subject->type,
                'affiliation_id' => (int) $locked->getKey(),
                'affiliation_code' => (string) $locked->code,
                'status' => AffiliationDateCorrection::STATUS_APPLIED,
                'scope' => $plan['scope'],
                'activated_at_before' => $plan['before']['activated_at'],
                'effective_date_before' => $plan['before']['effective_date'],
                'new_date' => $date,
                'reason' => $reason,
                'snapshot_before' => $before,
                'snapshot_after' => $after,
                'summary' => [
                    'batch_uuid' => $batch,
                    'cycle_start_before' => $plan['before']['cycle_start'],
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

            self::log($locked, $subject, self::LOG_ACTION, $summary, $actor);

            SecurityAudit::log('AUDIT_EFFECTIVE_DATE_CORRECTED', 'affiliations.correct-effective-date', [
                'correction_id' => $correction->id,
                'type' => $subject->type,
                'affiliation_code' => $locked->code,
                'scope' => $plan['scope'],
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
     * La acción se asigna en Administración o en Negocios.
     */
    public static function assertCan(User $actor, DateCorrectionSubject $subject): void
    {
        foreach (['ADMINISTRACION', 'NEGOCIOS'] as $module) {
            if (UserNavigationAccess::canPerformModuleAction($actor, $module, $subject->permissionSlug)) {
                return;
            }
        }

        // Quien repara desde el detector de inconsistencias usa esta misma herramienta.
        if (UserNavigationAccess::canPerformModuleAction($actor, 'ADMINISTRACION', BusinessFilamentActionPermissionRegistry::REPAIR_DATE_INCONSISTENCIES)) {
            return;
        }

        throw new RuntimeException('No tiene permiso para corregir la vigencia de una '.$subject->label.'.');
    }

    /**
     * Huella de una foto de filas: si cambia, los datos ya no son los que se revisaron.
     *
     * @param  array<string, array<int|string, array<string, string|null>>>  $snapshot
     */
    public static function fingerprint(array $snapshot): string
    {
        return sha1((string) json_encode($snapshot));
    }

    /**
     * Calendario real de cobro de la afiliación, según sus avisos POR PAGAR.
     *
     * @return array{
     *     renewed: bool,
     *     frequency: string|null,
     *     months: int,
     *     per_year: int,
     *     anchor: CarbonImmutable|null,
     *     current_start: CarbonImmutable|null,
     *     years_offset: int,
     *     aligned: list<array{id: int, invoice: string, due: string, k: int}>,
     *     misaligned: list<array{id: int, invoice: string, due: string|null, include: string|null, expected: string|null, days_off: int|null, before_cycle: bool}>
     * }
     */
    public static function calendar(Model $affiliation): array
    {
        $subject = DateCorrectionSubject::for($affiliation);
        $renewed = DB::table($subject->historiesTable)->where($subject->historiesForeignKey, $affiliation->getKey())->exists();
        $frequency = CorporatePaymentFrequency::normalize((string) $affiliation->payment_frequency);
        $months = $frequency !== null ? CorporatePaymentFrequency::monthsPerInstallment($frequency) : 12;
        $perYear = $frequency !== null ? CorporatePaymentFrequency::installmentsPerYear($frequency) : 1;

        $pending = Collection::query()
            ->where('affiliation_code', $affiliation->code)
            ->where('status', self::PENDING_STATUS)
            ->orderBy('id')
            ->get(['id', 'collection_invoice_number', 'include_date', 'next_payment_date', 'filter_next_payment_date']);

        $anchor = self::billingAnchor($pending)
            ?? ($renewed ? CollectionDueDate::parse($affiliation->effective_date) : null)
            ?? CollectionDueDate::parse($affiliation->activated_at)
            ?? CollectionDueDate::parse($affiliation->effective_date);

        $aligned = [];
        $misaligned = [];

        foreach ($pending as $collection) {
            $invoice = (string) ($collection->collection_invoice_number ?: '#'.$collection->id);
            $due = CollectionDueDate::of($collection);
            $base = CollectionDueDate::parse($collection->include_date) ?? $anchor;
            $k = ($due !== null && $base !== null) ? self::installmentIndex($base, $due, $months) : null;

            if ($k === null) {
                $expected = null;

                if ($due !== null && $base !== null && ! $due->lessThan($base)) {
                    $expected = $base->addMonthsNoOverflow((int) round($base->diffInMonths($due) / $months) * $months);
                }

                $misaligned[] = [
                    'id' => (int) $collection->id,
                    'invoice' => $invoice,
                    'due' => $due?->format('d/m/Y'),
                    'include' => self::clean($collection->include_date),
                    'expected' => $expected?->format('d/m/Y'),
                    'days_off' => $expected !== null ? (int) $expected->diffInDays($due, false) : null,
                    'before_cycle' => $due !== null && $base !== null && $due->lessThan($base),
                ];
            } else {
                $aligned[] = ['id' => (int) $collection->id, 'invoice' => $invoice, 'due' => $due->format('d/m/Y'), 'k' => $k];
            }
        }

        $offset = $aligned === [] ? 0 : intdiv(min(array_column($aligned, 'k')), $perYear);

        return [
            'renewed' => $renewed,
            'frequency' => $frequency,
            'months' => $months,
            'per_year' => $perYear,
            'anchor' => $anchor,
            'current_start' => $anchor?->addYearsNoOverflow($offset),
            'years_offset' => $offset,
            'aligned' => $aligned,
            'misaligned' => $misaligned,
        ];
    }

    /**
     * Número de cuota (0 = la del inicio del ciclo) si la fecha cae exactamente en el calendario.
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

    public static function log(Model $affiliation, DateCorrectionSubject $subject, string $action, string $text, User $actor): void
    {
        $affiliation->{$subject->statusLogRelation}()->create([
            $subject->statusLogForeignKey => $affiliation->getKey(),
            'action' => $action,
            'observation' => $text,
            'updated_by' => (string) $actor->name,
        ]);

        $affiliation->{$subject->observationsRelation}()->create([
            'description' => $text,
            'created_by' => (string) $actor->getKey(),
        ]);
    }

    /**
     * Inicio del ciclo según los avisos pendientes: la `include_date` más repetida.
     *
     * @param  \Illuminate\Support\Collection<int, Collection>  $pending
     */
    private static function billingAnchor(\Illuminate\Support\Collection $pending): ?CarbonImmutable
    {
        $counts = [];

        foreach ($pending as $collection) {
            $date = CollectionDueDate::parse($collection->include_date);

            if ($date !== null) {
                $key = $date->toDateString();
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        if ($counts === []) {
            return null;
        }

        arsort($counts);

        return CarbonImmutable::parse((string) array_key_first($counts))->startOfDay();
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private static function hasChanges(array $plan): bool
    {
        if ($plan['before']['activated_at'] !== $plan['after']['activated_at'] || $plan['before']['effective_date'] !== $plan['after']['effective_date']) {
            return true;
        }

        foreach ($plan['collections'] as $row) {
            if ($row['old'] !== $row['new'] || $row['include_old'] !== ($plan['after']['anchor'] ?? $plan['after']['effective_date'])) {
                return true;
            }
        }

        foreach ($plan['renovations'] as $row) {
            if ($row['action'] !== 'keep') {
                return true;
            }
        }

        foreach ($plan['payments'] as $row) {
            if ($row['prox_old'] !== $row['prox_new'] || $row['renewal_old'] !== $row['renewal_new']) {
                return true;
            }
        }

        return false;
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
