<?php

declare(strict_types=1);

namespace App\Support\CommercialStructure;

use App\Models\Agency;
use App\Models\Agent;
use App\Models\Commission;

final class CommissionReferidorPercentage
{
    /**
     * Porcentaje del referidor asignado a la agencia o al agente de la venta.
     * El agente tiene prioridad sobre la agencia. Sin referidor: 0.
     */
    public static function for(Commission $commission): float
    {
        $referrer = self::referrerFor($commission);

        return self::percentageOf($referrer);
    }

    public static function referrerFor(Commission $commission): Agency|Agent|null
    {
        $agent = $commission->agent instanceof Agent ? $commission->agent : null;
        $agency = $commission->agency instanceof Agency ? $commission->agency : null;

        return self::referrerForParticipants($agent, $agency);
    }

    public static function referrerForParticipants(?Agent $agent, ?Agency $agency): Agency|Agent|null
    {
        foreach ([$agent, $agency] as $participant) {
            if (! $participant instanceof Agency && ! $participant instanceof Agent) {
                continue;
            }

            $referrer = self::assignedReferrer($participant);

            if ($referrer !== null) {
                return $referrer;
            }
        }

        return null;
    }

    public static function percentageOf(Agency|Agent|null $referrer): float
    {
        if ($referrer === null || ! self::isActiveReferrer($referrer)) {
            return 0.0;
        }

        $percentage = round((float) ($referrer->referidor_percentage ?? 0), 2);

        if ($percentage < 0) {
            return 0.0;
        }

        if ($percentage > 100) {
            return 100.0;
        }

        return $percentage;
    }

    public static function isActiveReferrer(Agency|Agent $referrer): bool
    {
        return (bool) $referrer->is_referidor && self::hasAssignedValue($referrer->referidor_percentage);
    }

    /**
     * @return list<string>
     */
    public static function eagerLoadRelations(): array
    {
        return [
            'agency.referidor',
            'agency.referidorAgent',
            'agent.referidor',
            'agent.referidorAgent',
        ];
    }

    private static function assignedReferrer(Agency|Agent $record): Agency|Agent|null
    {
        if (self::hasAssignedValue($record->referidor_agent_id)) {
            $agent = $record->relationLoaded('referidorAgent')
                ? $record->getRelation('referidorAgent')
                : $record->referidorAgent;

            return $agent instanceof Agent ? $agent : null;
        }

        if (self::hasAssignedValue($record->referidor_id)) {
            $agency = $record->relationLoaded('referidor')
                ? $record->getRelation('referidor')
                : $record->referidor;

            return $agency instanceof Agency ? $agency : null;
        }

        return null;
    }

    private static function hasAssignedValue(mixed $value): bool
    {
        return $value !== null && $value !== '';
    }
}
