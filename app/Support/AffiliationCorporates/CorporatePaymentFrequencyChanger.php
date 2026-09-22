<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates;

use App\Exceptions\CorporatePaymentFrequencyChangeBlockedException;
use App\Http\Controllers\UtilsController;
use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\AffiliationCorporatePaymentFrequencyChange;
use App\Models\AfilliationCorporatePlan;
use App\Models\Collection as BillingCollection;
use App\Models\PaidMembershipCorporate;
use App\Models\RenovationCorporate;
use App\Support\SecurityAudit;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Cambia la frecuencia de pago de una afiliación corporativa.
 *
 * Todo en una transacción: afiliación, afiliados, filas de plan, renovación
 * preparada y cobranza. Los avisos de cobro pendientes se anulan (CANCELADO,
 * nunca se borran) y se reemplazan por otros con la nueva frecuencia que cubren
 * exactamente los mismos meses y el mismo saldo: cambiar la frecuencia reparte
 * la deuda de otra forma, no la re-tarifica. Si los meses no calzan con la
 * nueva frecuencia, la última cuota se prorratea.
 */
final class CorporatePaymentFrequencyChanger
{
    public const CANCELLED_COLLECTION_STATUS = 'CANCELADO';

    public const PENDING_PROOF_STATUS = 'PENDIENTE';

    /**
     * @var list<string>
     */
    public const BLOCKED_AFFILIATION_STATUSES = [
        'EXCLUIDO', 'EXCLUIDA', 'ANULADO', 'ANULADA', 'INACTIVO', 'INACTIVA', 'CANCELADO', 'CANCELADA',
    ];

    /**
     * Qué pasaría al cambiar a esa frecuencia, sin escribir nada.
     *
     * @return array{blocked: string|null, unchanged: bool, current: string, target: string, period_amount: float, pending_count: int, pending_balance: float, schedule: list<array{date: string, months: int, amount: float}>}
     */
    public static function preview(AffiliationCorporate $owner, string $frequency): array
    {
        $target = self::normalizeTarget($frequency);
        $current = (string) $owner->payment_frequency;
        $base = [
            'blocked' => null,
            'unchanged' => false,
            'current' => $current,
            'target' => $target,
            'period_amount' => CorporatePaymentFrequency::periodAmount((float) $owner->fee_anual, $target),
            'pending_count' => 0,
            'pending_balance' => 0.0,
            'schedule' => [],
        ];

        try {
            self::assertStatusAllowsChange($owner);

            if (CorporatePaymentFrequency::normalize($current) === $target) {
                return [...$base, 'unchanged' => true];
            }

            self::assertNoPendingProof($owner);
            $pending = self::pendingInstallments($owner, self::previousFrequency($owner), lock: false);
        } catch (CorporatePaymentFrequencyChangeBlockedException $exception) {
            return [...$base, 'blocked' => $exception->getMessage()];
        }

        return [
            ...$base,
            'pending_count' => count($pending),
            'pending_balance' => round(array_sum(array_column($pending, 'amount')), 2),
            'schedule' => array_map(fn (array $installment): array => [
                'date' => $installment['date']->format('d/m/Y'),
                'months' => $installment['months'],
                'amount' => $installment['amount'],
            ], self::reschedule($pending, $target)),
        ];
    }

    /**
     * @param  string|null  $batchUuid  Lote al que pertenece (cambio masivo); uno nuevo si es individual.
     * @param  bool  $notify  false cuando el lote notifica de forma consolidada al terminar.
     * @return array{changed: bool, change_id: int|null, previous: string, frequency: string, period_amount: float, cancelled: int, created: int, notices: int}
     *
     * @throws CorporatePaymentFrequencyChangeBlockedException
     */
    public static function change(
        AffiliationCorporate $owner,
        string $frequency,
        ?string $actor = null,
        ?string $batchUuid = null,
        bool $notify = true,
    ): array {
        $target = self::normalizeTarget($frequency);
        $actor = self::actorName($actor);
        $batchUuid ??= (string) Str::uuid();

        $result = DB::transaction(function () use ($owner, $target, $actor, $batchUuid): array {
            /** @var AffiliationCorporate $locked */
            $locked = AffiliationCorporate::query()->whereKey($owner->getKey())->lockForUpdate()->firstOrFail();
            $previousRaw = (string) $locked->payment_frequency;

            self::assertStatusAllowsChange($locked);

            if (CorporatePaymentFrequency::normalize($previousRaw) === $target) {
                return [
                    'changed' => false,
                    'previous' => $previousRaw,
                    'frequency' => $target,
                    'period_amount' => round((float) $locked->total_amount, 2),
                    'change_id' => null,
                    'cancelled_ids' => [],
                    'created_ids' => [],
                ];
            }

            self::assertNoPendingProof($locked);

            $snapshot = self::snapshotOf($locked);
            $previousTotalAmount = round((float) $locked->total_amount, 2);
            $pending = self::pendingInstallments($locked, self::previousFrequency($locked), lock: true);
            $schedule = self::reschedule($pending, $target);
            $cancelledDetails = self::describePending($pending);
            $cancelledIds = self::cancelPending($pending);
            $createdDetails = self::createInstallments($locked, $pending, $schedule, $target, $actor);
            $createdIds = array_column($createdDetails, 'id');

            $periodAmount = CorporatePaymentFrequency::periodAmount((float) $locked->fee_anual, $target);
            $locked->payment_frequency = $target;
            $locked->total_amount = $periodAmount;
            $locked->save();

            AffiliateCorporate::query()
                ->where('affiliation_corporate_id', $locked->getKey())
                ->update([
                    'payment_frequency' => $target,
                    'subtotal_payment_frequency' => DB::raw('ROUND(COALESCE(fee, 0) / '.CorporatePaymentFrequency::installmentsPerYear($target).'.0, 2)'),
                ]);

            AfilliationCorporatePlan::query()
                ->where('affiliation_corporate_id', $locked->getKey())
                ->update(['payment_frequency' => $target]);

            /** Una renovación ya preparada aplicaría la frecuencia vieja al aceptarse. */
            RenovationCorporate::query()
                ->where('affiliation_corporate_id', $locked->getKey())
                ->update(['payment_frequency' => $target]);

            SecurityAudit::log('AUDIT_BUSINESS_CORPORATE_PAYMENT_FREQUENCY_CHANGED', 'business.affiliation-corporates.payment-frequency', [
                'panel' => 'business',
                'module' => 'affiliation_corporates',
                'affiliation_corporate_id' => $locked->getKey(),
                'affiliation_code' => $locked->code,
                'previous_frequency' => $previousRaw,
                'new_frequency' => $target,
                'fee_anual' => (float) $locked->fee_anual,
                'period_amount' => $periodAmount,
                'pending_balance' => round(array_sum(array_column($pending, 'amount')), 2),
                'cancelled_collection_ids' => $cancelledIds,
                'created_collection_ids' => $createdIds,
                'actor' => $actor,
            ]);

            $change = AffiliationCorporatePaymentFrequencyChange::query()->create([
                'batch_uuid' => $batchUuid,
                'affiliation_corporate_id' => $locked->getKey(),
                'affiliation_code' => $locked->code,
                'affiliation_name' => $locked->name_corporate,
                'previous_frequency' => $previousRaw,
                'new_frequency' => $target,
                'fee_anual' => round((float) $locked->fee_anual, 2),
                'previous_total_amount' => $previousTotalAmount,
                'new_total_amount' => $periodAmount,
                'pending_balance' => round(array_sum(array_column($pending, 'amount')), 2),
                'cancelled_collections' => $cancelledDetails,
                'created_collections' => $createdDetails,
                'snapshot' => $snapshot,
                'status' => AffiliationCorporatePaymentFrequencyChange::STATUS_APPLIED,
                'performed_by_id' => Auth::id(),
                'performed_by_name' => $actor,
                'performed_from' => self::currentPanelId(),
                'ip' => self::requestIp(),
                'user_agent' => self::requestUserAgent(),
            ]);

            return [
                'changed' => true,
                'change_id' => (int) $change->getKey(),
                'previous' => $previousRaw,
                'frequency' => $target,
                'period_amount' => $periodAmount,
                'cancelled_ids' => $cancelledIds,
                'created_ids' => $createdIds,
            ];
        });

        $notices = CorporateCollectionNoticeDispatcher::dispatch($owner, $result['created_ids']);

        if ($notify && $result['change_id'] !== null) {
            CorporatePaymentFrequencyChangeNotifier::applied([$result['change_id']]);
        }

        return [
            'changed' => $result['changed'],
            'change_id' => $result['change_id'],
            'previous' => $result['previous'],
            'frequency' => $result['frequency'],
            'period_amount' => $result['period_amount'],
            'cancelled' => count($result['cancelled_ids']),
            'created' => count($result['created_ids']),
            'notices' => $notices,
        ];
    }

    /**
     * Cada afiliación en su propia transacción: una bloqueada no revierte a las demás.
     *
     * @param  iterable<int, AffiliationCorporate>  $owners
     * @return array{changed: list<string>, unchanged: list<string>, blocked: list<array{name: string, reason: string}>, cancelled: int, created: int, change_ids: list<int>}
     */
    public static function changeMany(iterable $owners, string $frequency, ?string $actor = null): array
    {
        $target = self::normalizeTarget($frequency);
        $batchUuid = (string) Str::uuid();
        $summary = ['changed' => [], 'unchanged' => [], 'blocked' => [], 'cancelled' => 0, 'created' => 0, 'change_ids' => []];

        foreach ($owners as $owner) {
            $name = self::labelFor($owner);

            try {
                $result = self::change($owner, $target, $actor, $batchUuid, notify: false);
            } catch (CorporatePaymentFrequencyChangeBlockedException $exception) {
                $summary['blocked'][] = ['name' => $name, 'reason' => $exception->getMessage()];

                continue;
            } catch (Throwable $throwable) {
                Log::error('NEGOCIOS-AFILIACIONES-CORPORATIVAS: Error al cambiar la frecuencia de pago.', [
                    'affiliation_corporate_id' => $owner->getKey(),
                    'frequency' => $target,
                    'error' => $throwable->getMessage(),
                ]);
                $summary['blocked'][] = ['name' => $name, 'reason' => 'Error inesperado; no se aplicó ningún cambio.'];

                continue;
            }

            $summary[$result['changed'] ? 'changed' : 'unchanged'][] = $name;
            $summary['cancelled'] += $result['cancelled'];
            $summary['created'] += $result['created'];

            if ($result['change_id'] !== null) {
                $summary['change_ids'][] = $result['change_id'];
            }
        }

        /** Una sola notificación consolidada por lote, no una por afiliación. */
        if ($summary['change_ids'] !== []) {
            CorporatePaymentFrequencyChangeNotifier::applied($summary['change_ids']);
        }

        return $summary;
    }

    /**
     * Reparte los meses y el saldo pendientes en cuotas de la nueva frecuencia.
     *
     * @param  list<array{date: Carbon, months: int, amount: float}>  $pending
     * @return list<array{date: Carbon, months: int, amount: float}>
     */
    public static function reschedule(array $pending, string $frequency): array
    {
        if ($pending === []) {
            return [];
        }

        $totalMonths = array_sum(array_column($pending, 'months'));
        $balance = round(array_sum(array_column($pending, 'amount')), 2);

        if ($totalMonths <= 0) {
            return [];
        }

        $start = collect($pending)->min(fn (array $installment): Carbon => $installment['date'])->copy()->startOfDay();
        $interval = CorporatePaymentFrequency::monthsPerInstallment($frequency);
        $count = intdiv($totalMonths + $interval - 1, $interval);
        $schedule = [];
        $assigned = 0.0;

        for ($index = 0; $index < $count; $index++) {
            $months = min($interval, $totalMonths - ($index * $interval));
            $amount = $index === $count - 1
                ? round($balance - $assigned, 2)
                : round($balance * $months / $totalMonths, 2);
            $assigned += $amount;

            $schedule[] = [
                'date' => $start->copy()->addMonthsNoOverflow($index * $interval),
                'months' => $months,
                'amount' => $amount,
            ];
        }

        return $schedule;
    }

    /**
     * Fecha de un aviso: primero la que se imprime (`next_payment_date`), luego la de filtro.
     */
    public static function parseDueDate(?string $nextPaymentDate, ?string $filterDate = null): ?Carbon
    {
        return self::parseDate((string) $nextPaymentDate) ?? self::parseDate((string) $filterDate);
    }

    /**
     * Acepta d/m/Y, d-m-Y y Y-m-d (con o sin hora); rechaza fechas imposibles.
     */
    private static function parseDate(string $value): ?Carbon
    {
        $value = trim($value);

        if (preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/', $value, $matches) === 1) {
            [$day, $month, $year] = [(int) $matches[1], (int) $matches[2], (int) $matches[3]];
        } elseif (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})(?:[ T].*)?$/', $value, $matches) === 1) {
            [$year, $month, $day] = [(int) $matches[1], (int) $matches[2], (int) $matches[3]];
        } else {
            return null;
        }

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return Carbon::create($year, $month, $day)->startOfDay();
    }

    private static function normalizeTarget(string $frequency): string
    {
        $target = CorporatePaymentFrequency::normalize($frequency);

        if ($target === null) {
            throw new InvalidArgumentException('Seleccione una frecuencia de pago válida: Anual, Semestral, Trimestral o Mensual.');
        }

        return $target;
    }

    private static function previousFrequency(AffiliationCorporate $owner): string
    {
        return CorporatePaymentFrequency::normalize($owner->payment_frequency) ?? CorporatePaymentFrequency::ANUAL;
    }

    private static function assertStatusAllowsChange(AffiliationCorporate $owner): void
    {
        $status = mb_strtoupper(trim((string) $owner->status), 'UTF-8');

        if (in_array($status, self::BLOCKED_AFFILIATION_STATUSES, true)) {
            throw new CorporatePaymentFrequencyChangeBlockedException(
                'La afiliación está '.mb_strtolower($status, 'UTF-8').'; no se puede cambiar su frecuencia de pago.'
            );
        }
    }

    private static function assertNoPendingProof(AffiliationCorporate $owner): void
    {
        $hasPendingProof = PaidMembershipCorporate::query()
            ->where('affiliation_corporate_id', $owner->getKey())
            ->where('status', self::PENDING_PROOF_STATUS)
            ->exists();

        if ($hasPendingProof) {
            throw new CorporatePaymentFrequencyChangeBlockedException(
                'Tiene un comprobante de pago pendiente de aprobación. Apruébelo o recházelo en Administración antes de cambiar la frecuencia.'
            );
        }
    }

    /**
     * @return list<array{collection: BillingCollection, date: Carbon, months: int, amount: float}>
     */
    private static function pendingInstallments(AffiliationCorporate $owner, string $previousFrequency, bool $lock): array
    {
        if (blank($owner->code)) {
            return [];
        }

        $query = BillingCollection::query()
            ->where('affiliation_code', $owner->code)
            ->where('status', CorporateAffiliateUpgradeManager::PENDING_COLLECTION_STATUS)
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        $installments = [];

        foreach ($query->get() as $collection) {
            $invoice = (string) ($collection->collection_invoice_number ?: '#'.$collection->getKey());

            if ((float) ($collection->pay_amount_usd ?? 0) > 0 || (float) ($collection->pay_amount_ves ?? 0) > 0) {
                throw new CorporatePaymentFrequencyChangeBlockedException(
                    'El aviso de cobro '.$invoice.' tiene un abono registrado. Concilie ese pago antes de cambiar la frecuencia.'
                );
            }

            $date = self::parseDueDate($collection->next_payment_date, $collection->filter_next_payment_date);

            if ($date === null) {
                throw new CorporatePaymentFrequencyChangeBlockedException(
                    'No se pudo leer la fecha de cobro del aviso '.$invoice.'. Corríjala en Administración antes de cambiar la frecuencia.'
                );
            }

            $collectionFrequency = CorporatePaymentFrequency::normalize($collection->payment_frequency) ?? $previousFrequency;

            $installments[] = [
                'collection' => $collection,
                'date' => $date,
                'months' => CorporatePaymentFrequency::monthsPerInstallment($collectionFrequency),
                'amount' => round((float) $collection->total_amount, 2),
            ];
        }

        if ($installments !== [] && array_sum(array_column($installments, 'amount')) <= 0.0) {
            throw new CorporatePaymentFrequencyChangeBlockedException(
                'Los avisos de cobro pendientes suman 0,00 US$. Revise la cobranza de la afiliación antes de cambiar la frecuencia.'
            );
        }

        return $installments;
    }

    /**
     * @param  list<array{collection: BillingCollection, date: Carbon, months: int, amount: float}>  $pending
     * @return list<int>
     */
    private static function cancelPending(array $pending): array
    {
        $ids = [];

        foreach ($pending as $installment) {
            $collection = $installment['collection'];
            $collection->status = self::CANCELLED_COLLECTION_STATUS;
            $collection->save();
            $ids[] = (int) $collection->getKey();
        }

        return $ids;
    }

    /**
     * @param  list<array{collection: BillingCollection, date: Carbon, months: int, amount: float}>  $pending
     * @param  list<array{date: Carbon, months: int, amount: float}>  $schedule
     * @return list<array{id: int, invoice: string, date: string, amount: float, months: int, frequency: string}>
     */
    private static function createInstallments(
        AffiliationCorporate $owner,
        array $pending,
        array $schedule,
        string $frequency,
        string $actor,
    ): array {
        if ($schedule === [] || $pending === []) {
            return [];
        }

        /** El aviso nuevo hereda venta, agencia, agente y datos del cliente del primero que reemplaza. */
        $template = $pending[0]['collection'];
        $lastInvoice = (string) (BillingCollection::query()->latest('id')->value('collection_invoice_number') ?? '');
        $expirationDays = $frequency === CorporatePaymentFrequency::MENSUAL ? 30 : 5;
        $created = [];

        foreach ($schedule as $installment) {
            $collection = $template->replicate();
            $collection->collection_invoice_number = UtilsController::generateCorrelativeCollection($lastInvoice);
            $collection->affiliate_status = $owner->status;
            $collection->payment_frequency = $frequency;
            $collection->total_amount = $installment['amount'];
            $collection->next_payment_date = $installment['date']->format('d/m/Y');
            $collection->filter_next_payment_date = $installment['date']->format('Y-m-d');
            $collection->expiration_date = $installment['date']->copy()->addDays($expirationDays)->format('d/m/Y');
            $collection->status = CorporateAffiliateUpgradeManager::PENDING_COLLECTION_STATUS;
            $collection->days = 0;
            $collection->created_by = $actor;
            $collection->save();

            $lastInvoice = (string) $collection->collection_invoice_number;
            $created[] = [
                'id' => (int) $collection->getKey(),
                'invoice' => (string) $collection->collection_invoice_number,
                'date' => (string) $collection->next_payment_date,
                'amount' => round((float) $installment['amount'], 2),
                'months' => (int) $installment['months'],
                'frequency' => $frequency,
            ];
        }

        return $created;
    }

    /**
     * Estado anterior de todo lo que el cambio toca, para poder revertirlo exacto.
     *
     * @return array{affiliation: array{payment_frequency: string|null, total_amount: float}, affiliates: list<array{id: int, fee: float, payment_frequency: string|null, subtotal_payment_frequency: float}>, plan_rows: list<array{id: int, payment_frequency: string|null}>, renovations: list<array{id: int, payment_frequency: string|null}>}
     */
    private static function snapshotOf(AffiliationCorporate $owner): array
    {
        return [
            'affiliation' => [
                'payment_frequency' => $owner->payment_frequency,
                'total_amount' => round((float) $owner->total_amount, 2),
            ],
            'affiliates' => AffiliateCorporate::query()
                ->where('affiliation_corporate_id', $owner->getKey())
                ->orderBy('id')
                ->get(['id', 'fee', 'payment_frequency', 'subtotal_payment_frequency'])
                ->map(fn (AffiliateCorporate $affiliate): array => [
                    'id' => (int) $affiliate->getKey(),
                    'fee' => round((float) $affiliate->fee, 2),
                    'payment_frequency' => $affiliate->payment_frequency,
                    'subtotal_payment_frequency' => round((float) $affiliate->subtotal_payment_frequency, 2),
                ])
                ->all(),
            'plan_rows' => AfilliationCorporatePlan::query()
                ->where('affiliation_corporate_id', $owner->getKey())
                ->orderBy('id')
                ->get(['id', 'payment_frequency'])
                ->map(fn (AfilliationCorporatePlan $row): array => ['id' => (int) $row->getKey(), 'payment_frequency' => $row->payment_frequency])
                ->all(),
            'renovations' => RenovationCorporate::query()
                ->where('affiliation_corporate_id', $owner->getKey())
                ->orderBy('id')
                ->get(['id', 'payment_frequency'])
                ->map(fn (RenovationCorporate $row): array => ['id' => (int) $row->getKey(), 'payment_frequency' => $row->payment_frequency])
                ->all(),
        ];
    }

    /**
     * @param  list<array{collection: BillingCollection, date: Carbon, months: int, amount: float}>  $pending
     * @return list<array{id: int, invoice: string, date: string, amount: float, months: int, frequency: string|null}>
     */
    private static function describePending(array $pending): array
    {
        return array_map(fn (array $installment): array => [
            'id' => (int) $installment['collection']->getKey(),
            'invoice' => (string) $installment['collection']->collection_invoice_number,
            'date' => (string) $installment['collection']->next_payment_date,
            'amount' => $installment['amount'],
            'months' => $installment['months'],
            'frequency' => $installment['collection']->payment_frequency,
        ], $pending);
    }

    private static function currentPanelId(): ?string
    {
        try {
            return Filament::getCurrentPanel()?->getId();
        } catch (Throwable) {
            return null;
        }
    }

    private static function requestIp(): ?string
    {
        return app()->runningInConsole() ? null : request()->ip();
    }

    private static function requestUserAgent(): ?string
    {
        return app()->runningInConsole() ? null : mb_substr((string) request()->userAgent(), 0, 1000);
    }

    private static function labelFor(AffiliationCorporate $owner): string
    {
        $label = implode(' · ', array_filter([trim((string) $owner->code), trim((string) $owner->name_corporate)]));

        return $label !== '' ? $label : 'Afiliación #'.$owner->getKey();
    }

    private static function actorName(?string $actor): string
    {
        $actor = trim((string) ($actor ?? Auth::user()?->name ?? ''));

        return $actor !== '' ? $actor : 'system';
    }
}
