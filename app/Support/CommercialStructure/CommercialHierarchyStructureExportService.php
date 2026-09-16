<?php

declare(strict_types=1);

namespace App\Support\CommercialStructure;

use App\Filament\Shared\CommercialStructure\CommercialHierarchyFlowchart;
use App\Models\Agency;
use App\Models\Agent;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class CommercialHierarchyStructureExportService
{
    private const AGENCY_TYPE_MASTER = 1;

    private const AGENCY_TYPE_GENERAL = 3;

    private const AGENT_TYPE_SUBAGENT = 3;

    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            '#',
            'Tipo',
            'Código',
            'Nombre',
            'Estatus',
            'Correo',
            'Teléfono',
            'Código agencia estructura',
            'Nombre agencia estructura',
            'Código agente superior',
            'Nombre agente superior',
            'Cadena jerárquica',
        ];
    }

    public static function toXlsxForAgency(Agency $agency): BinaryFileResponse
    {
        $code = trim((string) ($agency->code ?? 'agencia'));
        $slug = self::slugify($code !== '' ? $code : 'agencia');

        return self::downloadXlsx(
            rows: self::rowsForAgency($agency),
            filename: 'estructura_comercial_'.$slug.'_'.now()->format('Y-m-d_His').'.xlsx',
        );
    }

    public static function toXlsxForAgent(Agent $agent): BinaryFileResponse
    {
        $code = self::formatAgentCode($agent);
        $slug = self::slugify($code !== '' ? $code : 'agente');

        return self::downloadXlsx(
            rows: self::rowsForAgent($agent),
            filename: 'estructura_comercial_'.$slug.'_'.now()->format('Y-m-d_His').'.xlsx',
        );
    }

    /**
     * @return list<array<int, string|int>>
     */
    public static function rowsForAgency(Agency $agency): array
    {
        $rows = [self::headers()];
        $order = 1;
        $agencyTypeId = (int) ($agency->agency_type_id ?? 0);
        $agencyCode = trim((string) ($agency->code ?? ''));
        $agencyName = self::agencyDisplayName($agency);

        if ($agencyTypeId === self::AGENCY_TYPE_MASTER) {
            $chainPrefix = ['Agencia master · '.$agencyCode];

            $rows[] = self::agencyRow(
                order: $order++,
                role: 'Agencia master',
                agency: $agency,
                structureAgencyCode: $agencyCode,
                structureAgencyName: $agencyName,
                chain: implode(' → ', $chainPrefix),
            );

            foreach (self::agentBranchesForAgencyCode($agencyCode) as $branch) {
                $order = self::appendAgentBranchRows(
                    rows: $rows,
                    order: $order,
                    branch: $branch,
                    structureAgencyCode: $agencyCode,
                    structureAgencyName: $agencyName,
                    chainPrefix: $chainPrefix,
                );
            }

            foreach (self::generalAgenciesUnderMaster($agencyCode) as $general) {
                $generalCode = trim((string) ($general->code ?? ''));
                $generalName = self::agencyDisplayName($general);
                $generalChain = array_merge($chainPrefix, ['Agencia general · '.$generalCode]);

                $rows[] = self::agencyRow(
                    order: $order++,
                    role: 'Agencia general',
                    agency: $general,
                    structureAgencyCode: $agencyCode,
                    structureAgencyName: $agencyName,
                    chain: implode(' → ', $generalChain),
                );

                foreach (self::agentBranchesForAgencyCode($generalCode) as $branch) {
                    $order = self::appendAgentBranchRows(
                        rows: $rows,
                        order: $order,
                        branch: $branch,
                        structureAgencyCode: $generalCode,
                        structureAgencyName: $generalName,
                        chainPrefix: $generalChain,
                    );
                }
            }

            return $rows;
        }

        $role = $agencyTypeId === self::AGENCY_TYPE_GENERAL ? 'Agencia general' : 'Agencia';
        $chainPrefix = [$role.' · '.$agencyCode];

        $rows[] = self::agencyRow(
            order: $order++,
            role: $role,
            agency: $agency,
            structureAgencyCode: $agencyCode,
            structureAgencyName: $agencyName,
            chain: implode(' → ', $chainPrefix),
        );

        foreach (self::agentBranchesForAgencyCode($agencyCode) as $branch) {
            $order = self::appendAgentBranchRows(
                rows: $rows,
                order: $order,
                branch: $branch,
                structureAgencyCode: $agencyCode,
                structureAgencyName: $agencyName,
                chainPrefix: $chainPrefix,
            );
        }

        return $rows;
    }

    /**
     * @return list<array<int, string|int>>
     */
    public static function rowsForAgent(Agent $agent): array
    {
        $rows = [self::headers()];
        $order = 1;

        $structureAgencyCode = trim((string) ($agent->owner_code ?? $agent->code_agency ?? ''));
        $structureAgency = $structureAgencyCode !== ''
            ? Agency::query()
                ->select(['code', 'name_corporative', 'agency_type_id', 'status', 'email', 'phone', 'owner_code'])
                ->whereRaw('UPPER(TRIM(code)) = ?', [strtoupper($structureAgencyCode)])
                ->first()
            : null;
        $structureAgencyName = $structureAgency instanceof Agency
            ? self::agencyDisplayName($structureAgency)
            : '';

        $agentTypeId = (int) ($agent->agent_type_id ?? 0);
        $role = $agentTypeId === self::AGENT_TYPE_SUBAGENT ? 'Subagente' : 'Agente';
        $chainPrefix = [];

        if ($structureAgencyCode !== '') {
            $chainPrefix[] = 'Agencia · '.$structureAgencyCode;
        }

        $superior = null;

        if ($agentTypeId === self::AGENT_TYPE_SUBAGENT && filled($agent->owner_agent)) {
            $superior = Agent::query()
                ->select(['id', 'name', 'email', 'phone', 'status', 'agent_type_id', 'owner_agent', 'owner_code', 'code_agency', 'code_agent'])
                ->find((int) $agent->owner_agent);

            if ($superior instanceof Agent) {
                $chainPrefix[] = 'Agente · '.self::formatAgentCode($superior);
            }
        }

        $chainPrefix[] = $role.' · '.self::formatAgentCode($agent);

        $rows[] = self::agentRow(
            order: $order++,
            role: $role,
            agent: $agent,
            structureAgencyCode: $structureAgencyCode,
            structureAgencyName: $structureAgencyName,
            superiorAgent: $superior,
            chain: implode(' → ', $chainPrefix),
        );

        $subagents = CommercialHierarchyFlowchart::agentsUnderAgentQuery($agent->id)
            ->select(['id', 'name', 'email', 'phone', 'status', 'agent_type_id', 'owner_agent', 'owner_code', 'code_agency', 'code_agent'])
            ->orderBy('name')
            ->get();

        foreach ($subagents as $subagent) {
            $subChain = array_merge($chainPrefix, ['Subagente · '.self::formatAgentCode($subagent)]);

            $rows[] = self::agentRow(
                order: $order++,
                role: 'Subagente',
                agent: $subagent,
                structureAgencyCode: $structureAgencyCode,
                structureAgencyName: $structureAgencyName,
                superiorAgent: $agent,
                chain: implode(' → ', $subChain),
            );
        }

        return $rows;
    }

    /**
     * @param  list<array<int, string|int>>  $rows
     */
    private static function downloadXlsx(array $rows, string $filename): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'hierarchy_structure_');

        if ($path === false) {
            abort(500, 'No se pudo preparar el archivo temporal.');
        }

        $path .= '.xlsx';

        $writer = new Writer;
        $writer->openToFile($path);

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(array_map(
                static fn (mixed $value): string|int|float|null => is_scalar($value) || $value === null
                    ? $value
                    : (string) $value,
                $row,
            )));
        }

        $writer->close();

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * @return Collection<int, Agency>
     */
    private static function generalAgenciesUnderMaster(string $masterCode): Collection
    {
        $normalized = strtoupper(trim($masterCode));

        if ($normalized === '') {
            return collect();
        }

        return Agency::query()
            ->select(['code', 'name_corporative', 'agency_type_id', 'status', 'email', 'phone', 'owner_code'])
            ->where('agency_type_id', self::AGENCY_TYPE_GENERAL)
            ->whereRaw('UPPER(TRIM(owner_code)) = ?', [$normalized])
            ->orderBy('code')
            ->get();
    }

    /**
     * @return list<array{agent: Agent, subagents: list<Agent>}>
     */
    private static function agentBranchesForAgencyCode(string $agencyCode): array
    {
        if (trim($agencyCode) === '') {
            return [];
        }

        $agents = CommercialHierarchyFlowchart::agentsUnderGeneralAgencyQuery($agencyCode)
            ->select(['id', 'name', 'email', 'phone', 'status', 'agent_type_id', 'owner_agent', 'owner_code', 'code_agency', 'code_agent'])
            ->orderBy('agent_type_id')
            ->orderBy('name')
            ->get();

        $subagentsByOwner = [];
        $directAgents = [];

        foreach ($agents as $agent) {
            if ((int) ($agent->agent_type_id ?? 0) === self::AGENT_TYPE_SUBAGENT) {
                $ownerId = (int) ($agent->owner_agent ?? 0);

                if ($ownerId > 0) {
                    $subagentsByOwner[$ownerId][] = $agent;
                }

                continue;
            }

            $directAgents[] = $agent;
        }

        $branches = [];

        foreach ($directAgents as $agent) {
            $agentId = (int) ($agent->id ?? 0);

            $branches[] = [
                'agent' => $agent,
                'subagents' => $subagentsByOwner[$agentId] ?? [],
            ];
        }

        return $branches;
    }

    /**
     * @param  list<array<int, string|int>>  $rows
     * @param  array{agent: Agent, subagents: list<Agent>}  $branch
     * @param  list<string>  $chainPrefix
     */
    private static function appendAgentBranchRows(
        array &$rows,
        int $order,
        array $branch,
        string $structureAgencyCode,
        string $structureAgencyName,
        array $chainPrefix,
    ): int {
        $agent = $branch['agent'];
        $agentChain = array_merge($chainPrefix, ['Agente · '.self::formatAgentCode($agent)]);

        $rows[] = self::agentRow(
            order: $order++,
            role: 'Agente',
            agent: $agent,
            structureAgencyCode: $structureAgencyCode,
            structureAgencyName: $structureAgencyName,
            superiorAgent: null,
            chain: implode(' → ', $agentChain),
        );

        foreach ($branch['subagents'] as $subagent) {
            $subChain = array_merge($agentChain, ['Subagente · '.self::formatAgentCode($subagent)]);

            $rows[] = self::agentRow(
                order: $order++,
                role: 'Subagente',
                agent: $subagent,
                structureAgencyCode: $structureAgencyCode,
                structureAgencyName: $structureAgencyName,
                superiorAgent: $agent,
                chain: implode(' → ', $subChain),
            );
        }

        return $order;
    }

    /**
     * @return array<int, string|int>
     */
    private static function agencyRow(
        int $order,
        string $role,
        Agency $agency,
        string $structureAgencyCode,
        string $structureAgencyName,
        string $chain,
    ): array {
        return [
            $order,
            $role,
            trim((string) ($agency->code ?? '')),
            self::agencyDisplayName($agency),
            (string) ($agency->status ?? ''),
            (string) ($agency->email ?? ''),
            (string) ($agency->phone ?? ''),
            $structureAgencyCode,
            $structureAgencyName,
            '',
            '',
            $chain,
        ];
    }

    /**
     * @return array<int, string|int>
     */
    private static function agentRow(
        int $order,
        string $role,
        Agent $agent,
        string $structureAgencyCode,
        string $structureAgencyName,
        ?Agent $superiorAgent,
        string $chain,
    ): array {
        return [
            $order,
            $role,
            self::formatAgentCode($agent),
            mb_strtoupper(trim((string) ($agent->name ?? 'Sin nombre'))),
            (string) ($agent->status ?? ''),
            (string) ($agent->email ?? ''),
            (string) ($agent->phone ?? ''),
            $structureAgencyCode,
            $structureAgencyName,
            $superiorAgent instanceof Agent ? self::formatAgentCode($superiorAgent) : '',
            $superiorAgent instanceof Agent ? mb_strtoupper(trim((string) ($superiorAgent->name ?? ''))) : '',
            $chain,
        ];
    }

    private static function agencyDisplayName(Agency $agency): string
    {
        $code = strtoupper(trim((string) ($agency->code ?? '')));

        if ($code === 'TDG-100') {
            return 'TUDRENCASA';
        }

        return (string) ($agency->name_corporative ?? 'Sin razón social');
    }

    private static function formatAgentCode(Agent $agent): string
    {
        $codeAgent = trim((string) ($agent->code_agent ?? ''));

        if ($codeAgent !== '') {
            return $codeAgent;
        }

        $agentId = (int) ($agent->id ?? 0);

        return $agentId > 0 ? 'AGT-000'.$agentId : 'Sin código';
    }

    private static function slugify(string $value): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9\-]+/', '-', $value) ?? ''));

        return trim($slug, '-') !== '' ? trim($slug, '-') : 'estructura';
    }
}
