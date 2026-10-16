<?php

declare(strict_types=1);

namespace App\Support\Administration\Reports;

use App\Models\Agency;
use App\Models\Agent;
use Illuminate\Support\Str;

/**
 * Red comercial de una agencia o de un agente para el reporte de cobranza.
 *
 * Una agencia trae su estructura completa: las agencias que cuelgan de ella por
 * `agencies.owner_code` a cualquier profundidad (master → master → general →
 * general…), los agentes de todas ellas y los subagentes de esos agentes
 * (`agents.owner_agent`). Recorre en memoria: son tablas chicas (decenas de
 * agencias, cientos de agentes) y así se toleran ciclos y códigos con espacios
 * o minúsculas sin una consulta por nivel.
 */
final class CollectionReportNetwork
{
    public const AGENCY_TYPE_MASTER = 1;

    public const AGENCY_TYPE_GENERAL = 3;

    public const AGENT_TYPE_SUBAGENT = 3;

    /**
     * @param  list<string>  $agencyCodes  Códigos tal como están guardados en `agencies.code`.
     * @param  list<int>  $agentIds
     */
    public function __construct(
        public array $agencyCodes,
        public array $agentIds,
    ) {}

    public static function forAgency(string $agencyCode): self
    {
        $root = self::normalize($agencyCode);

        if ($root === '') {
            return new self([], []);
        }

        $agencies = Agency::query()->get(['code', 'owner_code']);
        $childrenByOwner = [];
        $storedCodes = [];

        foreach ($agencies as $agency) {
            $code = self::normalize($agency->code);

            if ($code === '') {
                continue;
            }

            $storedCodes[$code] = trim((string) $agency->code);
            $owner = self::normalize($agency->owner_code);

            if ($owner !== '' && $owner !== $code) {
                $childrenByOwner[$owner][] = $code;
            }
        }

        $seen = [$root => true];
        $frontier = [$root];

        while ($frontier !== []) {
            $next = [];

            foreach ($frontier as $code) {
                foreach ($childrenByOwner[$code] ?? [] as $child) {
                    if (! isset($seen[$child])) {
                        $seen[$child] = true;
                        $next[] = $child;
                    }
                }
            }

            $frontier = $next;
        }

        $codes = array_map(
            static fn (string $code): string => $storedCodes[$code] ?? $code,
            array_keys($seen),
        );

        return new self($codes, self::agentIdsUnderAgencies(array_keys($seen)));
    }

    public static function forAgent(int $agentId, bool $includeSubagents = true): self
    {
        if ($agentId <= 0) {
            return new self([], []);
        }

        if (! $includeSubagents) {
            return new self([], [$agentId]);
        }

        $agents = Agent::query()->get(['id', 'owner_agent']);

        return new self([], self::withSubagents([$agentId], $agents->all()));
    }

    /**
     * Cantidad de agencias y agentes de la red, para el resumen de la pantalla.
     *
     * @return array{agencies: int, agents: int}
     */
    public function size(): array
    {
        return ['agencies' => count($this->agencyCodes), 'agents' => count($this->agentIds)];
    }

    public function isEmpty(): bool
    {
        return $this->agencyCodes === [] && $this->agentIds === [];
    }

    /**
     * @param  list<string>  $normalizedCodes
     * @return list<int>
     */
    private static function agentIdsUnderAgencies(array $normalizedCodes): array
    {
        $codes = array_flip($normalizedCodes);
        $agents = Agent::query()->get(['id', 'owner_code', 'code_agency', 'owner_agent']);

        $direct = [];

        foreach ($agents as $agent) {
            if (isset($codes[self::normalize($agent->owner_code)]) || isset($codes[self::normalize($agent->code_agency)])) {
                $direct[] = (int) $agent->id;
            }
        }

        return self::withSubagents($direct, $agents->all());
    }

    /**
     * Suma a los agentes dados sus subagentes, a cualquier profundidad.
     *
     * @param  list<int>  $agentIds
     * @param  list<Agent>  $agents
     * @return list<int>
     */
    private static function withSubagents(array $agentIds, array $agents): array
    {
        $childrenByOwner = [];

        foreach ($agents as $agent) {
            $owner = (int) ($agent->owner_agent ?? 0);

            if ($owner > 0 && $owner !== (int) $agent->id) {
                $childrenByOwner[$owner][] = (int) $agent->id;
            }
        }

        $seen = array_fill_keys($agentIds, true);
        $frontier = $agentIds;

        while ($frontier !== []) {
            $next = [];

            foreach ($frontier as $id) {
                foreach ($childrenByOwner[$id] ?? [] as $child) {
                    if (! isset($seen[$child])) {
                        $seen[$child] = true;
                        $next[] = $child;
                    }
                }
            }

            $frontier = $next;
        }

        $ids = array_map('intval', array_keys($seen));
        sort($ids);

        return $ids;
    }

    private static function normalize(mixed $code): string
    {
        return Str::upper(trim((string) ($code ?? '')));
    }
}
