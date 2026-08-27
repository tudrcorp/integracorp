<?php

declare(strict_types=1);

namespace App\Support\CommercialStructure;

use App\Models\Agency;
use App\Models\Agent;
use App\Models\Commission;
use Illuminate\Support\Facades\Log;
use Throwable;

final class CommissionReferidorCalculator
{
    /**
     * @return array{percentage: float, usd: float, ves: float}
     */
    public static function compute(
        ?Agent $agent,
        ?Agency $agency,
        float $totalAmount,
        float $payAmountUsd = 0.0,
        float $payAmountVes = 0.0,
    ): array {
        $empty = [
            'percentage' => 0.0,
            'usd' => 0.0,
            'ves' => 0.0,
        ];

        $safeTotal = max(0.0, $totalAmount);

        if ($safeTotal <= 0) {
            return $empty;
        }

        $referrer = CommissionReferidorPercentage::referrerForParticipants($agent, $agency);

        if ($referrer === null || ! CommissionReferidorPercentage::isActiveReferrer($referrer)) {
            return $empty;
        }

        $percentage = CommissionReferidorPercentage::percentageOf($referrer);

        if ($percentage <= 0) {
            return $empty;
        }

        $usd = round($safeTotal * $percentage / 100, 2);
        $ves = self::vesFromSaleRate($usd, $percentage, $payAmountUsd, $payAmountVes);

        return [
            'percentage' => $percentage,
            'usd' => $usd,
            'ves' => $ves,
        ];
    }

    public static function apply(Commission $commission): void
    {
        try {
            $result = self::compute(
                self::resolveAgent($commission),
                self::resolveAgency($commission),
                (float) ($commission->amount ?? 0),
                (float) ($commission->pay_amount_usd ?? 0),
                (float) ($commission->pay_amount_ves ?? 0),
            );

            $commission->porcent_referidor = $result['percentage'];
            $commission->commission_referidor_usd = $result['usd'];
            $commission->commission_referidor_ves = $result['ves'];
        } catch (Throwable $exception) {
            Log::error('No se pudo calcular la comisión de referidor. La venta continúa con 0.', [
                'commission_code' => $commission->code ?? null,
                'agent_id' => $commission->agent_id ?? null,
                'code_agency' => $commission->code_agency ?? null,
                'error' => $exception->getMessage(),
            ]);

            $commission->porcent_referidor = 0;
            $commission->commission_referidor_usd = 0;
            $commission->commission_referidor_ves = 0;
        }
    }

    private static function vesFromSaleRate(
        float $usdCommission,
        float $percentage,
        float $payAmountUsd,
        float $payAmountVes,
    ): float {
        if ($usdCommission <= 0 || $payAmountVes <= 0) {
            return 0.0;
        }

        if ($payAmountUsd > 0) {
            return round($usdCommission * ($payAmountVes / $payAmountUsd), 2);
        }

        return round($payAmountVes * $percentage / 100, 2);
    }

    private static function resolveAgent(Commission $commission): ?Agent
    {
        if ($commission->relationLoaded('agent')) {
            $agent = $commission->getRelation('agent');

            return $agent instanceof Agent ? self::ensureReferrerRelations($agent) : null;
        }

        if ($commission->agent_id === null || $commission->agent_id === '') {
            return null;
        }

        return Agent::query()
            ->with(['referidor', 'referidorAgent'])
            ->find($commission->agent_id);
    }

    private static function resolveAgency(Commission $commission): ?Agency
    {
        if ($commission->relationLoaded('agency')) {
            $agency = $commission->getRelation('agency');

            return $agency instanceof Agency ? self::ensureReferrerRelations($agency) : null;
        }

        $code = trim((string) ($commission->code_agency ?? ''));

        if ($code === '') {
            return null;
        }

        return Agency::query()
            ->with(['referidor', 'referidorAgent'])
            ->where('code', $code)
            ->first();
    }

    /**
     * @template T of Agency|Agent
     *
     * @param  T  $record
     * @return T
     */
    private static function ensureReferrerRelations(Agency|Agent $record): Agency|Agent
    {
        if (! $record->relationLoaded('referidor')) {
            $record->loadMissing('referidor');
        }

        if (! $record->relationLoaded('referidorAgent')) {
            $record->loadMissing('referidorAgent');
        }

        return $record;
    }
}
