<?php

declare(strict_types=1);

namespace App\Support\Upgrades;

use App\Models\AffiliateCorporateUpgrade;
use App\Models\AffiliateUpgrade;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\Collection as BillingCollection;
use App\Support\AffiliationCorporates\CorporatePaymentFrequencyChanger;
use RuntimeException;

/**
 * Forma de cobro al agregar upgrades a una afiliación que ya existe (Negocios → «Agregar
 * upgrade»). Una afiliación tiene una sola forma de cobro para todos sus upgrades:
 *
 * - si ya tiene upgrades activos, se usa la suya y no se puede elegir otra;
 * - si no tiene, se toma la elegida (Anual por defecto), se valida contra la frecuencia de la
 *   afiliación y queda guardada en ella.
 */
final class UpgradeBillingResolution
{
    public const PENDING_COLLECTION_STATUS = 'POR PAGAR';

    /**
     * Valida, guarda la elección en la afiliación (bloqueada por el llamador) y devuelve el
     * `billing_mode` de las asignaciones nuevas.
     *
     * @throws RuntimeException con el motivo, en palabras del analista
     */
    public static function resolveForAdd(Affiliation|AffiliationCorporate $locked, ?string $requested): string
    {
        $current = UpgradeBillingChoice::of($locked);

        if (self::hasActiveUpgrades($locked)) {
            $requested = UpgradeBillingChoice::normalize($requested);

            if ($requested !== null && $requested !== $current) {
                throw new RuntimeException('Esta afiliación ya cobra sus upgrades '.mb_strtolower(UpgradeBillingChoice::label($current))
                    .'. Todos los upgrades de una afiliación se cobran igual: elija esa misma forma, o quite primero los upgrades actuales para cambiarla.');
            }

            return UpgradeBillingChoice::modeFor($current, $locked->payment_frequency);
        }

        $choice = UpgradeBillingChoice::normalize($requested) ?? $current;
        $error = UpgradeBillingChoice::error($choice, $locked->payment_frequency);

        if ($error !== null) {
            throw new RuntimeException($error);
        }

        if ($locked->getAttribute('upgrade_billing_frequency') !== $choice && UpgradeFirstPaymentBilling::hasColumn($locked->getTable(), 'upgrade_billing_frequency')) {
            $locked->forceFill(['upgrade_billing_frequency' => $choice])->save();
        }

        return UpgradeBillingChoice::modeFor($choice, $locked->payment_frequency);
    }

    /**
     * Para la vista previa (sin escribir): modo que se aplicaría y el bloqueo, si lo hay.
     *
     * @return array{mode: string, choice: string, locked: bool, blocker: ?string}
     */
    public static function preview(Affiliation|AffiliationCorporate $owner, ?string $requested): array
    {
        $current = UpgradeBillingChoice::of($owner);
        $locked = self::hasActiveUpgrades($owner);
        $choice = $locked ? $current : (UpgradeBillingChoice::normalize($requested) ?? $current);
        $blocker = null;

        if ($locked && UpgradeBillingChoice::normalize($requested) !== null && UpgradeBillingChoice::normalize($requested) !== $current) {
            $blocker = 'Esta afiliación ya cobra sus upgrades '.mb_strtolower(UpgradeBillingChoice::label($current)).'. Todos sus upgrades se cobran igual.';
        } elseif (! $locked) {
            $blocker = UpgradeBillingChoice::error($choice, $owner->payment_frequency);
        }

        return [
            'mode' => UpgradeBillingChoice::modeFor($choice, $owner->payment_frequency),
            'choice' => $choice,
            'locked' => $locked,
            'blocker' => $blocker,
        ];
    }

    public static function hasActiveUpgrades(Affiliation|AffiliationCorporate $owner): bool
    {
        if (! $owner->exists) {
            return false;
        }

        return $owner instanceof Affiliation
            ? AffiliateUpgrade::query()
                ->where('status', AffiliateUpgrade::STATUS_ACTIVE)
                ->whereHas('affiliate', fn ($query) => $query->where('affiliation_id', $owner->getKey()))
                ->exists()
            : AffiliateCorporateUpgrade::query()
                ->where('affiliation_corporate_id', $owner->getKey())
                ->where('status', AffiliateCorporateUpgrade::STATUS_ACTIVE)
                ->exists();
    }

    /**
     * Cuotas por pagar que recibirían el reparto, sin escribir: el mismo cálculo que
     * `UpgradeFirstPaymentBilling::spreadOverPendingInstallments()`.
     *
     * @param  list<float>  $amounts  monto anual de cada asignación nueva
     * @return array{0: list<array{invoice: string, date: ?string, before: float, after: float}>, 1: string, 2: ?string}
     */
    public static function spreadPreview(Affiliation|AffiliationCorporate $owner, array $amounts): array
    {
        $amounts = array_values(array_filter($amounts, fn (float $amount): bool => $amount > 0));
        $total = round(array_sum($amounts), 2);
        $note = 'Los upgrades de esta afiliación van repartidos en cada cuota ('.mb_strtolower(UpgradeBillingChoice::label($owner->payment_frequency)).').';

        if ($total <= 0) {
            return [[], $note, null];
        }

        if (UpgradeFirstPaymentBilling::awaitsFirstPayment($owner)) {
            return [[], $note.' Suben la cuota desde el primer pago.', null];
        }

        $collections = BillingCollection::query()
            ->calendarInstallments()
            ->where('affiliation_code', (string) $owner->code)
            ->where('status', self::PENDING_COLLECTION_STATUS)
            ->orderByRaw('filter_next_payment_date IS NULL')
            ->orderBy('filter_next_payment_date')
            ->orderBy('id')
            ->get();

        if ($collections->isEmpty()) {
            return [[], $note, 'La afiliación no tiene avisos de cobro por pagar este año donde repartir el upgrade. Elija cobro Anual o espere al próximo aviso.'];
        }

        $count = $collections->count();
        $added = array_fill(0, $count, 0);

        foreach ($amounts as $amount) {
            $cents = (int) round($amount * 100);
            $part = intdiv($cents, $count);

            foreach (range(0, $count - 1) as $position) {
                $added[$position] += $part + ($position === $count - 1 ? $cents - $part * $count : 0);
            }
        }

        $blocker = null;
        $rows = [];

        foreach ($collections->values() as $position => $collection) {
            $invoice = (string) ($collection->collection_invoice_number ?: '#'.$collection->getKey());

            if ((float) ($collection->pay_amount_usd ?? 0) > 0 || (float) ($collection->pay_amount_ves ?? 0) > 0) {
                $blocker = 'El aviso de cobro '.$invoice.' tiene un abono registrado. Concilie ese pago en Administración antes de cambiar upgrades.';
            }

            $before = round((float) $collection->total_amount, 2);
            $rows[] = [
                'invoice' => $invoice,
                'date' => CorporatePaymentFrequencyChanger::parseDueDate($collection->next_payment_date, $collection->filter_next_payment_date)?->format('d/m/Y'),
                'before' => $before,
                'after' => round($before + $added[$position] / 100, 2),
            ];
        }

        return [$rows, $note.' Se agregan a mitad de año: el monto completo se reparte entre las '.$count.' cuota(s) que quedan por pagar.', $blocker];
    }
}
