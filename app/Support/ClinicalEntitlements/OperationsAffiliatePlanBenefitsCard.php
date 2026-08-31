<?php

declare(strict_types=1);

namespace App\Support\ClinicalEntitlements;

use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\Benefit;
use App\Models\Limit;
use App\Models\Plan;
use App\Models\TelemedicinePatient;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Una sola lista: beneficio comercial + cupo clínico. Evita la doble columna
 * de badges y el bloque extra de cupo en la ficha de Operaciones.
 */
final class OperationsAffiliatePlanBenefitsCard
{
    /**
     * @return array{
     *     tone: string,
     *     summary: ?string,
     *     message: ?string,
     *     otpHint: bool,
     *     rows: list<array{label: string, limit: string, channel: ?string, count: ?string, balance: string, tone: string, clinical: bool}>
     * }
     */
    public static function viewData(Affiliate|AffiliateCorporate|null $record): array
    {
        if (! $record instanceof Affiliate && ! $record instanceof AffiliateCorporate) {
            return self::state('muted', 'No hay afiliado en esta ficha.');
        }

        try {
            $record->loadMissing(['plan.benefitPlans.limit:id,description']);
            $snapshot = $record instanceof Affiliate
                ? AffiliateClinicalEntitlementResolver::forAffiliate($record)
                : AffiliateClinicalEntitlementResolver::forAffiliateCorporate($record);
        } catch (Throwable $exception) {
            report($exception);

            return self::state('danger', 'No se pudo calcular el uso clínico. Revise la vigencia o el mapa del plan.');
        }

        $plan = $record->plan;
        $benefits = $plan instanceof Plan
            ? $plan->benefitPlans
            : collect();

        return self::present($snapshot, $benefits);
    }

    /**
     * @return array{
     *     tone: string,
     *     summary: ?string,
     *     message: ?string,
     *     otpHint: bool,
     *     rows: list<array{label: string, limit: string, channel: ?string, count: ?string, balance: string, tone: string, clinical: bool}>
     * }
     */
    public static function viewDataForPatient(?TelemedicinePatient $record): array
    {
        if (! $record instanceof TelemedicinePatient) {
            return self::state('muted', 'No hay paciente en esta ficha.');
        }

        try {
            $record->loadMissing(['plan.benefitPlans.limit:id,description']);
            $snapshot = AffiliateClinicalEntitlementResolver::forPatient($record);
        } catch (Throwable $exception) {
            report($exception);

            return self::state('danger', 'No se pudo calcular el uso clínico. Revise la vigencia o el mapa del plan.');
        }

        $plan = $record->plan;
        $benefits = $plan instanceof Plan
            ? $plan->benefitPlans
            : collect();

        return self::present($snapshot, $benefits);
    }

    /**
     * @param  Collection<int, Benefit>  $benefits
     * @return array{
     *     tone: string,
     *     summary: ?string,
     *     message: ?string,
     *     otpHint: bool,
     *     rows: list<array{label: string, limit: string, channel: ?string, count: ?string, balance: string, tone: string, clinical: bool}>
     * }
     */
    private static function present(ClinicalEntitlementSnapshot $snapshot, Collection $benefits): array
    {
        $rows = self::rowsFrom($snapshot, $benefits);

        if (! $snapshot->hasPlan) {
            return self::state('muted', $snapshot->blockingMessage !== ''
                ? $snapshot->blockingMessage
                : 'Sin plan asignado.');
        }

        if (! $snapshot->isComplete) {
            $missing = $snapshot->missingBenefitLabels;
            $preview = array_slice($missing, 0, 3);
            $extra = count($missing) > 3 ? ' +'.(count($missing) - 3) : '';
            $detail = $preview === []
                ? 'Negocios debe completar Planes → Uso clínico.'
                : 'Falta mapear: '.implode(', ', $preview).$extra.'.';

            return [
                'tone' => 'warning',
                'summary' => $rows === [] ? null : count($rows).' beneficios',
                'message' => $detail,
                'otpHint' => false,
                'rows' => $rows,
            ];
        }

        if ($rows === []) {
            return self::state('muted', 'Este plan no tiene beneficios cargados.');
        }

        $ok = 0;
        $exhausted = 0;
        $unmapped = 0;

        foreach ($rows as $row) {
            if (! $row['clinical']) {
                $unmapped++;

                continue;
            }

            if ($row['tone'] === 'danger') {
                $exhausted++;
            } else {
                $ok++;
            }
        }

        $parts = [];
        if ($ok > 0) {
            $parts[] = $ok.' con saldo';
        }
        if ($exhausted > 0) {
            $parts[] = $exhausted.' agotado'.($exhausted === 1 ? '' : 's');
        }
        if ($unmapped > 0) {
            $parts[] = $unmapped.' sin mapa';
        }

        return [
            'tone' => $exhausted > 0 && $ok === 0 ? 'danger' : 'ok',
            'summary' => implode(' · ', $parts),
            'message' => null,
            'otpHint' => $exhausted > 0,
            'rows' => $rows,
        ];
    }

    /**
     * @param  Collection<int, Benefit>  $benefits
     * @return list<array{label: string, limit: string, channel: ?string, count: ?string, balance: string, tone: string, clinical: bool}>
     */
    public static function rowsFrom(ClinicalEntitlementSnapshot $snapshot, Collection $benefits): array
    {
        $rows = [];
        $seenBenefitIds = [];

        foreach ($benefits as $benefit) {
            if (! $benefit instanceof Benefit) {
                continue;
            }

            $benefitId = (int) ($benefit->id ?? 0);
            $seenBenefitIds[$benefitId] = true;
            $entitlement = $benefitId > 0 ? $snapshot->forBenefit($benefitId) : null;
            $limit = $benefit->limit;
            $limitLabel = $limit instanceof Limit
                ? trim((string) ($limit->description ?? ''))
                : '';

            $rows[] = self::row(
                label: self::benefitLabel($benefit, $benefitId),
                limit: $limitLabel !== '' ? $limitLabel : '—',
                entitlement: $entitlement,
            );
        }

        foreach ($snapshot->entitlements as $entitlement) {
            if (isset($seenBenefitIds[$entitlement->benefitId])) {
                continue;
            }

            $rows[] = self::row(
                label: $entitlement->displayName(),
                limit: '—',
                entitlement: $entitlement,
            );
        }

        return $rows;
    }

    private static function benefitLabel(Benefit $benefit, int $benefitId): string
    {
        $pivotLabel = $benefit->relationLoaded('pivot')
            ? trim((string) ($benefit->getRelation('pivot')?->description ?? ''))
            : '';
        if ($pivotLabel !== '') {
            return $pivotLabel;
        }

        $label = trim((string) ($benefit->description ?? ''));

        return $label !== '' ? $label : 'Beneficio #'.$benefitId;
    }

    /**
     * @return array{label: string, limit: string, channel: ?string, count: ?string, balance: string, tone: string, clinical: bool}
     */
    private static function row(string $label, string $limit, ?ClinicalEntitlement $entitlement): array
    {
        if ($entitlement === null) {
            return [
                'label' => $label,
                'limit' => $limit,
                'channel' => null,
                'count' => null,
                'balance' => 'Sin mapa',
                'tone' => 'muted',
                'clinical' => false,
            ];
        }

        return [
            'label' => $label,
            'limit' => $limit,
            'channel' => $entitlement->channel->shortLabel(),
            'count' => $entitlement->operationsCountLabel(),
            'balance' => $entitlement->operationsBalanceLabel(),
            'tone' => $entitlement->operationsTone(),
            'clinical' => true,
        ];
    }

    /**
     * @return array{
     *     tone: string,
     *     summary: ?string,
     *     message: ?string,
     *     otpHint: bool,
     *     rows: list<array{label: string, limit: string, channel: ?string, count: ?string, balance: string, tone: string, clinical: bool}>
     * }
     */
    private static function state(string $tone, string $message): array
    {
        return [
            'tone' => $tone,
            'summary' => null,
            'message' => $message,
            'otpHint' => false,
            'rows' => [],
        ];
    }
}
