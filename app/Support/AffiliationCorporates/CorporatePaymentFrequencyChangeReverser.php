<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates;

use App\Exceptions\CorporatePaymentFrequencyChangeBlockedException;
use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\AffiliationCorporatePaymentFrequencyChange as FrequencyChange;
use App\Models\AfilliationCorporatePlan;
use App\Models\Collection as BillingCollection;
use App\Models\PaidMembershipCorporate;
use App\Models\RenovationCorporate;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Revierte un cambio de frecuencia de pago y deja la afiliación exactamente
 * como estaba: los avisos creados pasan a CANCELADO, los cancelados vuelven a
 * POR PAGAR y se restauran frecuencia y montos de afiliación, afiliados, filas
 * de plan y renovación preparada. Nada se borra.
 *
 * Solo se permite si nada cambió después: un aviso cobrado, un abono, una
 * tarifa distinta o un cambio de frecuencia posterior harían que restaurar la
 * foto anterior descuadre la cobranza, así que se bloquea con el motivo.
 */
final class CorporatePaymentFrequencyChangeReverser
{
    public const MIN_REASON_LENGTH = 20;

    public const MAX_REASON_LENGTH = 1000;

    /**
     * Por qué no se puede revertir, o null si se puede. Solo lee.
     */
    public static function blockReason(FrequencyChange $change): ?string
    {
        try {
            self::assertReversible($change, lock: false);
        } catch (CorporatePaymentFrequencyChangeBlockedException $exception) {
            return $exception->getMessage();
        }

        return null;
    }

    /**
     * @return array{restored: int, cancelled: int, notices: int}
     *
     * @throws CorporatePaymentFrequencyChangeBlockedException
     * @throws InvalidArgumentException
     */
    public static function reverse(FrequencyChange $change, string $reason, string $confirmationCode, ?string $actor = null): array
    {
        $reason = trim(preg_replace('/\s+/u', ' ', $reason) ?? '');

        if (mb_strlen($reason) < self::MIN_REASON_LENGTH) {
            throw new InvalidArgumentException('Explique el motivo del reverso (mínimo '.self::MIN_REASON_LENGTH.' caracteres).');
        }

        if (mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            throw new InvalidArgumentException('El motivo no puede superar los '.self::MAX_REASON_LENGTH.' caracteres.');
        }

        if (! self::confirmationMatches($change, $confirmationCode)) {
            throw new InvalidArgumentException('El código escrito no coincide con el de la afiliación. Escriba '.self::expectedConfirmation($change).' para confirmar.');
        }

        $actor = trim((string) ($actor ?? Auth::user()?->name ?? '')) ?: 'system';

        $result = DB::transaction(function () use ($change, $reason, $actor): array {
            /** @var FrequencyChange $locked */
            $locked = FrequencyChange::query()->whereKey($change->getKey())->lockForUpdate()->firstOrFail();
            [$owner, $created, $cancelled] = self::assertReversible($locked, lock: true);

            foreach ($created as $collection) {
                $collection->status = CorporatePaymentFrequencyChanger::CANCELLED_COLLECTION_STATUS;
                $collection->save();
            }

            foreach ($cancelled as $collection) {
                $collection->status = CorporateAffiliateUpgradeManager::PENDING_COLLECTION_STATUS;
                $collection->affiliate_status = $owner->status;
                $collection->save();
            }

            $snapshot = $locked->snapshot ?? [];
            $previousFrequency = $snapshot['affiliation']['payment_frequency'] ?? $locked->previous_frequency;

            $owner->payment_frequency = $previousFrequency;
            $owner->total_amount = round((float) ($snapshot['affiliation']['total_amount'] ?? $locked->previous_total_amount), 2);
            $owner->save();

            self::restoreAffiliates($owner, $snapshot['affiliates'] ?? [], $previousFrequency);
            self::restoreFrequencyById(AfilliationCorporatePlan::class, $owner, $snapshot['plan_rows'] ?? []);
            self::restoreFrequencyById(RenovationCorporate::class, $owner, $snapshot['renovations'] ?? []);

            $locked->status = FrequencyChange::STATUS_REVERSED;
            $locked->reversed_at = now();
            $locked->reversed_by_id = Auth::id();
            $locked->reversed_by_name = $actor;
            $locked->reversal_reason = $reason;
            $locked->save();

            SecurityAudit::log('AUDIT_ADMINISTRATION_CORPORATE_PAYMENT_FREQUENCY_REVERSED', 'administration.payment-frequency-changes.reverse', [
                'panel' => 'administration',
                'module' => 'affiliation_corporates',
                'change_id' => $locked->getKey(),
                'affiliation_corporate_id' => $owner->getKey(),
                'affiliation_code' => $owner->code,
                'restored_frequency' => $previousFrequency,
                'reverted_frequency' => $locked->new_frequency,
                'restored_collection_ids' => array_map(fn (BillingCollection $c): int => (int) $c->getKey(), $cancelled),
                'cancelled_collection_ids' => array_map(fn (BillingCollection $c): int => (int) $c->getKey(), $created),
                'reason' => $reason,
                'actor' => $actor,
            ]);

            return [
                'owner' => $owner,
                'restored_ids' => array_map(fn (BillingCollection $c): int => (int) $c->getKey(), $cancelled),
                'cancelled_count' => count($created),
            ];
        });

        $notices = CorporateCollectionNoticeDispatcher::dispatch($result['owner'], $result['restored_ids']);
        CorporatePaymentFrequencyChangeNotifier::reversed((int) $change->getKey());

        return [
            'restored' => count($result['restored_ids']),
            'cancelled' => $result['cancelled_count'],
            'notices' => $notices,
        ];
    }

    /**
     * Marca el cambio como revisado por Administración. No altera datos de cobranza.
     */
    public static function validate(FrequencyChange $change, ?string $actor = null): void
    {
        DB::transaction(function () use ($change, $actor): void {
            /** @var FrequencyChange $locked */
            $locked = FrequencyChange::query()->whereKey($change->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isReversed()) {
                throw new CorporatePaymentFrequencyChangeBlockedException('Este cambio ya fue revertido; no hay nada que validar.');
            }

            if ($locked->isValidated()) {
                return;
            }

            $actor = trim((string) ($actor ?? Auth::user()?->name ?? '')) ?: 'system';

            $locked->validated_at = now();
            $locked->validated_by_id = Auth::id();
            $locked->validated_by_name = $actor;
            $locked->save();

            SecurityAudit::log('AUDIT_ADMINISTRATION_CORPORATE_PAYMENT_FREQUENCY_VALIDATED', 'administration.payment-frequency-changes.validate', [
                'panel' => 'administration',
                'change_id' => $locked->getKey(),
                'affiliation_code' => $locked->affiliation_code,
                'actor' => $actor,
            ]);
        });
    }

    public static function expectedConfirmation(FrequencyChange $change): string
    {
        $code = trim((string) $change->affiliation_code);

        return $code !== '' ? $code : (string) $change->affiliation_corporate_id;
    }

    public static function confirmationMatches(FrequencyChange $change, ?string $typed): bool
    {
        return mb_strtoupper(trim((string) $typed)) === mb_strtoupper(self::expectedConfirmation($change));
    }

    /**
     * @return array{0: AffiliationCorporate, 1: list<BillingCollection>, 2: list<BillingCollection>}
     *
     * @throws CorporatePaymentFrequencyChangeBlockedException
     */
    private static function assertReversible(FrequencyChange $change, bool $lock): array
    {
        if ($change->isReversed()) {
            throw new CorporatePaymentFrequencyChangeBlockedException(
                'Este cambio ya fue revertido el '.($change->reversed_at?->format('d/m/Y H:i') ?? '—').' por '.($change->reversed_by_name ?: 'el sistema').'.'
            );
        }

        $ownerQuery = AffiliationCorporate::query()->whereKey($change->affiliation_corporate_id);
        $owner = ($lock ? $ownerQuery->lockForUpdate() : $ownerQuery)->first();

        if (! $owner instanceof AffiliationCorporate) {
            throw new CorporatePaymentFrequencyChangeBlockedException('La afiliación ya no existe; no se puede revertir.');
        }

        $laterChange = FrequencyChange::query()
            ->where('affiliation_corporate_id', $owner->getKey())
            ->where('status', FrequencyChange::STATUS_APPLIED)
            ->where('id', '>', $change->getKey())
            ->exists();

        if ($laterChange) {
            throw new CorporatePaymentFrequencyChangeBlockedException(
                'Después de este hubo otro cambio de frecuencia en la afiliación. Revierta primero el más reciente.'
            );
        }

        $status = mb_strtoupper(trim((string) $owner->status), 'UTF-8');

        if (in_array($status, CorporatePaymentFrequencyChanger::BLOCKED_AFFILIATION_STATUSES, true)) {
            throw new CorporatePaymentFrequencyChangeBlockedException('La afiliación está '.mb_strtolower($status, 'UTF-8').'; no se puede revertir.');
        }

        if (CorporatePaymentFrequency::normalize($owner->payment_frequency) !== CorporatePaymentFrequency::normalize($change->new_frequency)) {
            throw new CorporatePaymentFrequencyChangeBlockedException(
                'La afiliación ya no tiene la frecuencia '.CorporatePaymentFrequency::label($change->new_frequency).'; alguien la modificó después.'
            );
        }

        if (abs(round((float) $owner->fee_anual, 2) - round((float) $change->fee_anual, 2)) >= 0.01) {
            throw new CorporatePaymentFrequencyChangeBlockedException(
                'La tarifa anual de la afiliación cambió después (upgrades, altas o bajas). Restaurar los avisos anteriores descuadraría la cobranza.'
            );
        }

        $hasPendingProof = PaidMembershipCorporate::query()
            ->where('affiliation_corporate_id', $owner->getKey())
            ->where('status', CorporatePaymentFrequencyChanger::PENDING_PROOF_STATUS)
            ->exists();

        if ($hasPendingProof) {
            throw new CorporatePaymentFrequencyChangeBlockedException(
                'Hay un comprobante de pago pendiente de aprobación. Apruébelo o recházelo antes de revertir.'
            );
        }

        $created = self::lockedCollections($change->created_collections ?? [], $lock);
        $cancelled = self::lockedCollections($change->cancelled_collections ?? [], $lock);

        foreach ($change->created_collections ?? [] as $row) {
            $collection = $created[(int) ($row['id'] ?? 0)] ?? null;
            $invoice = (string) ($row['invoice'] ?? '#'.($row['id'] ?? '?'));

            if ($collection === null) {
                throw new CorporatePaymentFrequencyChangeBlockedException('El aviso '.$invoice.' creado por el cambio ya no existe.');
            }

            if ((string) $collection->status !== CorporateAffiliateUpgradeManager::PENDING_COLLECTION_STATUS) {
                throw new CorporatePaymentFrequencyChangeBlockedException(
                    'El aviso '.$invoice.' creado por el cambio ya está en estado '.$collection->status.'. Solo se revierte si ninguno fue cobrado.'
                );
            }

            if ((float) ($collection->pay_amount_usd ?? 0) > 0 || (float) ($collection->pay_amount_ves ?? 0) > 0) {
                throw new CorporatePaymentFrequencyChangeBlockedException('El aviso '.$invoice.' tiene un abono registrado.');
            }

            if (abs(round((float) $collection->total_amount, 2) - round((float) ($row['amount'] ?? 0), 2)) >= 0.01) {
                throw new CorporatePaymentFrequencyChangeBlockedException('El monto del aviso '.$invoice.' cambió después del cambio de frecuencia.');
            }
        }

        foreach ($change->cancelled_collections ?? [] as $row) {
            $collection = $cancelled[(int) ($row['id'] ?? 0)] ?? null;
            $invoice = (string) ($row['invoice'] ?? '#'.($row['id'] ?? '?'));

            if ($collection === null) {
                throw new CorporatePaymentFrequencyChangeBlockedException('El aviso original '.$invoice.' ya no existe.');
            }

            if ((string) $collection->status !== CorporatePaymentFrequencyChanger::CANCELLED_COLLECTION_STATUS) {
                throw new CorporatePaymentFrequencyChangeBlockedException(
                    'El aviso original '.$invoice.' ya no está cancelado (estado '.$collection->status.').'
                );
            }
        }

        return [$owner, array_values($created), array_values($cancelled)];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, BillingCollection>
     */
    private static function lockedCollections(array $rows, bool $lock): array
    {
        $ids = array_values(array_filter(array_map(fn (array $row): int => (int) ($row['id'] ?? 0), $rows)));

        if ($ids === []) {
            return [];
        }

        $query = BillingCollection::query()->whereIn('id', $ids)->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get()->keyBy(fn (BillingCollection $collection): int => (int) $collection->getKey())->all();
    }

    /**
     * Si la tarifa del afiliado no cambió, se restaura su subtotal exacto; si
     * cambió, se recalcula con la frecuencia restaurada.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private static function restoreAffiliates(AffiliationCorporate $owner, array $rows, ?string $frequency): void
    {
        $byId = collect($rows)->keyBy(fn (array $row): int => (int) ($row['id'] ?? 0));

        if ($byId->isEmpty()) {
            return;
        }

        AffiliateCorporate::query()
            ->where('affiliation_corporate_id', $owner->getKey())
            ->whereIn('id', $byId->keys()->all())
            ->orderBy('id')
            ->get()
            ->each(function (AffiliateCorporate $affiliate) use ($byId, $frequency): void {
                $row = $byId->get((int) $affiliate->getKey());
                $sameFee = abs(round((float) $affiliate->fee, 2) - round((float) ($row['fee'] ?? 0), 2)) < 0.01;

                $affiliate->payment_frequency = $row['payment_frequency'] ?? $frequency;
                $affiliate->subtotal_payment_frequency = $sameFee
                    ? round((float) ($row['subtotal_payment_frequency'] ?? 0), 2)
                    : CorporatePaymentFrequency::periodAmount((float) $affiliate->fee, $frequency);
                $affiliate->save();
            });
    }

    /**
     * @param  class-string<AfilliationCorporatePlan|RenovationCorporate>  $model
     * @param  array<int, array<string, mixed>>  $rows
     */
    private static function restoreFrequencyById(string $model, AffiliationCorporate $owner, array $rows): void
    {
        foreach ($rows as $row) {
            $model::query()
                ->where('affiliation_corporate_id', $owner->getKey())
                ->whereKey((int) ($row['id'] ?? 0))
                ->update(['payment_frequency' => $row['payment_frequency'] ?? null]);
        }
    }
}
