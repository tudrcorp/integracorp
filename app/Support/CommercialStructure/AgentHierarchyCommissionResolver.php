<?php

declare(strict_types=1);

namespace App\Support\CommercialStructure;

use App\Models\Agency;
use App\Models\Agent;

final class AgentHierarchyCommissionResolver
{
    private const AGENCY_COMMISSION_COLUMNS = [
        'code',
        'name_corporative',
        'agency_type_id',
        'owner_code',
        'status',
        'commission_tdec',
        'commission_tdec_renewal',
        'commission_tdev',
        'commission_tdev_renewal',
    ];

    private const AGENT_COMMISSION_COLUMNS = [
        'id',
        'name',
        'code_agent',
        'status',
        'owner_code',
        'owner_agent',
        'agent_type_id',
        'commission_tdec',
        'commission_tdec_renewal',
        'commission_tdev',
        'commission_tdev_renewal',
    ];

    /**
     * @return array{nodes: list<array<string, mixed>>, warnings: list<string>}
     */
    public static function resolve(Agent $agent): array
    {
        $nodes = [];
        $warnings = [];

        $agentTypeId = (int) ($agent->agent_type_id ?? 0);
        $agentTypeLabel = $agentTypeId === 3 ? 'Subagente' : 'Agente';

        $nodes[] = self::nodeFromAgent($agent, 'Agente actual');

        $superiorAgent = null;

        if ($agentTypeId === 3) {
            if (filled($agent->owner_agent)) {
                $superiorAgent = Agent::query()
                    ->select(self::AGENT_COMMISSION_COLUMNS)
                    ->find((int) $agent->owner_agent);

                if ($superiorAgent instanceof Agent) {
                    $nodes[] = self::nodeFromAgent($superiorAgent, 'Agente superior');
                } else {
                    $warnings[] = 'El subagente no tiene un agente superior válido configurado en owner_agent.';
                }
            } else {
                $warnings[] = 'El subagente no tiene owner_agent configurado.';
            }
        }

        $agencyCode = trim((string) ($superiorAgent?->owner_code ?? $agent->owner_code ?? ''));
        $linkedAgency = null;
        $masterAgency = null;

        if ($agencyCode !== '') {
            $linkedAgency = Agency::query()
                ->select(self::AGENCY_COMMISSION_COLUMNS)
                ->where('code', $agencyCode)
                ->first();

            if ($linkedAgency instanceof Agency) {
                $linkedAgencyTypeId = (int) ($linkedAgency->agency_type_id ?? 0);
                $linkedAgencyRole = $linkedAgencyTypeId === 1 ? 'Agencia master' : 'Agencia general';

                $nodes[] = self::nodeFromAgency($linkedAgency, $linkedAgencyRole);

                if ($linkedAgencyTypeId === 1) {
                    $masterAgency = $linkedAgency;
                } else {
                    $parentMasterCode = trim((string) ($linkedAgency->owner_code ?? ''));

                    if ($parentMasterCode !== '' && $parentMasterCode !== (string) ($linkedAgency->code ?? '')) {
                        $masterAgency = Agency::query()
                            ->select(self::AGENCY_COMMISSION_COLUMNS)
                            ->where('code', $parentMasterCode)
                            ->where('agency_type_id', 1)
                            ->first();

                        if ($masterAgency instanceof Agency) {
                            $nodes[] = self::nodeFromAgency($masterAgency, 'Agencia master');
                        } else {
                            $warnings[] = 'La agencia general no tiene una agencia master válida en owner_code.';
                        }
                    } else {
                        $warnings[] = 'La agencia general no tiene owner_code hacia una agencia master.';
                    }
                }
            } else {
                $warnings[] = 'No se encontró la agencia relacionada usando el código owner_code.';
            }
        } else {
            $warnings[] = $agentTypeLabel === 'Subagente'
                ? 'No se pudo resolver la agencia del subagente por owner_code.'
                : 'El agente no tiene owner_code configurado para identificar su agencia.';
        }

        $orderedNodes = array_reverse($nodes);

        $casaMatriz = self::resolveCasaMatrizNode($masterAgency);

        if ($casaMatriz !== null) {
            array_unshift($orderedNodes, $casaMatriz);
        }

        return [
            'nodes' => $orderedNodes,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    public static function formatLinearChain(array $nodes): string
    {
        if ($nodes === []) {
            return '';
        }

        $segments = [];

        foreach ($nodes as $node) {
            $code = trim((string) ($node['code'] ?? ''));
            $name = trim((string) ($node['name'] ?? ''));
            $role = trim((string) ($node['role'] ?? ''));

            $segment = $name;

            if ($code !== '') {
                $segment .= ' ('.$code.')';
            }

            if ($role !== '') {
                $segment = $role.': '.$segment;
            }

            $segments[] = $segment;
        }

        return implode(' → ', $segments);
    }

    /**
     * @return array<string, mixed>
     */
    private static function nodeFromAgent(Agent $agent, string $role): array
    {
        return [
            'role' => $role,
            'entity_type' => 'agente',
            'code' => self::formatAgentRegistrationCode($agent),
            'name' => (string) ($agent->name ?? 'Sin nombre'),
            'status' => (string) ($agent->status ?? ''),
            'commission_tdec' => $agent->commission_tdec,
            'commission_tdec_renewal' => $agent->commission_tdec_renewal,
            'commission_tdev' => $agent->commission_tdev,
            'commission_tdev_renewal' => $agent->commission_tdev_renewal,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function nodeFromAgency(Agency $agency, string $role): array
    {
        return [
            'role' => $role,
            'entity_type' => 'agencia',
            'code' => trim((string) ($agency->code ?? '')),
            'name' => self::resolveAgencyDisplayName($agency),
            'status' => (string) ($agency->status ?? ''),
            'commission_tdec' => $agency->commission_tdec,
            'commission_tdec_renewal' => $agency->commission_tdec_renewal,
            'commission_tdev' => $agency->commission_tdev,
            'commission_tdev_renewal' => $agency->commission_tdev_renewal,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function resolveCasaMatrizNode(?Agency $masterAgency): ?array
    {
        if (! $masterAgency instanceof Agency) {
            return null;
        }

        $masterCode = strtoupper(trim((string) ($masterAgency->code ?? '')));
        $ownerCode = strtoupper(trim((string) ($masterAgency->owner_code ?? '')));

        if ($ownerCode !== 'TDG-100' || $masterCode === 'TDG-100') {
            return null;
        }

        $casaMatriz = Agency::query()
            ->select(self::AGENCY_COMMISSION_COLUMNS)
            ->where('code', 'TDG-100')
            ->first();

        if (! $casaMatriz instanceof Agency) {
            return null;
        }

        return self::nodeFromAgency($casaMatriz, 'Casa matriz');
    }

    private static function resolveAgencyDisplayName(Agency $agency): string
    {
        $agencyCode = strtoupper(trim((string) ($agency->code ?? '')));

        if ($agencyCode === 'TDG-100') {
            return 'TUDRENCASA';
        }

        return (string) ($agency->name_corporative ?? 'Sin razón social');
    }

    private static function formatAgentRegistrationCode(Agent $agent): string
    {
        $codeAgent = trim((string) ($agent->code_agent ?? ''));

        if ($codeAgent !== '') {
            return $codeAgent;
        }

        $agentId = (int) ($agent->id ?? 0);

        if ($agentId <= 0) {
            return 'Sin código';
        }

        return 'AGT-000'.$agentId;
    }
}
