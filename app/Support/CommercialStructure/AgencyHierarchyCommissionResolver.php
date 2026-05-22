<?php

declare(strict_types=1);

namespace App\Support\CommercialStructure;

use App\Models\Agency;
use App\Models\Agent;

final class AgencyHierarchyCommissionResolver
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
    public static function resolve(Agency $agency): array
    {
        $nodes = [];
        $warnings = [];
        $structureTargets = [];

        $agencyTypeId = (int) ($agency->agency_type_id ?? 0);
        $agencyRole = $agencyTypeId === 1 ? 'Agencia master' : 'Agencia general';
        $currentAgencyCode = trim((string) ($agency->code ?? ''));

        $nodes[] = self::nodeFromAgency($agency, $agencyRole);

        if ($currentAgencyCode !== '') {
            $structureTargets[$currentAgencyCode] = self::resolveAgencyDisplayName($agency);
        }

        if ($agencyTypeId === 1) {
            $ownerCode = strtoupper(trim((string) ($agency->owner_code ?? '')));

            if ($ownerCode === 'TDG-100' && strtoupper(trim((string) ($agency->code ?? ''))) !== 'TDG-100') {
                $casaMatriz = Agency::query()
                    ->select(self::AGENCY_COMMISSION_COLUMNS)
                    ->where('code', 'TDG-100')
                    ->first();

                if ($casaMatriz instanceof Agency) {
                    array_unshift($nodes, self::nodeFromAgency($casaMatriz, 'Casa matriz'));
                    $structureTargets['TDG-100'] = 'TUDRENCASA';
                }
            } elseif ($ownerCode !== '' && $ownerCode !== 'TDG-100') {
                $warnings[] = 'La agencia master tiene owner_code distinto a TDG-100.';
            }
        } else {
            $ownerCode = trim((string) ($agency->owner_code ?? ''));

            if ($ownerCode === '') {
                $warnings[] = 'La agencia general no tiene owner_code configurado.';
            } else {
                $masterAgency = Agency::query()
                    ->select(self::AGENCY_COMMISSION_COLUMNS)
                    ->where('code', $ownerCode)
                    ->where('agency_type_id', 1)
                    ->first();

                if ($masterAgency instanceof Agency) {
                    $nodes[] = self::nodeFromAgency($masterAgency, 'Agencia master');
                    $masterCode = trim((string) ($masterAgency->code ?? ''));

                    if ($masterCode !== '') {
                        $structureTargets[$masterCode] = self::resolveAgencyDisplayName($masterAgency);
                    }

                    $masterOwnerCode = strtoupper(trim((string) ($masterAgency->owner_code ?? '')));
                    $masterAgencyCode = strtoupper(trim((string) ($masterAgency->code ?? '')));

                    if ($masterOwnerCode === 'TDG-100' && $masterAgencyCode !== 'TDG-100' && ! isset($structureTargets['TDG-100'])) {
                        $casaMatriz = Agency::query()
                            ->select(self::AGENCY_COMMISSION_COLUMNS)
                            ->where('code', 'TDG-100')
                            ->first();

                        if ($casaMatriz instanceof Agency) {
                            array_unshift($nodes, self::nodeFromAgency($casaMatriz, 'Casa matriz'));
                            $structureTargets['TDG-100'] = 'TUDRENCASA';
                        }
                    }
                } else {
                    $warnings[] = 'No se encontró agencia master válida usando owner_code de esta agencia general.';
                }
            }
        }

        $orderedNodes = self::deduplicateNodesByCode($nodes);

        foreach ($structureTargets as $agencyCode => $agencyName) {
            $agents = Agent::query()
                ->select(self::AGENT_COMMISSION_COLUMNS)
                ->where('owner_code', $agencyCode)
                ->orderBy('agent_type_id')
                ->orderBy('name')
                ->get();

            foreach ($agents as $agent) {
                $agentTypeId = (int) ($agent->agent_type_id ?? 0);
                $role = $agentTypeId === 3 ? 'Subagente' : 'Agente principal';

                $orderedNodes[] = array_merge(
                    self::nodeFromAgent($agent, $role),
                    [
                        'structure_agency_code' => $agencyCode,
                        'structure_agency_name' => $agencyName,
                    ],
                );
            }
        }

        return [
            'nodes' => $orderedNodes,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private static function deduplicateNodesByCode(array $nodes): array
    {
        $seen = [];
        $unique = [];

        foreach ($nodes as $node) {
            $entityType = (string) ($node['entity_type'] ?? '');
            $code = trim((string) ($node['code'] ?? ''));

            if ($entityType === 'agencia' && $code !== '') {
                $key = 'agencia:'.$code;

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
            }

            $unique[] = $node;
        }

        return $unique;
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
