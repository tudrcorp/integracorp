<?php

declare(strict_types=1);

namespace App\Filament\Shared\CommercialStructure;

use App\Models\Agency;
use App\Models\Agent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\HtmlString;

class CommercialHierarchyFlowchart
{
    public const VIEWER_FULL = 'full';

    public const VIEWER_MASTER = 'master';

    public const VIEWER_GENERAL = 'general';

    public const VIEWER_AGENT = 'agent';

    private const AGENCY_TYPE_MASTER = 1;

    private const AGENCY_TYPE_GENERAL = 3;

    private const SLIDER_THRESHOLD = 5;

    private static int $sliderSequence = 0;

    public static function renderForAgency(Agency $agency): HtmlString
    {
        $tree = self::buildInteractiveHierarchyTree($agency);

        return self::renderDiagramShell($tree, self::resolveInitialExpandState($tree));
    }

    public static function commercialCodeSequenceForAgent(Agent $agent, string $viewerContext = self::VIEWER_FULL): string
    {
        $agentTypeId = (int) ($agent->agent_type_id ?? 0);
        $segments = self::commercialAgencySegmentsForAgent($agent);

        if ($agentTypeId === 3 && filled($agent->owner_agent)) {
            $segments[] = 'AGENTE · AGT-000'.(int) $agent->owner_agent;
            $segments[] = 'SUB AGENTE · AGT-000'.(int) $agent->id;
        } else {
            $segments[] = 'AGENTE · AGT-000'.(int) $agent->id;
        }

        $segments = self::filterCommercialCodeSegmentsForViewer($segments, $viewerContext);

        return implode(' → ', $segments);
    }

    /**
     * Agentes y subagentes bajo una agencia general (code_agency o owner_code legacy).
     *
     * @return Builder<Agent>
     */
    public static function agentsUnderGeneralAgencyQuery(string $generalAgencyCode): Builder
    {
        $normalizedCodes = self::resolveAgentOwnerCodesForAgencyCode($generalAgencyCode);

        if ($normalizedCodes === []) {
            return Agent::query()->whereRaw('0 = 1');
        }

        return Agent::query()->where(function (Builder $query) use ($normalizedCodes): void {
            foreach ($normalizedCodes as $code) {
                $normalizedCode = strtoupper(trim($code));

                $query->orWhereRaw('UPPER(TRIM(code_agency)) = ?', [$normalizedCode]);
                $query->orWhereRaw('UPPER(TRIM(owner_code)) = ?', [$normalizedCode]);
            }

            $query->orWhereIn('owner_agent', function (\Illuminate\Database\Query\Builder $subquery) use ($normalizedCodes): void {
                $subquery->select('id')
                    ->from('agents')
                    ->where('agent_type_id', 2)
                    ->where(function (\Illuminate\Database\Query\Builder $parentQuery) use ($normalizedCodes): void {
                        foreach ($normalizedCodes as $code) {
                            $normalizedCode = strtoupper(trim($code));

                            $parentQuery->orWhereRaw('UPPER(TRIM(code_agency)) = ?', [$normalizedCode]);
                            $parentQuery->orWhereRaw('UPPER(TRIM(owner_code)) = ?', [$normalizedCode]);
                        }
                    });
            });
        });
    }

    /**
     * Subagentes bajo un agente responsable (owner_agent).
     *
     * @return Builder<Agent>
     */
    public static function agentsUnderAgentQuery(int|string|null $agentId): Builder
    {
        $normalizedAgentId = (int) $agentId;

        if ($normalizedAgentId <= 0) {
            return Agent::query()->whereRaw('0 = 1');
        }

        return Agent::query()
            ->where('owner_agent', $normalizedAgentId)
            ->where('agent_type_id', 3);
    }

    public static function renderForAgent(Agent $agent): HtmlString
    {
        $superiorAgent = self::resolveSuperiorAgentForHierarchy($agent);
        $agencyCode = trim((string) ($superiorAgent?->owner_code ?? $agent->owner_code ?? ''));
        $highlightAgentId = (int) ($agent->id ?? 0);
        $highlightAgentId = $highlightAgentId > 0 ? $highlightAgentId : null;

        $linkedAgency = $agencyCode !== '' ? self::resolveAgencyByOwnerCode($agencyCode) : null;

        if (! $linkedAgency instanceof Agency) {
            if (strtoupper($agencyCode) === 'TDG-100') {
                $tree = self::buildInteractiveHierarchyTreeForHeadquarters($highlightAgentId);

                return self::renderDiagramShell(
                    $tree,
                    self::resolveInitialExpandState($tree, $highlightAgentId),
                    $highlightAgentId,
                );
            }

            return self::renderDiagramShell([]);
        }

        $tree = self::buildInteractiveHierarchyTree($linkedAgency, $highlightAgentId);

        return self::renderDiagramShell(
            $tree,
            self::resolveInitialExpandState($tree, $highlightAgentId),
            $highlightAgentId,
        );
    }

    /**
     * @param  array{
     *     headquarters?: array<string, mixed>|null,
     *     master?: array<string, mixed>|null,
     *     master_direct_agents?: list<array{agent: array<string, mixed>, subagents: list<array<string, mixed>>}>,
     *     generals?: list<array{agency: array<string, mixed>, agents: list<array{agent: array<string, mixed>, subagents: list<array<string, mixed>>}>}>
     * }  $tree
     * @param  array{masterAgentsOpen?: bool, activeGeneralBranch?: string|null, activeSubagentBranch?: string|null}  $initialExpandState
     */
    private static function renderDiagramShell(array $tree, array $initialExpandState = [], ?int $highlightAgentId = null): HtmlString
    {
        $diagram = '<div class="tdg-hierarchy-flowchart-shell">'
            .'<div class="tdg-hierarchy-flowchart-shell__header">'
            .'<div class="tdg-hierarchy-flowchart-shell__header-text">'
            .'<p class="tdg-hierarchy-flowchart-shell__eyebrow">Estructura comercial</p>'
            .'<h3 class="tdg-hierarchy-flowchart-shell__title">Jerarquía interactiva</h3>'
            .'<p class="tdg-hierarchy-flowchart-shell__subtitle">Master → General → Agente → Subagente · Despliega equipos directos o desliza cuando hay más de cinco nodos.</p>'
            .'</div>'
            .'<div class="tdg-hierarchy-flowchart-shell__legend" aria-hidden="true">'
            .'<span class="tdg-hierarchy-flowchart-shell__legend-item tdg-hierarchy-flowchart-shell__legend-item--master">Master</span>'
            .'<span class="tdg-hierarchy-flowchart-shell__legend-item tdg-hierarchy-flowchart-shell__legend-item--general">General</span>'
            .'<span class="tdg-hierarchy-flowchart-shell__legend-item tdg-hierarchy-flowchart-shell__legend-item--agent">Agente</span>'
            .'<span class="tdg-hierarchy-flowchart-shell__legend-item tdg-hierarchy-flowchart-shell__legend-item--subagent">Subagente</span>'
            .'</div>'
            .'</div>'
            .'<div class="tdg-hierarchy-flowchart-shell__canvas">'
            .self::renderInteractiveHierarchyTree($tree, $initialExpandState, $highlightAgentId)
            .'</div>'
            .'</div>';

        return new HtmlString($diagram);
    }

    /**
     * Casa matriz (TDG-100) no siempre existe como registro de agencia; se arma un árbol sintético.
     *
     * @return array{
     *     headquarters?: array<string, mixed>|null,
     *     master?: array<string, mixed>|null,
     *     master_direct_agents?: list<array{agent: array<string, mixed>, subagents: list<array<string, mixed>>}>,
     *     generals?: list<array{agency: array<string, mixed>, agents: list<array{agent: array<string, mixed>, subagents: list<array<string, mixed>>}>}>
     * }
     */
    private static function buildInteractiveHierarchyTreeForHeadquarters(?int $highlightAgentId = null): array
    {
        return [
            'headquarters' => self::hierarchyNodePayload(
                title: 'Casa matriz',
                agency: null,
                tone: 'blue',
                isHighlighted: true,
                name: 'TUDRENCASA',
                subtitle: 'TDG-100',
                status: 'ACTIVO',
                structure: self::structureSummaryForAgencyCode('TDG-100'),
            ),
            'master' => null,
            'master_direct_agents' => [],
            'generals' => [
                [
                    'agency' => self::hierarchyNodePayload(
                        title: 'Equipo directo',
                        agency: null,
                        tone: 'amber',
                        isHighlighted: true,
                        name: 'Agentes TUDRENCASA',
                        subtitle: 'TDG-100',
                        status: 'ACTIVO',
                        structure: self::structureSummaryForAgencyCode('TDG-100'),
                    ),
                    'agents' => self::buildAgentTreeForAgencyCode('TDG-100', $highlightAgentId),
                ],
            ],
        ];
    }

    /**
     * @return array{
     *     headquarters?: array<string, mixed>|null,
     *     master?: array<string, mixed>|null,
     *     master_direct_agents?: list<array{agent: array<string, mixed>, subagents: list<array<string, mixed>>}>,
     *     generals?: list<array{agency: array<string, mixed>, agents: list<array{agent: array<string, mixed>, subagents: list<array<string, mixed>>}>}>
     * }
     */
    private static function buildInteractiveHierarchyTree(Agency $agency, ?int $highlightAgentId = null): array
    {
        $agencyTypeId = (int) ($agency->agency_type_id ?? 0);
        $currentAgencyCode = trim((string) ($agency->code ?? ''));
        $normalizedCurrentCode = strtoupper($currentAgencyCode);

        $tree = [
            'headquarters' => null,
            'master' => null,
            'master_direct_agents' => [],
            'generals' => [],
        ];

        $masterAgency = null;
        $generalAgencies = new EloquentCollection;

        if ($agencyTypeId === self::AGENCY_TYPE_MASTER) {
            $masterAgency = $agency;
            $ownerCode = strtoupper(trim((string) ($agency->owner_code ?? '')));

            if ($ownerCode === 'TDG-100' && $normalizedCurrentCode !== 'TDG-100') {
                $tree['headquarters'] = self::hierarchyNodePayload(
                    title: 'Casa matriz',
                    agency: null,
                    tone: 'blue',
                    name: 'TUDRENCASA',
                    subtitle: 'TDG-100',
                    status: 'ACTIVO',
                    structure: self::structureSummaryForAgencyCode('TDG-100'),
                );
            }

            if ($currentAgencyCode !== '') {
                $generalAgencies = self::generalAgenciesUnderMasterCode($currentAgencyCode);
            }
        } else {
            $ownerCode = trim((string) ($agency->owner_code ?? ''));

            if ($ownerCode !== '') {
                $masterAgency = Agency::query()
                    ->select(['code', 'name_corporative', 'agency_type_id', 'status', 'owner_code'])
                    ->whereRaw('UPPER(TRIM(code)) = ?', [strtoupper($ownerCode)])
                    ->where('agency_type_id', self::AGENCY_TYPE_MASTER)
                    ->first();

                if ($masterAgency instanceof Agency && $currentAgencyCode !== '') {
                    $generalAgencies = self::generalAgenciesUnderMasterCode(trim((string) $masterAgency->code));
                }
            }
        }

        if ($masterAgency instanceof Agency) {
            $masterCode = trim((string) ($masterAgency->code ?? ''));

            $tree['master'] = self::hierarchyNodePayload(
                title: 'Agencia master',
                agency: $masterAgency,
                tone: 'emerald',
                isHighlighted: $agencyTypeId === self::AGENCY_TYPE_MASTER,
                structure: '',
            );

            if ($masterCode !== '') {
                $tree['master_direct_agents'] = self::buildAgentTreeForAgencyCode($masterCode, $highlightAgentId);
            }
        } elseif ($agencyTypeId === self::AGENCY_TYPE_GENERAL && ! $masterAgency instanceof Agency) {
            $tree['generals'][] = [
                'agency' => self::hierarchyNodePayload(
                    title: 'Agencia general',
                    agency: $agency,
                    tone: 'amber',
                    isHighlighted: true,
                ),
                'agents' => self::buildAgentTreeForAgencyCode($currentAgencyCode, $highlightAgentId),
            ];

            return $tree;
        }

        foreach ($generalAgencies as $generalAgency) {
            $generalCode = trim((string) ($generalAgency->code ?? ''));

            $tree['generals'][] = [
                'agency' => self::hierarchyNodePayload(
                    title: 'Agencia general',
                    agency: $generalAgency,
                    tone: 'amber',
                    isHighlighted: $agencyTypeId === self::AGENCY_TYPE_GENERAL
                        && strtoupper($generalCode) === $normalizedCurrentCode,
                ),
                'agents' => $generalCode !== ''
                    ? self::buildAgentTreeForAgencyCode($generalCode, $highlightAgentId)
                    : [],
            ];
        }

        if ($tree['master'] === null && $tree['generals'] === []) {
            $tree['generals'][] = [
                'agency' => self::hierarchyNodePayload(
                    title: 'Agencia',
                    agency: $agency,
                    tone: 'blue',
                    isHighlighted: true,
                ),
                'agents' => $currentAgencyCode !== ''
                    ? self::buildAgentTreeForAgencyCode($currentAgencyCode, $highlightAgentId)
                    : [],
            ];
        }

        return $tree;
    }

    /**
     * @param  array{
     *     headquarters?: array<string, mixed>|null,
     *     master?: array<string, mixed>|null,
     *     master_direct_agents?: list<array{agent: array<string, mixed>, subagents: list<array<string, mixed>>}>,
     *     generals?: list<array{agency: array<string, mixed>, agents: list<array{agent: array<string, mixed>, subagents: list<array<string, mixed>>}>}>
     * }  $tree
     * @return array{masterAgentsOpen: bool, activeGeneralBranch: string|null, activeSubagentBranch: string|null}
     */
    private static function resolveInitialExpandState(array $tree, ?int $highlightAgentId = null): array
    {
        $defaultState = [
            'masterAgentsOpen' => false,
            'activeGeneralBranch' => null,
            'activeSubagentBranch' => null,
        ];

        if ($highlightAgentId !== null && $highlightAgentId > 0) {
            $agentState = self::resolveInitialExpandStateForHighlightedAgent($tree, $highlightAgentId);

            if ($agentState !== null) {
                return $agentState;
            }

            return $defaultState;
        }

        foreach ($tree['generals'] ?? [] as $generalBranch) {
            if (($generalBranch['agency']['is_highlighted'] ?? false) !== true) {
                continue;
            }

            $agencyCode = trim((string) ($generalBranch['agency']['subtitle'] ?? ''));

            if ($agencyCode === '') {
                break;
            }

            $defaultState['activeGeneralBranch'] = self::alpineToggleKey('general-'.$agencyCode);

            break;
        }

        return $defaultState;
    }

    /**
     * @param  array{
     *     headquarters?: array<string, mixed>|null,
     *     master?: array<string, mixed>|null,
     *     master_direct_agents?: list<array{agent: array<string, mixed>, subagents: list<array<string, mixed>>}>,
     *     generals?: list<array{agency: array<string, mixed>, agents: list<array{agent: array<string, mixed>, subagents: list<array<string, mixed>>}>}>
     * }  $tree
     * @return array{masterAgentsOpen: bool, activeGeneralBranch: string|null, activeSubagentBranch: string|null}|null
     */
    private static function resolveInitialExpandStateForHighlightedAgent(array $tree, int $highlightAgentId): ?array
    {
        foreach ($tree['master_direct_agents'] ?? [] as $branch) {
            $branchState = self::resolveInitialExpandStateForAgentBranch(
                branch: $branch,
                collectionKey: 'master-direct',
                highlightAgentId: $highlightAgentId,
                activeGeneralBranch: null,
                masterAgentsOpen: true,
            );

            if ($branchState !== null) {
                return $branchState;
            }
        }

        foreach ($tree['generals'] ?? [] as $generalBranch) {
            $agencyCode = trim((string) ($generalBranch['agency']['subtitle'] ?? ''));

            if ($agencyCode === '') {
                continue;
            }

            $generalBranchKey = self::alpineToggleKey('general-'.$agencyCode);

            foreach ($generalBranch['agents'] ?? [] as $branch) {
                $branchState = self::resolveInitialExpandStateForAgentBranch(
                    branch: $branch,
                    collectionKey: $generalBranchKey,
                    highlightAgentId: $highlightAgentId,
                    activeGeneralBranch: $generalBranchKey,
                    masterAgentsOpen: false,
                );

                if ($branchState !== null) {
                    return $branchState;
                }
            }
        }

        return null;
    }

    /**
     * @param  array{agent: array<string, mixed>, subagents: list<array<string, mixed>>}  $branch
     * @return array{masterAgentsOpen: bool, activeGeneralBranch: string|null, activeSubagentBranch: string|null}|null
     */
    private static function resolveInitialExpandStateForAgentBranch(
        array $branch,
        string $collectionKey,
        int $highlightAgentId,
        ?string $activeGeneralBranch,
        bool $masterAgentsOpen,
    ): ?array {
        $parentAgentKey = trim((string) ($branch['agent']['subtitle'] ?? ''));

        if ($parentAgentKey === '') {
            $parentAgentKey = md5((string) ($branch['agent']['name'] ?? ''));
        }

        if (self::hierarchyAgentNodeMatchesId($branch['agent'] ?? [], $highlightAgentId)) {
            return [
                'masterAgentsOpen' => $masterAgentsOpen,
                'activeGeneralBranch' => $activeGeneralBranch,
                'activeSubagentBranch' => null,
            ];
        }

        foreach ($branch['subagents'] ?? [] as $subagent) {
            if (! self::hierarchyAgentNodeMatchesId($subagent, $highlightAgentId)) {
                continue;
            }

            return [
                'masterAgentsOpen' => $masterAgentsOpen,
                'activeGeneralBranch' => $activeGeneralBranch,
                'activeSubagentBranch' => self::alpineToggleKey('subagent-'.$collectionKey.'-'.$parentAgentKey),
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function hierarchyAgentNodeMatchesId(array $node, int $agentId): bool
    {
        if ($agentId <= 0) {
            return false;
        }

        $subtitle = trim((string) ($node['subtitle'] ?? ''));

        if ($subtitle === '') {
            return false;
        }

        if (preg_match('/AGT-000(\d+)/', $subtitle, $matches) !== 1) {
            return false;
        }

        return (int) $matches[1] === $agentId;
    }

    /**
     * @param  array{
     *     headquarters?: array<string, mixed>|null,
     *     master?: array<string, mixed>|null,
     *     master_direct_agents?: list<array{agent: array<string, mixed>, subagents: list<array<string, mixed>>}>,
     *     generals?: list<array{agency: array<string, mixed>, agents: list<array{agent: array<string, mixed>, subagents: list<array<string, mixed>>}>}>
     * }  $tree
     * @return array{
     *     highlight_headquarters: bool,
     *     highlight_master: bool,
     *     general_agency_code: string|null,
     *     parent_agent_id: int|null,
     *     is_master_direct: bool
     * }|null
     */
    private static function resolveAgentFocusPath(array $tree, int $highlightAgentId): ?array
    {
        foreach ($tree['master_direct_agents'] ?? [] as $branch) {
            if (self::hierarchyAgentNodeMatchesId($branch['agent'] ?? [], $highlightAgentId)) {
                return [
                    'highlight_headquarters' => ($tree['headquarters'] ?? null) !== null,
                    'highlight_master' => ($tree['master'] ?? null) !== null,
                    'general_agency_code' => null,
                    'parent_agent_id' => null,
                    'is_master_direct' => true,
                ];
            }

            foreach ($branch['subagents'] ?? [] as $subagent) {
                if (! self::hierarchyAgentNodeMatchesId($subagent, $highlightAgentId)) {
                    continue;
                }

                return [
                    'highlight_headquarters' => ($tree['headquarters'] ?? null) !== null,
                    'highlight_master' => ($tree['master'] ?? null) !== null,
                    'general_agency_code' => null,
                    'parent_agent_id' => self::agentIdFromNodePayload($branch['agent'] ?? []),
                    'is_master_direct' => true,
                ];
            }
        }

        foreach ($tree['generals'] ?? [] as $generalBranch) {
            $agencyCode = trim((string) ($generalBranch['agency']['subtitle'] ?? ''));

            foreach ($generalBranch['agents'] ?? [] as $branch) {
                if (self::hierarchyAgentNodeMatchesId($branch['agent'] ?? [], $highlightAgentId)) {
                    return [
                        'highlight_headquarters' => ($tree['headquarters'] ?? null) !== null,
                        'highlight_master' => ($tree['master'] ?? null) !== null,
                        'general_agency_code' => $agencyCode !== '' ? $agencyCode : null,
                        'parent_agent_id' => null,
                        'is_master_direct' => false,
                    ];
                }

                foreach ($branch['subagents'] ?? [] as $subagent) {
                    if (! self::hierarchyAgentNodeMatchesId($subagent, $highlightAgentId)) {
                        continue;
                    }

                    return [
                        'highlight_headquarters' => ($tree['headquarters'] ?? null) !== null,
                        'highlight_master' => ($tree['master'] ?? null) !== null,
                        'general_agency_code' => $agencyCode !== '' ? $agencyCode : null,
                        'parent_agent_id' => self::agentIdFromNodePayload($branch['agent'] ?? []),
                        'is_master_direct' => false,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param  array{
     *     highlight_headquarters: bool,
     *     highlight_master: bool,
     *     general_agency_code: string|null,
     *     parent_agent_id: int|null,
     *     is_master_direct: bool
     * }  $focusPath
     * @param  array{
     *     headquarters?: array<string, mixed>|null,
     *     master?: array<string, mixed>|null,
     *     master_direct_agents?: list<array{agent: array<string, mixed>, subagents: list<array<string, mixed>>}>,
     *     generals?: list<array{agency: array<string, mixed>, agents: list<array{agent: array<string, mixed>, subagents: list<array<string, mixed>>}>}>
     * }  $tree
     */
    private static function applyAgentFocusPathToTree(array &$tree, array $focusPath, int $highlightAgentId): void
    {
        $tree['focus_path_meta'] = $focusPath;

        if ($focusPath['highlight_headquarters'] && ($tree['headquarters'] ?? null) !== null) {
            $tree['headquarters']['is_focus_path'] = true;
        }

        if ($focusPath['highlight_master'] && ($tree['master'] ?? null) !== null) {
            $tree['master']['is_focus_path'] = true;
        }

        $normalizedGeneralCode = strtoupper(trim((string) ($focusPath['general_agency_code'] ?? '')));

        if ($normalizedGeneralCode !== '') {
            foreach ($tree['generals'] as &$generalBranch) {
                $branchCode = strtoupper(trim((string) ($generalBranch['agency']['subtitle'] ?? '')));

                if ($branchCode !== $normalizedGeneralCode) {
                    continue;
                }

                $generalBranch['is_focus_path'] = true;
                $generalBranch['agency']['is_focus_path'] = true;

                foreach ($generalBranch['agents'] as &$agentBranch) {
                    self::markAgentBranchFocusPath($agentBranch, $focusPath['parent_agent_id'], $highlightAgentId);
                }

                unset($agentBranch);

                break;
            }

            unset($generalBranch);

            return;
        }

        if (! ($focusPath['is_master_direct'] ?? false)) {
            return;
        }

        foreach ($tree['master_direct_agents'] as &$agentBranch) {
            self::markAgentBranchFocusPath($agentBranch, $focusPath['parent_agent_id'], $highlightAgentId);
        }

        unset($agentBranch);
    }

    /**
     * @param  array{agent: array<string, mixed>, subagents: list<array<string, mixed>>}  $branch
     */
    private static function markAgentBranchFocusPath(array &$branch, ?int $parentAgentId, int $highlightAgentId): void
    {
        $branchAgentId = self::agentIdFromNodePayload($branch['agent'] ?? []);

        if ($parentAgentId !== null && $branchAgentId === $parentAgentId) {
            $branch['is_focus_path'] = true;
            $branch['agent']['is_focus_path'] = true;
        }
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function agentIdFromNodePayload(array $node): ?int
    {
        $subtitle = trim((string) ($node['subtitle'] ?? ''));

        if ($subtitle === '' || preg_match('/AGT-000(\d+)/', $subtitle, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private static function alpineInitialBranchValue(?string $branchKey): string
    {
        if ($branchKey === null || $branchKey === '') {
            return 'null';
        }

        return "'".addslashes($branchKey)."'";
    }

    /**
     * @param  array{
     *     headquarters?: array<string, mixed>|null,
     *     master?: array<string, mixed>|null,
     *     master_direct_agents?: list<array{agent: array<string, mixed>, subagents: list<array<string, mixed>>}>,
     *     generals?: list<array{agency: array<string, mixed>, agents: list<array{agent: array<string, mixed>, subagents: list<array<string, mixed>>}>}>
     * }  $tree
     * @param  array{masterAgentsOpen?: bool, activeGeneralBranch?: string|null, activeSubagentBranch?: string|null}  $initialExpandState
     */
    private static function renderInteractiveHierarchyTree(array $tree, array $initialExpandState = [], ?int $highlightAgentId = null): string
    {
        $hasContent = ($tree['headquarters'] ?? null) !== null
            || ($tree['master'] ?? null) !== null
            || ($tree['generals'] ?? []) !== [];

        if (! $hasContent) {
            return '<div class="tdg-hierarchy-flowchart__empty">'
                .self::hierarchyIconSvg('empty')
                .'<p>Sin datos de jerarquía para mostrar.</p>'
                .'</div>';
        }

        if ($highlightAgentId !== null && $highlightAgentId > 0) {
            $focusPath = self::resolveAgentFocusPath($tree, $highlightAgentId);

            if ($focusPath !== null) {
                self::applyAgentFocusPathToTree($tree, $focusPath, $highlightAgentId);
            }
        }

        $masterOnFocusPath = (bool) ($tree['master']['is_focus_path'] ?? false);
        $headquartersOnFocusPath = (bool) ($tree['headquarters']['is_focus_path'] ?? false);
        $masterAgentsOnFocusPath = (bool) ($tree['focus_path_meta']['is_master_direct'] ?? false);

        $masterAgentsOpen = ($initialExpandState['masterAgentsOpen'] ?? false) ? 'true' : 'false';
        $activeGeneralBranch = self::alpineInitialBranchValue($initialExpandState['activeGeneralBranch'] ?? null);
        $activeSubagentBranch = self::alpineInitialBranchValue($initialExpandState['activeSubagentBranch'] ?? null);
        $agentFocusClass = $highlightAgentId !== null && $highlightAgentId > 0
            ? ' tdg-hierarchy-flowchart--agent-focus'
            : '';

        $html = '<div class="tdg-hierarchy-flowchart tdg-hierarchy-flowchart--interactive'.$agentFocusClass.'" x-data="{ masterAgentsOpen: '.$masterAgentsOpen.', activeGeneralBranch: '.$activeGeneralBranch.', activeSubagentBranch: '.$activeSubagentBranch.', toggleGeneralAgents(branchKey) { this.masterAgentsOpen = false; this.activeSubagentBranch = null; this.activeGeneralBranch = this.activeGeneralBranch === branchKey ? null : branchKey; }, toggleMasterAgents() { this.activeGeneralBranch = null; this.activeSubagentBranch = null; this.masterAgentsOpen = ! this.masterAgentsOpen; }, toggleSubagents(branchKey) { this.activeSubagentBranch = this.activeSubagentBranch === branchKey ? null : branchKey; } }">';

        if (($tree['headquarters'] ?? null) !== null) {
            $html .= self::renderHierarchyTier(
                'Matriz',
                self::renderHierarchyFlowNode($tree['headquarters']),
                tierFocusPath: $headquartersOnFocusPath,
            );
        }

        if (($tree['master'] ?? null) !== null) {
            if (($tree['headquarters'] ?? null) !== null) {
                $html .= self::renderHierarchyFlowConnector(onFocusPath: $headquartersOnFocusPath && $masterOnFocusPath);
            }

            $html .= self::renderHierarchyTier(
                'Master',
                self::renderMasterHierarchyNode($tree),
                tierFocusPath: $masterOnFocusPath,
            );

            $masterAgents = $tree['master_direct_agents'] ?? [];

            if ($masterAgents !== []) {
                $html .= self::renderExpandableAgentBranch(
                    label: count($masterAgents).' agente(s) directo(s) de master',
                    agents: $masterAgents,
                    alpineToggle: 'masterAgentsOpen',
                    branchKey: 'master-direct',
                    isFocusPath: $masterAgentsOnFocusPath,
                );
            }
        }

        $generals = $tree['generals'] ?? [];

        if ($generals !== []) {
            if (($tree['master'] ?? null) !== null || ($tree['headquarters'] ?? null) !== null) {
                $html .= self::renderHierarchyFlowConnector(count($generals));
            }

            $generalBlocks = array_map(
                fn (array $generalBranch): string => self::renderGeneralBranch($generalBranch),
                $generals,
            );

            $html .= self::renderHierarchyTier(
                'General',
                self::renderNodeCollection($generalBlocks, 'general'),
                tierKey: 'general',
            );
        }

        $html .= '<div class="tdg-hierarchy-flowchart__subagents-dock" id="hierarchy-subagents-dock"></div>';

        return $html.'</div>';
    }

    /**
     * @param  array{agency: array<string, mixed>, agents: list<array{agent: array<string, mixed>, subagents: list<array<string, mixed>>}>}  $generalBranch
     */
    private static function renderGeneralBranch(array $generalBranch): string
    {
        $agencyNode = $generalBranch['agency'] ?? [];
        $agents = $generalBranch['agents'] ?? [];
        $agencyCode = (string) ($agencyNode['subtitle'] ?? '');
        $branchKey = self::alpineToggleKey('general-'.$agencyCode);
        $openExpression = "activeGeneralBranch === '{$branchKey}'";

        $agencyName = trim((string) ($agencyNode['name'] ?? ''));
        $panelOriginLabel = self::connectorEntityLabel($agencyNode);

        if ($agencyCode !== '' && $agencyName !== '') {
            $panelOriginLabel = $agencyCode.' · '.$agencyName;
        }

        $isFocusPath = (bool) ($generalBranch['is_focus_path'] ?? false);
        $stackFocusClass = $isFocusPath ? ' tdg-hierarchy-flowchart__general-stack--focus-path' : '';

        $html = '<div class="tdg-hierarchy-flowchart__general-stack'.$stackFocusClass.'" :class="{ \'is-active\': '.$openExpression.' }" data-general-branch="'.$branchKey.'">'
            .'<div class="tdg-hierarchy-flowchart__branch">'
            .self::renderHierarchyFlowNode($agencyNode)
            .'</div>';

        if ($agents !== []) {
            $html .= self::renderExpandableConnector(
                label: count($agents).' agente(s) directo(s) de '.self::connectorEntityLabel($agencyNode),
                openExpression: $openExpression,
                toggleClick: "toggleGeneralAgents('{$branchKey}')",
                sectionLabel: 'Agentes · '.$panelOriginLabel,
                panelContent: self::renderAgentTreeCollection($agents, $branchKey),
                tone: 'amber',
                nested: true,
                teleportTo: '#hierarchy-general-agents-dock',
                panelSourceKey: $branchKey,
                isFocusPath: $isFocusPath,
            );
        }

        return $html.'</div>';
    }

    /**
     * @param  list<array{agent: array<string, mixed>, subagents: list<array<string, mixed>>}>  $agents
     */
    private static function renderAgentTreeCollection(array $agents, string $collectionKey): string
    {
        $agentBlocks = array_map(
            fn (array $branch): string => self::renderAgentBranch($branch, $collectionKey),
            $agents,
        );

        return self::renderNodeCollection($agentBlocks, 'agent-'.$collectionKey);
    }

    /**
     * @param  list<array{agent: array<string, mixed>, subagents: list<array<string, mixed>>}>  $agents
     */
    private static function renderExpandableAgentBranch(
        string $label,
        array $agents,
        string $alpineToggle,
        string $branchKey,
        bool $isFocusPath = false,
    ): string {
        return self::renderExpandableConnector(
            label: $label,
            openExpression: 'masterAgentsOpen',
            toggleClick: 'toggleMasterAgents()',
            sectionLabel: 'Equipo directo',
            panelContent: self::renderAgentTreeCollection($agents, $branchKey),
            tone: 'emerald',
            horizontalAgentsLayout: true,
            isFocusPath: $isFocusPath,
        );
    }

    private static function renderExpandableConnector(
        string $label,
        string $openExpression,
        string $toggleClick,
        string $sectionLabel,
        string $panelContent,
        string $tone = 'emerald',
        bool $nested = false,
        ?string $teleportTo = null,
        bool $horizontalAgentsLayout = false,
        ?string $panelSourceKey = null,
        bool $isFocusPath = false,
    ): string {
        $nestedClass = $nested ? ' tdg-hierarchy-flowchart__expandable--nested' : '';
        $focusPathClass = $isFocusPath ? ' tdg-hierarchy-flowchart__expandable--focus-path' : '';
        $teleportClass = match ($teleportTo) {
            '#hierarchy-general-agents-dock' => ' tdg-hierarchy-flowchart__expandable--general-agents',
            '#hierarchy-subagents-dock' => ' tdg-hierarchy-flowchart__expandable--subagents-panel',
            default => '',
        };
        $horizontalLayoutClass = $horizontalAgentsLayout ? ' tdg-hierarchy-flowchart__expandable--horizontal-agents' : '';
        $toneClass = ' tdg-hierarchy-flowchart__expand-trigger--'.$tone;
        $branchSectionClass = ($teleportTo !== null || $horizontalAgentsLayout)
            ? ' tdg-hierarchy-flowchart__branch-section--horizontal'
            : '';
        $panelWrapperClass = match ($teleportTo) {
            '#hierarchy-general-agents-dock' => 'tdg-hierarchy-flowchart__general-agents-panel',
            '#hierarchy-subagents-dock' => 'tdg-hierarchy-flowchart__subagents-panel',
            default => '',
        };

        if ($isFocusPath && $panelWrapperClass !== '') {
            $panelWrapperClass .= ' tdg-hierarchy-flowchart__'.str_replace('tdg-hierarchy-flowchart__', '', $panelWrapperClass).'--focus-path';
        }

        $triggerHtml = self::renderHierarchyFlowConnector(onFocusPath: $isFocusPath)
            .'<button type="button" class="tdg-hierarchy-flowchart__expand-trigger'.$toneClass.'" @click="'.$toggleClick.'" :aria-expanded="'.$openExpression.'">'
            .'<span class="tdg-hierarchy-flowchart__expand-trigger-line" aria-hidden="true"></span>'
            .'<span class="tdg-hierarchy-flowchart__expand-trigger-dot" aria-hidden="true"></span>'
            .'<span class="tdg-hierarchy-flowchart__expand-trigger-label">'.e($label).'</span>'
            .'<span class="tdg-hierarchy-flowchart__expand-trigger-chevron" :class="{ \'is-open\': '.$openExpression.' }" aria-hidden="true">'
            .self::hierarchyIconSvg('chevron')
            .'</span>'
            .'</button>';

        if ($teleportTo !== null) {
            $triggerHtml .= self::renderExpandActiveIndicator($tone, $openExpression);

            $sourceKeyAttr = $panelSourceKey !== null
                ? ' data-panel-source="'.e($panelSourceKey).'"'
                : '';

            $panelHtml = '<template x-teleport="'.$teleportTo.'">'
                .'<div class="'.$panelWrapperClass.'"'.$sourceKeyAttr.' x-show="'.$openExpression.'" x-collapse'.self::hierarchySliderPanelRefreshInit().'>'
                .'<div class="tdg-hierarchy-flowchart__branch-section tdg-hierarchy-flowchart__branch-section--horizontal">'
                .'<span class="tdg-hierarchy-flowchart__branch-section-label">'.e($sectionLabel).'</span>'
                .$panelContent
                .'</div>'
                .'</div>'
                .'</template>';

            return '<div class="tdg-hierarchy-flowchart__expandable'.$nestedClass.$teleportClass.$horizontalLayoutClass.$focusPathClass.'" :class="{ \'is-open\': '.$openExpression.' }">'
                .$triggerHtml
                .$panelHtml
                .'</div>';
        }

        return '<div class="tdg-hierarchy-flowchart__expandable'.$nestedClass.$teleportClass.$horizontalLayoutClass.$focusPathClass.'" :class="{ \'is-open\': '.$openExpression.' }">'
            .$triggerHtml
            .'<div class="tdg-hierarchy-flowchart__expand-panel" x-show="'.$openExpression.'" x-collapse'.self::hierarchySliderPanelRefreshInit().'>'
            .'<div class="tdg-hierarchy-flowchart__branch-section'.$branchSectionClass.'">'
            .'<span class="tdg-hierarchy-flowchart__branch-section-label">'.e($sectionLabel).'</span>'
            .$panelContent
            .'</div>'
            .'</div>'
            .'</div>';
    }

    /**
     * @param  array{agent: array<string, mixed>, subagents: list<array<string, mixed>>}>  $branch
     */
    private static function renderAgentBranch(array $branch, string $parentKey): string
    {
        $agentNode = $branch['agent'] ?? [];
        $subagents = $branch['subagents'] ?? [];
        $agentId = (string) ($agentNode['subtitle'] ?? md5((string) ($agentNode['name'] ?? '')));
        $toggleKey = self::alpineToggleKey('subagent-'.$parentKey.'-'.$agentId);
        $openExpression = "activeSubagentBranch === '{$toggleKey}'";
        $isFocusPath = (bool) ($branch['is_focus_path'] ?? false);
        $branchFocusClass = $isFocusPath ? ' tdg-hierarchy-flowchart__agent-branch--focus-path' : '';

        $html = '<div class="tdg-hierarchy-flowchart__agent-branch'.$branchFocusClass.'" :class="{ \'is-active\': '.$openExpression.' }">'
            .self::renderHierarchyFlowNode($agentNode);

        if ($subagents !== []) {
            $subagentBlocks = array_map(
                fn (array $subagent): string => self::renderHierarchyFlowNode($subagent),
                $subagents,
            );

            $html .= self::renderExpandableConnector(
                label: count($subagents).' subagente(s) de '.self::connectorEntityLabel($agentNode),
                openExpression: $openExpression,
                toggleClick: "toggleSubagents('{$toggleKey}')",
                sectionLabel: 'Subagentes · '.self::connectorEntityLabel($agentNode),
                panelContent: self::renderNodeCollection($subagentBlocks, 'subagent-'.$parentKey.'-'.$agentId),
                tone: 'violet',
                nested: true,
                teleportTo: '#hierarchy-subagents-dock',
                panelSourceKey: $toggleKey,
                isFocusPath: $isFocusPath && $subagents !== [],
            );
        }

        return $html.'</div>';
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function connectorEntityLabel(array $node): string
    {
        $code = trim((string) ($node['subtitle'] ?? ''));
        $name = trim((string) ($node['name'] ?? ''));

        if ($code !== '') {
            return $code;
        }

        if ($name !== '') {
            return mb_strlen($name) > 28 ? mb_substr($name, 0, 28).'…' : $name;
        }

        return 'nodo';
    }

    private static function alpineToggleKey(string $seed): string
    {
        return 'k_'.substr(md5($seed), 0, 12);
    }

    private static function hierarchySliderAlpineData(int $slideCount): string
    {
        return '{'
            .'slideCount: '.$slideCount.', '
            .'canScrollPrev: false, '
            .'canScrollNext: false, '
            .'counterLabel: \'1 / '.$slideCount.'\', '
            .'didScrollToHighlighted: false, '
            .'initSlider(el) { '
            .'if (! el) { return; } '
            .'this.refreshSlider(el); '
            .'if (this._sliderObserver) { this._sliderObserver.disconnect(); } '
            .'this._sliderObserver = new ResizeObserver(() => this.refreshSlider(el)); '
            .'this._sliderObserver.observe(el); '
            .'this.scrollToHighlighted(el, false); '
            .'}, '
            .'getHighlightedSlideIndex(el) { '
            .'const slides = Array.from(el.querySelectorAll(\'.tdg-hierarchy-slider__slide\')); '
            .'return slides.findIndex((slide) => slide.getAttribute(\'data-hierarchy-highlighted\') === \'1\' || slide.querySelector(\'.tdg-hierarchy-flowchart__node--highlighted-person, .tdg-hierarchy-flowchart__node--highlighted\')); '
            .'}, '
            .'getCurrentSlideIndex(el) { '
            .'const slides = el.querySelectorAll(\'.tdg-hierarchy-slider__slide\'); '
            .'if (! slides.length) { return 0; } '
            .'let closest = 0; '
            .'let minDistance = Number.POSITIVE_INFINITY; '
            .'slides.forEach((slide, index) => { '
            .'const distance = Math.abs(slide.offsetLeft - el.scrollLeft); '
            .'if (distance < minDistance) { minDistance = distance; closest = index; } '
            .'}); '
            .'return closest; '
            .'}, '
            .'refreshSlider(el) { '
            .'if (! el) { return; } '
            .'const slides = el.querySelectorAll(\'.tdg-hierarchy-slider__slide\'); '
            .'const total = slides.length || this.slideCount; '
            .'const index = this.getCurrentSlideIndex(el); '
            .'this.canScrollPrev = index > 0; '
            .'this.canScrollNext = index < total - 1; '
            .'this.counterLabel = (index + 1) + \' / \' + total; '
            .'}, '
            .'scrollToSlide(el, index, smooth, center) { '
            .'if (smooth === undefined) { smooth = true; } '
            .'if (center === undefined) { center = true; } '
            .'const slides = el.querySelectorAll(\'.tdg-hierarchy-slider__slide\'); '
            .'const slide = slides[index]; '
            .'if (! slide || ! el) { return; } '
            .'const max = Math.max(0, el.scrollWidth - el.clientWidth); '
            .'const centered = slide.offsetLeft - ((el.clientWidth - slide.clientWidth) / 2); '
            .'const left = Math.min(max, Math.max(0, center ? centered : slide.offsetLeft)); '
            .'el.scrollTo({ left, behavior: smooth ? \'smooth\' : \'auto\' }); '
            .'window.setTimeout(() => this.refreshSlider(el), smooth ? 360 : 0); '
            .'}, '
            .'scrollToHighlighted(el, smooth) { '
            .'if (smooth === undefined) { smooth = false; } '
            .'if (! el) { return; } '
            .'const index = this.getHighlightedSlideIndex(el); '
            .'if (index < 0) { return; } '
            .'this.scrollToSlide(el, index, smooth, true); '
            .'this.didScrollToHighlighted = true; '
            .'}, '
            .'scrollPrev(el) { '
            .'if (! this.canScrollPrev || ! el) { return; } '
            .'this.scrollToSlide(el, this.getCurrentSlideIndex(el) - 1); '
            .'}, '
            .'scrollNext(el) { '
            .'if (! this.canScrollNext || ! el) { return; } '
            .'this.scrollToSlide(el, this.getCurrentSlideIndex(el) + 1); '
            .'} '
            .'}';
    }

    private static function hierarchySliderPanelRefreshInit(): string
    {
        return ' x-init="$nextTick(() => { const refreshPanelSliders = () => { $el.querySelectorAll(\'[data-hierarchy-slider]\').forEach((slider) => { const viewport = slider.querySelector(\'.tdg-hierarchy-slider__viewport\'); const api = Alpine.$data(slider); if (api?.initSlider && viewport) { api.initSlider(viewport); } if (api?.scrollToHighlighted && viewport) { api.scrollToHighlighted(viewport, false); } }); }; refreshPanelSliders(); window.setTimeout(refreshPanelSliders, 120); window.setTimeout(refreshPanelSliders, 360); })"';
    }

    /**
     * @param  list<string>  $nodeHtmlBlocks
     */
    private static function renderNodeCollection(array $nodeHtmlBlocks, string $collectionKey): string
    {
        $count = count($nodeHtmlBlocks);

        if ($count === 0) {
            return '';
        }

        if ($count <= self::SLIDER_THRESHOLD) {
            return '<div class="tdg-hierarchy-flowchart__nodes tdg-hierarchy-flowchart__nodes--inline" x-init="$nextTick(() => { $el.querySelector(\'.tdg-hierarchy-flowchart__node--highlighted-person, .tdg-hierarchy-flowchart__node--highlighted\')?.scrollIntoView({ inline: \'center\', block: \'nearest\', behavior: \'auto\' }); })">'
                .implode('', $nodeHtmlBlocks)
                .'</div>';
        }

        $sliderId = 'hierarchy-slider-'.(++self::$sliderSequence).'-'.substr(md5($collectionKey), 0, 8);

        $html = '<div class="tdg-hierarchy-slider" data-hierarchy-slider x-data="'.e(self::hierarchySliderAlpineData($count)).'" x-init="initSlider($refs.viewport)" id="'.$sliderId.'">'
            .'<div class="tdg-hierarchy-slider__controls">'
            .'<button type="button" class="tdg-hierarchy-slider__btn" @click="scrollPrev($refs.viewport)" :disabled="!canScrollPrev" aria-label="Anterior">'
            .self::hierarchyIconSvg('chevron-left')
            .'</button>'
            .'<span class="tdg-hierarchy-slider__counter" x-text="counterLabel" aria-live="polite"></span>'
            .'<button type="button" class="tdg-hierarchy-slider__btn" @click="scrollNext($refs.viewport)" :disabled="!canScrollNext" aria-label="Siguiente">'
            .self::hierarchyIconSvg('chevron-right')
            .'</button>'
            .'</div>'
            .'<div class="tdg-hierarchy-slider__viewport" x-ref="viewport" @scroll="refreshSlider($refs.viewport)">'
            .'<div class="tdg-hierarchy-slider__track">';

        foreach ($nodeHtmlBlocks as $block) {
            $isHighlightedSlide = str_contains($block, 'tdg-hierarchy-flowchart__node--highlighted');
            $html .= '<div class="tdg-hierarchy-slider__slide"'
                .($isHighlightedSlide ? ' data-hierarchy-highlighted="1"' : '')
                .'>'.$block.'</div>';
        }

        return $html
            .'</div>'
            .'</div>'
            .'</div>';
    }

    /**
     * @param  array{
     *     master?: array<string, mixed>|null,
     *     master_direct_agents?: list<array{agent: array<string, mixed>, subagents: list<array<string, mixed>>}>,
     *     generals?: list<array{agency: array<string, mixed>, agents: list<array{agent: array<string, mixed>, subagents: list<array<string, mixed>>}>}>
     * }  $tree
     */
    private static function renderMasterHierarchyNode(array $tree): string
    {
        $nodeHtml = self::renderHierarchyFlowNode($tree['master'] ?? []);
        $summaryMarkup = self::renderMasterStructureCountsMarkup($tree);

        if ($summaryMarkup === '') {
            return $nodeHtml;
        }

        return str_replace('</article>', $summaryMarkup.'</article>', $nodeHtml);
    }

    /**
     * @param  array{
     *     master_direct_agents?: list<array{agent: array<string, mixed>, subagents: list<array<string, mixed>>}>,
     *     generals?: list<array{agency: array<string, mixed>, agents: list<array{agent: array<string, mixed>, subagents: list<array<string, mixed>>}>}>
     * }  $tree
     */
    private static function renderMasterStructureCountsMarkup(array $tree): string
    {
        $counts = self::buildMasterStructureCounts($tree);

        return '<div class="tdg-hierarchy-flowchart__node-structure-summary" aria-label="Resumen de estructura bajo master">'
            .'<p class="tdg-hierarchy-flowchart__node-meta tdg-hierarchy-flowchart__node-meta--structure-summary">'
            .e($counts['generals'].' Agencia(s) General(s)')
            .'</p>'
            .'<p class="tdg-hierarchy-flowchart__node-meta tdg-hierarchy-flowchart__node-meta--structure-summary">'
            .e($counts['agents'].' Agente(s)')
            .'</p>'
            .'<p class="tdg-hierarchy-flowchart__node-meta tdg-hierarchy-flowchart__node-meta--structure-summary">'
            .e($counts['subagents'].' Sub-Agente(s)')
            .'</p>'
            .'</div>';
    }

    /**
     * @param  array{
     *     master_direct_agents?: list<array{agent: array<string, mixed>, subagents: list<array<string, mixed>>}>,
     *     generals?: list<array{agency: array<string, mixed>, agents: list<array{agent: array<string, mixed>, subagents: list<array<string, mixed>>}>}>
     * }  $tree
     * @return array{generals: int, agents: int, subagents: int}
     */
    private static function buildMasterStructureCounts(array $tree): array
    {
        $masterDirectAgents = $tree['master_direct_agents'] ?? [];
        $generals = $tree['generals'] ?? [];

        $agentCount = count($masterDirectAgents);
        $subagentCount = self::countSubagentsInAgentTree($masterDirectAgents);

        foreach ($generals as $generalBranch) {
            $agentBranches = $generalBranch['agents'] ?? [];
            $agentCount += count($agentBranches);
            $subagentCount += self::countSubagentsInAgentTree($agentBranches);
        }

        return [
            'generals' => count($generals),
            'agents' => $agentCount,
            'subagents' => $subagentCount,
        ];
    }

    /**
     * @param  list<array{agent: array<string, mixed>, subagents: list<array<string, mixed>>}>  $agentBranches
     */
    private static function countSubagentsInAgentTree(array $agentBranches): int
    {
        $count = 0;

        foreach ($agentBranches as $branch) {
            $count += count($branch['subagents'] ?? []);
        }

        return $count;
    }

    private static function renderHierarchyTier(string $label, string $content, ?string $tierKey = null, bool $tierFocusPath = false): string
    {
        $tierClass = $tierKey !== null
            ? ' tdg-hierarchy-flowchart__tier--'.$tierKey
            : '';
        $tierFocusClass = $tierFocusPath ? ' tdg-hierarchy-flowchart__tier--focus-path' : '';
        $agentsDock = $tierKey === 'general'
            ? '<div class="tdg-hierarchy-flowchart__general-agents-dock" id="hierarchy-general-agents-dock"></div>'
            : '';

        return '<div class="tdg-hierarchy-flowchart__tier'.$tierClass.$tierFocusClass.'">'
            .'<div class="tdg-hierarchy-flowchart__tier-label">'
            .'<span class="tdg-hierarchy-flowchart__tier-label-text">'.e($label).'</span>'
            .'</div>'
            .'<div class="tdg-hierarchy-flowchart__tier-body">'.$content.$agentsDock.'</div>'
            .'</div>';
    }

    private static function renderHierarchyFlowConnector(int $childCount = 1, bool $onFocusPath = false): string
    {
        $branchClass = $childCount > 1 ? ' tdg-hierarchy-flowchart__connector--branch' : '';
        $focusPathClass = $onFocusPath ? ' tdg-hierarchy-flowchart__connector--focus-path' : '';

        return '<div class="tdg-hierarchy-flowchart__connector'.$branchClass.$focusPathClass.'" aria-hidden="true">'
            .'<span class="tdg-hierarchy-flowchart__connector-line"></span>'
            .'<span class="tdg-hierarchy-flowchart__connector-dot"></span>'
            .'</div>';
    }

    private static function renderExpandActiveIndicator(string $tone, string $openExpression): string
    {
        return '<div class="tdg-hierarchy-flowchart__expand-active-indicator tdg-hierarchy-flowchart__expand-active-indicator--'.$tone.'" x-show="'.$openExpression.'" x-collapse>'
            .'<div class="tdg-hierarchy-flowchart__connector" aria-hidden="true">'
            .'<span class="tdg-hierarchy-flowchart__connector-line"></span>'
            .'<span class="tdg-hierarchy-flowchart__connector-dot"></span>'
            .'</div>'
            .'</div>';
    }

    /**
     * @return list<array{agent: array<string, mixed>, subagents: list<array<string, mixed>>}>
     */
    private static function buildAgentTreeForAgencyCode(string $agencyCode, ?int $highlightAgentId = null): array
    {
        if (trim($agencyCode) === '') {
            return [];
        }

        $allAgents = self::agentsForAgencyCode($agencyCode);
        $subagentsByOwner = [];
        $directAgents = [];

        foreach ($allAgents as $agent) {
            if ((int) ($agent->agent_type_id ?? 0) === 3) {
                $ownerId = (int) ($agent->owner_agent ?? 0);

                if ($ownerId > 0) {
                    $subagentsByOwner[$ownerId][] = $agent;
                }

                continue;
            }

            $directAgents[] = $agent;
        }

        $tree = [];

        foreach ($directAgents as $agent) {
            $agentId = (int) ($agent->id ?? 0);
            $subagents = $subagentsByOwner[$agentId] ?? [];

            $tree[] = [
                'agent' => self::hierarchyAgentNodePayload($agent, $highlightAgentId),
                'subagents' => array_map(
                    fn (Agent $subagent): array => self::hierarchyAgentNodePayload($subagent, $highlightAgentId),
                    $subagents,
                ),
            ];
        }

        return $tree;
    }

    /**
     * @return array{kind: string, title: string, name: string, subtitle: string, status: string, tone: string, structure: string|null, is_highlighted: bool}
     */
    private static function hierarchyAgentNodePayload(Agent $agent, ?int $highlightAgentId = null): array
    {
        $agentTypeId = (int) ($agent->agent_type_id ?? 0);
        $agentId = (int) ($agent->id ?? 0);

        $displayName = trim((string) ($agent->name ?? 'Sin nombre'));

        if ($displayName === '') {
            $displayName = 'Sin nombre';
        }

        return [
            'kind' => 'agent',
            'title' => $agentTypeId === 3 ? 'Subagente' : 'Agente',
            'name' => mb_strtoupper($displayName),
            'subtitle' => 'AGT-000'.$agentId,
            'status' => (string) ($agent->status ?? 'Sin estado'),
            'tone' => $agentTypeId === 3 ? 'slate' : 'violet',
            'structure' => null,
            'is_highlighted' => $highlightAgentId !== null && $agentId === $highlightAgentId,
        ];
    }

    /**
     * @return array{kind: string, title: string, name: string, subtitle: string, status: string, tone: string, structure: string|null, is_highlighted: bool}
     */
    private static function hierarchyNodePayload(
        string $title,
        ?Agency $agency,
        string $tone,
        bool $isHighlighted = false,
        ?string $name = null,
        ?string $subtitle = null,
        ?string $status = null,
        ?string $structure = null,
    ): array {
        $displayName = $name ?? ($agency instanceof Agency ? self::resolveAgencyDisplayName($agency) : 'Sin razón social');

        if ($title === 'Agencia general') {
            $displayName = mb_strtoupper($displayName);
        }

        return [
            'kind' => 'agency',
            'title' => $title,
            'name' => $displayName,
            'subtitle' => $subtitle ?? ($agency instanceof Agency ? trim((string) ($agency->code ?? 'Sin código')) : 'Sin código'),
            'status' => $status ?? ($agency instanceof Agency ? (string) ($agency->status ?? 'Sin estado') : 'Sin estado'),
            'tone' => $tone,
            'structure' => $structure ?? ($agency instanceof Agency ? self::structureSummaryForAgency($agency) : null),
            'is_highlighted' => $isHighlighted,
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function renderHierarchyFlowNode(array $node): string
    {
        $kind = (string) ($node['kind'] ?? 'agency');
        $title = (string) ($node['title'] ?? '');
        $name = (string) ($node['name'] ?? '');
        $tone = (string) ($node['tone'] ?? 'blue');
        $tonePalette = match ($tone) {
            'emerald' => 'tdg-hierarchy-flowchart__node--emerald',
            'amber' => 'tdg-hierarchy-flowchart__node--amber',
            'violet' => 'tdg-hierarchy-flowchart__node--violet',
            'slate' => 'tdg-hierarchy-flowchart__node--slate',
            default => 'tdg-hierarchy-flowchart__node--blue',
        };

        $kindClass = $kind === 'agent' ? ' tdg-hierarchy-flowchart__node--person' : ' tdg-hierarchy-flowchart__node--agency';
        $isHighlighted = (bool) ($node['is_highlighted'] ?? false);
        $isFocusPath = (bool) ($node['is_focus_path'] ?? false);
        $highlightClass = $isHighlighted ? ' tdg-hierarchy-flowchart__node--highlighted' : '';
        $focusPathClass = $isFocusPath ? ' tdg-hierarchy-flowchart__node--focus-path' : '';
        $highlightPersonClass = $isHighlighted
            ? ' tdg-hierarchy-flowchart__node--highlighted-person'
            : '';
        $statusBadge = self::renderStatusBadge((string) ($node['status'] ?? 'Sin estado'));
        $highlightBadge = $isHighlighted
            ? '<span class="tdg-hierarchy-flowchart__node-highlight-badge">'
                .($kind === 'agent' ? 'Este agente' : 'Esta agencia')
                .'</span>'
            : '';
        $iconMarkup = $kind === 'agent'
            ? '<span class="tdg-hierarchy-flowchart__node-avatar">'.e(self::nodeInitials($name)).'</span>'
            : self::hierarchyNodeIconMarkup($title, $tone);

        $structure = $node['structure'] ?? null;
        $structureMarkup = filled($structure) && $structure !== 'Sin estructura de agentes/subagentes'
            ? '<p class="tdg-hierarchy-flowchart__node-meta">'.e((string) $structure).'</p>'
            : '';

        return '<article class="tdg-hierarchy-flowchart__node '.$tonePalette.$kindClass.$highlightClass.$focusPathClass.$highlightPersonClass.'">'
            .'<div class="tdg-hierarchy-flowchart__node-glow" aria-hidden="true"></div>'
            .($isHighlighted ? '<div class="tdg-hierarchy-flowchart__node-highlight-ring" aria-hidden="true"></div>' : '')
            .'<header class="tdg-hierarchy-flowchart__node-header">'
            .'<div class="tdg-hierarchy-flowchart__node-icon">'.$iconMarkup.'</div>'
            .'<div class="tdg-hierarchy-flowchart__node-header-text">'
            .'<span class="tdg-hierarchy-flowchart__node-eyebrow">'.e($title).'</span>'
            .'<span class="tdg-hierarchy-flowchart__node-code">'.e((string) ($node['subtitle'] ?? '')).'</span>'
            .'</div>'
            .$statusBadge
            .'</header>'
            .'<h4 class="tdg-hierarchy-flowchart__node-name">'.e($name).'</h4>'
            .$highlightBadge
            .$structureMarkup
            .'</article>';
    }

    private static function nodeInitials(string $name): string
    {
        $trimmed = trim($name);

        if ($trimmed === '') {
            return '—';
        }

        $parts = preg_split('/\s+/u', $trimmed) ?: [];

        if (count($parts) >= 2) {
            return mb_strtoupper(mb_substr((string) $parts[0], 0, 1).mb_substr((string) $parts[1], 0, 1));
        }

        return mb_strtoupper(mb_substr($trimmed, 0, 2));
    }

    private static function hierarchyNodeIconMarkup(string $title, string $tone): string
    {
        $iconKey = match ($title) {
            'Casa matriz' => 'headquarters',
            'Agencia master' => 'master',
            'Agencia general' => 'general',
            default => 'agency',
        };

        return '<span class="tdg-hierarchy-flowchart__node-icon-svg tdg-hierarchy-flowchart__node-icon-svg--'.$iconKey.' tdg-hierarchy-flowchart__node-icon-svg--tone-'.e($tone).'">'
            .self::hierarchyIconSvg($iconKey)
            .'</span>';
    }

    private static function hierarchyIconSvg(string $icon): string
    {
        return match ($icon) {
            'headquarters' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-5" aria-hidden="true"><path d="M4 21V3h2v18H4Zm7-6V3h2v12h-2Zm7 9V3h2v21h-2Z"/></svg>',
            'master' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-5" aria-hidden="true"><path fill-rule="evenodd" d="M4.5 2.25a.75.75 0 0 0-.75.75v18a.75.75 0 0 0 1.5 0V3a.75.75 0 0 0-.75-.75Zm15 0a.75.75 0 0 0-.75.75v18a.75.75 0 0 0 1.5 0V3a.75.75 0 0 0-.75-.75ZM9 5.25a.75.75 0 0 0-.75.75v14a.75.75 0 0 0 1.5 0V6a.75.75 0 0 0-.75-.75Zm6 0a.75.75 0 0 0-.75.75v14a.75.75 0 0 0 1.5 0V6a.75.75 0 0 0-.75-.75Z" clip-rule="evenodd"/></svg>',
            'general' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-5" aria-hidden="true"><path d="M11.47 3.84a.75.75 0 0 1 1.06 0l8.69 8.69a.75.75 0 0 1-.53 1.28H19v6.75A2.25 2.25 0 0 1 16.75 21h-9.5A2.25 2.25 0 0 1 5 18.75V13.8h-1.69a.75.75 0 0 1-.53-1.28l8.69-8.69Z"/></svg>',
            'chevron' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/></svg>',
            'chevron-left' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4" aria-hidden="true"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 0 1-.02 1.06L8.832 10l3.938 3.71a.75.75 0 1 1-1.04 1.08l-4.5-4.25a.75.75 0 0 1 0-1.08l4.5-4.25a.75.75 0 0 1 1.06.02Z" clip-rule="evenodd"/></svg>',
            'chevron-right' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd"/></svg>',
            'empty' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-8 opacity-40" aria-hidden="true"><path fill-rule="evenodd" d="M3 6a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3V6ZM3 15.75a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-2.25Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3v-2.25Z" clip-rule="evenodd"/></svg>',
            default => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-5" aria-hidden="true"><path fill-rule="evenodd" d="M3 6a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3V6Z" clip-rule="evenodd"/></svg>',
        };
    }

    private static function resolveSuperiorAgentForHierarchy(Agent $agent): ?Agent
    {
        $agentTypeId = (int) ($agent->agent_type_id ?? 0);

        if ($agentTypeId !== 3 || ! filled($agent->owner_agent)) {
            return null;
        }

        return Agent::query()
            ->select(['id', 'name', 'status', 'owner_code'])
            ->find((int) $agent->owner_agent);
    }

    /**
     * @return list<string>
     */
    private static function commercialAgencySegmentsForAgent(Agent $agent): array
    {
        $codeAgency = trim((string) ($agent->code_agency ?? ''));

        if ($codeAgency !== '') {
            return self::commercialAgencySegments($codeAgency);
        }

        $superiorAgent = self::resolveSuperiorAgentForHierarchy($agent);
        $agencyCode = trim((string) ($superiorAgent?->owner_code ?? $agent->owner_code ?? ''));

        return self::commercialAgencySegments($agencyCode);
    }

    /**
     * @param  list<string>  $segments
     * @return list<string>
     */
    private static function filterCommercialCodeSegmentsForViewer(array $segments, string $viewerContext): array
    {
        if ($viewerContext === self::VIEWER_FULL) {
            return $segments;
        }

        return array_values(array_filter(
            $segments,
            function (string $segment) use ($viewerContext): bool {
                if ($viewerContext === self::VIEWER_MASTER && str_starts_with($segment, 'AGENCIA MASTER ·')) {
                    return false;
                }

                if ($viewerContext === self::VIEWER_GENERAL && (
                    str_starts_with($segment, 'AGENCIA MASTER ·')
                    || str_starts_with($segment, 'AGENCIA GENERAL ·')
                )) {
                    return false;
                }

                if ($viewerContext === self::VIEWER_AGENT && ! str_starts_with($segment, 'SUB AGENTE ·')) {
                    return false;
                }

                return true;
            },
        ));
    }

    /**
     * @return list<string>
     */
    private static function commercialAgencySegments(string $agencyCode): array
    {
        if ($agencyCode === '') {
            return [];
        }

        $agency = self::resolveAgencyByOwnerCode($agencyCode);

        if (! $agency instanceof Agency) {
            return ['AGENCIA MASTER · '.$agencyCode];
        }

        $agencyTypeId = (int) ($agency->agency_type_id ?? 0);

        if ($agencyTypeId === self::AGENCY_TYPE_GENERAL) {
            $segments = [];
            $masterCode = trim((string) ($agency->owner_code ?? ''));
            $generalCode = trim((string) ($agency->code ?? ''));

            if ($masterCode !== '') {
                $segments[] = 'AGENCIA MASTER · '.$masterCode;
            }

            if ($generalCode !== '') {
                $segments[] = 'AGENCIA GENERAL · '.$generalCode;
            }

            return $segments;
        }

        $masterCode = trim((string) ($agency->code ?? ''));

        return $masterCode !== '' ? ['AGENCIA MASTER · '.$masterCode] : [];
    }

    private static function resolveAgencyByOwnerCode(string $agencyCode): ?Agency
    {
        foreach (self::resolveAgentOwnerCodesForAgencyCode($agencyCode) as $candidate) {
            $agency = Agency::query()
                ->select(['code', 'name_corporative', 'agency_type_id', 'status', 'owner_code'])
                ->whereRaw('UPPER(TRIM(code)) = ?', [strtoupper(trim($candidate))])
                ->first();

            if ($agency instanceof Agency) {
                return $agency;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function resolveAgentOwnerCodesForAgencyCode(string $agencyCode): array
    {
        $trimmed = trim($agencyCode);

        if ($trimmed === '') {
            return [];
        }

        $candidates = [
            $trimmed,
            strtoupper($trimmed),
        ];

        if (preg_match('/^TDG-(\d+)$/i', $trimmed, $matches) === 1) {
            $candidates[] = $matches[1];
        } elseif (preg_match('/^\d+$/', $trimmed) === 1) {
            $candidates[] = 'TDG-'.$trimmed;
        }

        return array_values(array_unique(array_filter(
            $candidates,
            fn (string $candidate): bool => $candidate !== '',
        )));
    }

    /**
     * @param  Builder<Agent>  $query
     * @return Builder<Agent>
     */
    private static function applyAgentOwnerCodeScopeForAgency(Builder $query, string $agencyCode): Builder
    {
        $ownerCodes = self::resolveAgentOwnerCodesForAgencyCode($agencyCode);

        if ($ownerCodes === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(function (Builder $ownerCodeQuery) use ($ownerCodes): void {
            foreach ($ownerCodes as $ownerCode) {
                $ownerCodeQuery->orWhereRaw('UPPER(TRIM(owner_code)) = ?', [strtoupper(trim($ownerCode))]);
            }
        });
    }

    /**
     * @return EloquentCollection<int, Agent>
     */
    private static function agentsForAgencyCode(string $agencyCode): EloquentCollection
    {
        return self::applyAgentOwnerCodeScopeForAgency(
            Agent::query()
                ->select(['id', 'name', 'status', 'agent_type_id', 'owner_agent', 'owner_code']),
            $agencyCode,
        )
            ->orderBy('agent_type_id', 'asc')
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * @return EloquentCollection<int, Agency>
     */
    private static function generalAgenciesUnderMasterCode(string $masterCode): EloquentCollection
    {
        $normalizedMasterCode = strtoupper(trim($masterCode));

        if ($normalizedMasterCode === '') {
            return new EloquentCollection;
        }

        return Agency::query()
            ->select(['code', 'name_corporative', 'agency_type_id', 'status', 'owner_code'])
            ->where('agency_type_id', self::AGENCY_TYPE_GENERAL)
            ->whereRaw('UPPER(TRIM(owner_code)) = ?', [$normalizedMasterCode])
            ->orderBy('code')
            ->get();
    }

    private static function resolveAgencyDisplayName(Agency $agency): string
    {
        $agencyCode = strtoupper(trim((string) ($agency->code ?? '')));

        if ($agencyCode === 'TDG-100') {
            return 'TUDRENCASA';
        }

        return (string) ($agency->name_corporative ?? 'Sin razón social');
    }

    private static function structureSummaryForAgency(Agency $agency): string
    {
        return self::structureSummaryForAgencyCode((string) ($agency->code ?? ''));
    }

    private static function structureSummaryForAgencyCode(string $agencyCode): string
    {
        if (trim($agencyCode) === '') {
            return 'Sin estructura de agentes/subagentes';
        }

        $agentsCount = self::countAgentsForAgencyCode($agencyCode, 2);
        $subagentsCount = self::countAgentsForAgencyCode($agencyCode, 3);

        if ($agentsCount === 0 && $subagentsCount === 0) {
            return 'Sin estructura de agentes/subagentes';
        }

        return "{$agentsCount} agente(s) · {$subagentsCount} subagente(s)";
    }

    private static function countAgentsForAgencyCode(string $agencyCode, int $agentTypeId): int
    {
        return (int) self::applyAgentOwnerCodeScopeForAgency(
            Agent::query()->where('agent_type_id', $agentTypeId),
            $agencyCode,
        )->count();
    }

    private static function renderStatusBadge(string $status): string
    {
        $normalizedStatus = strtoupper(trim($status));

        $statusClass = match ($normalizedStatus) {
            'ACTIVO', 'ACTIVA' => 'bg-emerald-100 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/20 dark:text-emerald-200 dark:ring-emerald-500/30',
            'POR REVISION', 'PENDIENTE' => 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/20 dark:text-amber-200 dark:ring-amber-500/30',
            'INACTIVO', 'INACTIVA' => 'bg-rose-100 text-rose-700 ring-rose-200 dark:bg-rose-500/20 dark:text-rose-200 dark:ring-rose-500/30',
            default => 'bg-slate-100 text-slate-700 ring-slate-200 dark:bg-slate-500/20 dark:text-slate-200 dark:ring-slate-500/30',
        };

        return '<span class="tdg-hierarchy-flowchart__node-status inline-flex shrink-0 items-center rounded-full px-1.5 py-0.5 text-[9px] font-semibold ring-1 whitespace-nowrap '.$statusClass.'">'
            .e($normalizedStatus !== '' ? $normalizedStatus : 'SIN ESTADO')
            .'</span>';
    }
}
