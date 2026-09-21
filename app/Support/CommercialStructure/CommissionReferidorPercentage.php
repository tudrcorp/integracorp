<?php

declare(strict_types=1);

namespace App\Support\CommercialStructure;

use App\Models\Agency;
use App\Models\Agent;
use App\Models\Commission;

final class CommissionReferidorPercentage
{
    /**
     * Suma los porcentajes de todos los referidores del agente (prioridad)
     * o, si el agente no tiene, de la agencia. Sin referidor: 0.
     */
    public static function for(Commission $commission): float
    {
        return self::totalPercentage(self::referrersFor($commission));
    }

    public static function referrerFor(Commission $commission): Agency|Agent|null
    {
        $referrers = self::referrersFor($commission);

        return $referrers[0] ?? null;
    }

    /**
     * @return list<Agency|Agent>
     */
    public static function referrersFor(Commission $commission): array
    {
        $agent = $commission->agent instanceof Agent ? $commission->agent : null;
        $agency = $commission->agency instanceof Agency ? $commission->agency : null;

        return self::referrersForParticipants($agent, $agency);
    }

    /**
     * @return list<Agency|Agent>
     */
    public static function referrersForParticipants(?Agent $agent, ?Agency $agency): array
    {
        foreach ([$agent, $agency] as $participant) {
            if (! $participant instanceof Agency && ! $participant instanceof Agent) {
                continue;
            }

            $assigned = ReferidorAssignmentService::assignedReferrers($participant);

            if ($assigned === []) {
                continue;
            }

            return array_values(array_filter(
                $assigned,
                fn (Agency|Agent $referrer): bool => self::isActiveReferrer($referrer),
            ));
        }

        return [];
    }

    /**
     * @param  list<Agency|Agent>  $referrers
     */
    public static function totalPercentage(array $referrers): float
    {
        $total = 0.0;

        foreach ($referrers as $referrer) {
            $total += self::percentageOf($referrer);
        }

        $total = round($total, 2);

        if ($total < 0) {
            return 0.0;
        }

        if ($total > 100) {
            return 100.0;
        }

        return $total;
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
            'agency.referrerAgencies',
            'agency.referrerAgents',
            'agency.referidor',
            'agency.referidorAgent',
            'agent.referrerAgencies',
            'agent.referrerAgents',
            'agent.referidor',
            'agent.referidorAgent',
        ];
    }

    private static function hasAssignedValue(mixed $value): bool
    {
        return $value !== null && $value !== '';
    }
}
