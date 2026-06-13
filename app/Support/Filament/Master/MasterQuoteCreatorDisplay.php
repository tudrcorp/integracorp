<?php

declare(strict_types=1);

namespace App\Support\Filament\Master;

use App\Models\Agency;
use App\Models\Agent;
use Illuminate\Support\Collection;

class MasterQuoteCreatorDisplay
{
    /** @var array<int, Agent|null> */
    private static array $agentsByIdCache = [];

    private static ?Collection $agenciesByCodeCache = null;

    public static function agencyTypePrefix(?string $codeAgency): string
    {
        if (blank($codeAgency)) {
            return 'MASTER - ';
        }

        $agency = self::agenciesByCode()->get($codeAgency);
        $definition = $agency?->typeAgency?->definition;

        return $definition ? $definition.' - ' : 'MASTER - ';
    }

    public static function agentName(?int $agentId): string
    {
        $agent = self::resolveAgent($agentId);

        if ($agent === null) {
            return '—';
        }

        if ((int) $agent->agent_type_id === 3 && filled($agent->owner_agent)) {
            return self::resolveAgent((int) $agent->owner_agent)?->name ?? '—';
        }

        return $agent->name;
    }

    public static function subAgentName(?int $agentId): string
    {
        $agent = self::resolveAgent($agentId);

        if ($agent === null || (int) $agent->agent_type_id !== 3) {
            return '—';
        }

        return $agent->name;
    }

    public static function isSubAgent(?int $agentId): bool
    {
        $agent = self::resolveAgent($agentId);

        return $agent !== null && (int) $agent->agent_type_id === 3;
    }

    /**
     * @return Collection<string, Agency>
     */
    private static function agenciesByCode(): Collection
    {
        if (self::$agenciesByCodeCache === null) {
            self::$agenciesByCodeCache = Agency::query()
                ->with('typeAgency')
                ->get()
                ->keyBy('code');
        }

        return self::$agenciesByCodeCache;
    }

    private static function resolveAgent(?int $agentId): ?Agent
    {
        if ($agentId === null) {
            return null;
        }

        if (! array_key_exists($agentId, self::$agentsByIdCache)) {
            self::$agentsByIdCache[$agentId] = Agent::query()
                ->select(['id', 'name', 'agent_type_id', 'owner_agent'])
                ->find($agentId);
        }

        return self::$agentsByIdCache[$agentId];
    }
}
