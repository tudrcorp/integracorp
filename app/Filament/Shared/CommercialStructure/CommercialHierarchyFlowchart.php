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

    public static function renderForAgency(Agency $agency): HtmlString
    {
        $context = self::buildHierarchyFlowContext($agency);

        return self::renderDiagramShell($context['levels']);
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
        $linkedAgency = $agencyCode !== '' ? self::resolveAgencyByOwnerCode($agencyCode) : null;

        if (! $linkedAgency instanceof Agency) {
            return self::renderDiagramShell([]);
        }

        $highlightAgentId = (int) ($agent->id ?? 0);
        $highlightAgentId = $highlightAgentId > 0 ? $highlightAgentId : null;
        $context = self::buildHierarchyFlowContext($linkedAgency, $highlightAgentId);

        return self::renderDiagramShell($context['levels']);
    }

    private static function renderDiagramShell(array $levels): HtmlString
    {
        $diagram = '<div class="tdg-hierarchy-flowchart-shell">'
            .'<div class="tdg-hierarchy-flowchart-shell__header">'
            .'<div class="tdg-hierarchy-flowchart-shell__header-text">'
            .'<p class="tdg-hierarchy-flowchart-shell__eyebrow">Estructura comercial</p>'
            .'<h3 class="tdg-hierarchy-flowchart-shell__title">Diagrama de flujo</h3>'
            .'<p class="tdg-hierarchy-flowchart-shell__subtitle">Jerarquía de arriba hacia abajo · Casa matriz, agencias y equipo</p>'
            .'</div>'
            .'<div class="tdg-hierarchy-flowchart-shell__legend" aria-hidden="true">'
            .'<span class="tdg-hierarchy-flowchart-shell__legend-item tdg-hierarchy-flowchart-shell__legend-item--master">Master</span>'
            .'<span class="tdg-hierarchy-flowchart-shell__legend-item tdg-hierarchy-flowchart-shell__legend-item--general">General</span>'
            .'<span class="tdg-hierarchy-flowchart-shell__legend-item tdg-hierarchy-flowchart-shell__legend-item--agent">Agente</span>'
            .'</div>'
            .'</div>'
            .'<div class="tdg-hierarchy-flowchart-shell__canvas">'
            .self::renderHierarchyFlowchart($levels)
            .'</div>'
            .'</div>';

        return new HtmlString($diagram);
    }

    /**
     * @return array{
     *     structure_targets: array<string, string>,
     *     levels: list<array{
     *         layout: 'row'|'column_group',
     *         label?: string,
     *         nodes?: list<array{kind: string, title: string, name: string, subtitle: string, status: string, tone: string, structure: string|null, is_highlighted: bool}>,
     *         columns?: list<array{parent: array{kind: string, title: string, name: string, subtitle: string, status: string, tone: string, structure: string|null, is_highlighted: bool}, children: list<array{kind: string, title: string, name: string, subtitle: string, status: string, tone: string, structure: string|null, is_highlighted: bool}>}>
     *     }>
     * }
     */
    private static function buildHierarchyFlowContext(Agency $agency, ?int $highlightAgentId = null): array
    {
        $structureTargets = [];
        $levels = [];

        $agencyTypeId = (int) ($agency->agency_type_id ?? 0);
        $currentAgencyCode = trim((string) ($agency->code ?? ''));
        $normalizedCurrentCode = strtoupper($currentAgencyCode);

        $masterAgency = null;
        $generalAgencies = new EloquentCollection;

        if ($agencyTypeId === self::AGENCY_TYPE_MASTER) {
            $masterAgency = $agency;
            $ownerCode = strtoupper(trim((string) ($agency->owner_code ?? '')));

            if ($ownerCode === 'TDG-100' && $normalizedCurrentCode !== 'TDG-100') {
                $levels[] = [
                    'layout' => 'row',
                    'label' => 'Casa matriz',
                    'nodes' => [
                        self::hierarchyNodePayload(
                            title: 'Casa matriz',
                            agency: null,
                            tone: 'blue',
                            name: 'TUDRENCASA',
                            subtitle: 'TDG-100',
                            status: 'ACTIVO',
                            structure: self::structureSummaryForAgencyCode('TDG-100'),
                        ),
                    ],
                ];
                $structureTargets['TDG-100'] = 'TUDRENCASA';
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

            if ($masterCode !== '') {
                $structureTargets[$masterCode] = self::resolveAgencyDisplayName($masterAgency);
            }

            $levels[] = [
                'layout' => 'row',
                'label' => 'Nivel 1 · Agencia master',
                'nodes' => [
                    self::hierarchyNodePayload(
                        title: 'Agencia master',
                        agency: $masterAgency,
                        tone: 'emerald',
                        isHighlighted: $agencyTypeId === self::AGENCY_TYPE_MASTER,
                    ),
                ],
            ];

            if ($masterCode !== '') {
                self::appendAgentRowLevel(
                    $levels,
                    $masterCode,
                    'Equipo comercial · Agencia master ('.strtoupper($masterCode).')',
                    $highlightAgentId,
                );
            }
        } elseif ($agencyTypeId === self::AGENCY_TYPE_GENERAL) {
            if ($currentAgencyCode !== '') {
                $structureTargets[$currentAgencyCode] = self::resolveAgencyDisplayName($agency);
            }

            if (! $masterAgency instanceof Agency) {
                $levels[] = self::buildAgencyColumnGroupLevel(
                    agencies: new EloquentCollection([$agency]),
                    agencyTypeId: $agencyTypeId,
                    normalizedCurrentAgencyCode: $normalizedCurrentCode,
                    structureTargets: $structureTargets,
                    label: 'Nivel 2 · Agencia general y equipo',
                    highlightAgentId: $highlightAgentId,
                );
            }
        }

        if ($generalAgencies->isNotEmpty()) {
            $levels[] = self::buildAgencyColumnGroupLevel(
                agencies: $generalAgencies,
                agencyTypeId: $agencyTypeId,
                normalizedCurrentAgencyCode: $normalizedCurrentCode,
                structureTargets: $structureTargets,
                label: count($generalAgencies) > 1
                    ? 'Nivel 2 · Agencias generales y equipos'
                    : 'Nivel 2 · Agencia general y equipo',
                highlightAgentId: $highlightAgentId,
            );
        }

        if ($levels === []) {
            $levels[] = [
                'layout' => 'row',
                'label' => 'Agencia',
                'nodes' => [
                    self::hierarchyNodePayload(
                        title: 'Agencia',
                        agency: $agency,
                        tone: 'blue',
                        isHighlighted: true,
                    ),
                ],
            ];

            if ($currentAgencyCode !== '') {
                $structureTargets[$currentAgencyCode] = self::resolveAgencyDisplayName($agency);
                self::appendAgentRowLevel(
                    $levels,
                    $currentAgencyCode,
                    'Equipo comercial · '.strtoupper($currentAgencyCode),
                    $highlightAgentId,
                );
            }
        }

        return [
            'structure_targets' => $structureTargets,
            'levels' => $levels,
        ];
    }

    /**
     * @param  array<string, string>  $structureTargets
     * @return array{
     *     layout: 'column_group',
     *     label: string,
     *     columns: list<array{parent: array{kind: string, title: string, name: string, subtitle: string, status: string, tone: string, structure: string|null, is_highlighted: bool}, children: list<array{kind: string, title: string, name: string, subtitle: string, status: string, tone: string, structure: string|null, is_highlighted: bool}>}>
     * }
     */
    private static function buildAgencyColumnGroupLevel(
        EloquentCollection $agencies,
        int $agencyTypeId,
        string $normalizedCurrentAgencyCode,
        array &$structureTargets,
        string $label,
        ?int $highlightAgentId = null,
    ): array {
        $columns = [];

        foreach ($agencies as $generalAgency) {
            $generalCode = trim((string) ($generalAgency->code ?? ''));

            if ($generalCode !== '') {
                $structureTargets[$generalCode] = self::resolveAgencyDisplayName($generalAgency);
            }

            $columns[] = [
                'parent' => self::hierarchyNodePayload(
                    title: 'Agencia general',
                    agency: $generalAgency,
                    tone: 'amber',
                    isHighlighted: $agencyTypeId === self::AGENCY_TYPE_GENERAL
                        && strtoupper($generalCode) === $normalizedCurrentAgencyCode,
                ),
                'children' => self::buildAgentFlowNodes($generalCode, $highlightAgentId),
            ];
        }

        return [
            'layout' => 'column_group',
            'label' => $label,
            'columns' => $columns,
        ];
    }

    /**
     * @param  list<array{layout: string, label?: string, nodes?: list<array<string, mixed>>, columns?: list<array<string, mixed>>}>  $levels
     */
    private static function appendAgentRowLevel(array &$levels, string $agencyCode, string $label, ?int $highlightAgentId = null): void
    {
        $agentNodes = self::buildAgentFlowNodes($agencyCode, $highlightAgentId);

        if ($agentNodes === []) {
            return;
        }

        $levels[] = [
            'layout' => 'row',
            'label' => $label,
            'nodes' => $agentNodes,
        ];
    }

    /**
     * @return list<array{kind: string, title: string, name: string, subtitle: string, status: string, tone: string, structure: string|null, is_highlighted: bool}>
     */
    private static function buildAgentFlowNodes(string $agencyCode, ?int $highlightAgentId = null): array
    {
        if (trim($agencyCode) === '') {
            return [];
        }

        return self::agentsForAgencyCode($agencyCode)
            ->map(fn (Agent $agent): array => self::hierarchyAgentNodePayload($agent, $highlightAgentId))
            ->values()
            ->all();
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
     * @return array{kind: string, title: string, name: string, subtitle: string, status: string, tone: string, structure: string|null, is_highlighted: bool}
     */
    private static function hierarchyAgentNodePayload(Agent $agent, ?int $highlightAgentId = null): array
    {
        $agentTypeId = (int) ($agent->agent_type_id ?? 0);
        $structure = null;

        if ($agentTypeId === 3 && filled($agent->owner_agent)) {
            $structure = 'Superior: AGT-000'.(string) $agent->owner_agent;
        }

        $agentId = (int) ($agent->id ?? 0);

        return [
            'kind' => 'agent',
            'title' => $agentTypeId === 3 ? 'Subagente' : 'Agente',
            'name' => (string) ($agent->name ?? 'Sin nombre'),
            'subtitle' => 'AGT-000'.$agentId,
            'status' => (string) ($agent->status ?? 'Sin estado'),
            'tone' => $agentTypeId === 3 ? 'slate' : 'violet',
            'structure' => $structure,
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
        return [
            'kind' => 'agency',
            'title' => $title,
            'name' => $name ?? ($agency instanceof Agency ? self::resolveAgencyDisplayName($agency) : 'Sin razón social'),
            'subtitle' => $subtitle ?? ($agency instanceof Agency ? trim((string) ($agency->code ?? 'Sin código')) : 'Sin código'),
            'status' => $status ?? ($agency instanceof Agency ? (string) ($agency->status ?? 'Sin estado') : 'Sin estado'),
            'tone' => $tone,
            'structure' => $structure ?? ($agency instanceof Agency ? self::structureSummaryForAgency($agency) : null),
            'is_highlighted' => $isHighlighted,
        ];
    }

    /**
     * @param  list<array{layout: string, label?: string, nodes?: list<array<string, mixed>>, columns?: list<array<string, mixed>>}>  $levels
     */
    private static function renderHierarchyFlowchart(array $levels): string
    {
        if ($levels === []) {
            return '<div class="tdg-hierarchy-flowchart__empty">'
                .self::hierarchyIconSvg('empty')
                .'<p>Sin datos de jerarquía para mostrar.</p>'
                .'</div>';
        }

        $html = '<div class="tdg-hierarchy-flowchart">';

        foreach ($levels as $levelIndex => $level) {
            $layout = (string) ($level['layout'] ?? 'row');
            $branchCount = $layout === 'column_group'
                ? count($level['columns'] ?? [])
                : count($level['nodes'] ?? []);

            if ($levelIndex > 0) {
                $html .= self::renderHierarchyFlowConnector($branchCount);
            }

            if ($layout === 'column_group') {
                $html .= self::renderHierarchyColumnGroupLevel($level, $levelIndex + 1);
            } else {
                $html .= self::renderHierarchyRowLevel($level, $levelIndex + 1);
            }
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * @param  array{layout?: string, label?: string, nodes?: list<array<string, mixed>>}  $level
     */
    private static function renderHierarchyRowLevel(array $level, int $stepNumber): string
    {
        $levelNodes = $level['nodes'] ?? [];
        $isBranchLevel = count($levelNodes) > 1;
        $levelLabel = $level['label'] ?? self::resolveHierarchyLevelLabel($levelNodes);

        $html = '<div class="tdg-hierarchy-flowchart__level'.($isBranchLevel ? ' tdg-hierarchy-flowchart__level--branch' : '').'">';

        if ($levelLabel !== null && $levelLabel !== '') {
            $html .= self::renderLevelLabel($stepNumber, $levelLabel);
        }

        if ($isBranchLevel) {
            $html .= '<div class="tdg-hierarchy-flowchart__branch-rail" aria-hidden="true"></div>';
        }

        $html .= '<div class="tdg-hierarchy-flowchart__nodes">';

        foreach ($levelNodes as $node) {
            $html .= self::renderHierarchyFlowNode($node);
        }

        return $html.'</div></div>';
    }

    /**
     * @param  array{layout?: string, label?: string, columns?: list<array{parent: array<string, mixed>, children: list<array<string, mixed>>}>}  $level
     */
    private static function renderHierarchyColumnGroupLevel(array $level, int $stepNumber): string
    {
        $columns = $level['columns'] ?? [];
        $levelLabel = $level['label'] ?? 'Agencias y equipos';
        $isBranchLevel = count($columns) > 1;

        $html = '<div class="tdg-hierarchy-flowchart__level tdg-hierarchy-flowchart__level--columns'.($isBranchLevel ? ' tdg-hierarchy-flowchart__level--branch' : '').'">'
            .self::renderLevelLabel($stepNumber, $levelLabel);

        if ($isBranchLevel) {
            $html .= '<div class="tdg-hierarchy-flowchart__branch-rail" aria-hidden="true"></div>';
        }

        $html .= '<div class="tdg-hierarchy-flowchart__columns">';

        foreach ($columns as $column) {
            $parent = $column['parent'] ?? [];
            $children = $column['children'] ?? [];

            $html .= '<div class="tdg-hierarchy-flowchart__column">'
                .self::renderHierarchyFlowNode($parent);

            if ($children !== []) {
                $html .= '<div class="tdg-hierarchy-flowchart__column-connector" aria-hidden="true">'
                    .'<span class="tdg-hierarchy-flowchart__connector-line"></span>'
                    .'<span class="tdg-hierarchy-flowchart__connector-dot"></span>'
                    .'</div>'
                    .'<div class="tdg-hierarchy-flowchart__column-agents">'
                    .'<span class="tdg-hierarchy-flowchart__column-agents-label">Equipo</span>';

                foreach ($children as $childNode) {
                    $html .= self::renderHierarchyFlowNode($childNode);
                }

                $html .= '</div>';
            }

            $html .= '</div>';
        }

        return $html.'</div></div>';
    }

    private static function renderHierarchyFlowConnector(int $childCount = 1): string
    {
        $branchClass = $childCount > 1 ? ' tdg-hierarchy-flowchart__connector--branch' : '';

        return '<div class="tdg-hierarchy-flowchart__connector'.$branchClass.'" aria-hidden="true">'
            .'<span class="tdg-hierarchy-flowchart__connector-line"></span>'
            .'<span class="tdg-hierarchy-flowchart__connector-dot"></span>'
            .'</div>';
    }

    private static function renderLevelLabel(int $stepNumber, string $label): string
    {
        return '<div class="tdg-hierarchy-flowchart__level-label">'
            .'<span class="tdg-hierarchy-flowchart__level-step">'.e((string) $stepNumber).'</span>'
            .'<span class="tdg-hierarchy-flowchart__level-text">'.e($label).'</span>'
            .'</div>';
    }

    /**
     * @param  list<array<string, mixed>>  $levelNodes
     */
    private static function resolveHierarchyLevelLabel(array $levelNodes): ?string
    {
        $firstNode = $levelNodes[0] ?? [];
        $kind = (string) ($firstNode['kind'] ?? 'agency');
        $title = (string) ($firstNode['title'] ?? '');

        if ($kind === 'agent') {
            return count($levelNodes) > 1
                ? 'Equipo comercial'
                : 'Agente / subagente';
        }

        return match ($title) {
            'Casa matriz' => 'Casa matriz',
            'Agencia master' => 'Nivel 1 · Agencia master',
            'Agencia general' => count($levelNodes) > 1
                ? 'Nivel 2 · Agencias generales'
                : 'Nivel 2 · Agencia general',
            default => null,
        };
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
        $highlightClass = $isHighlighted ? ' tdg-hierarchy-flowchart__node--highlighted' : '';
        $statusBadge = self::renderStatusBadge((string) ($node['status'] ?? 'Sin estado'));
        $iconMarkup = $kind === 'agent'
            ? '<span class="tdg-hierarchy-flowchart__node-avatar">'.e(self::nodeInitials($name)).'</span>'
            : self::hierarchyNodeIconMarkup($title, $tone);

        $structureMarkup = $node['structure'] !== null
            ? '<p class="tdg-hierarchy-flowchart__node-meta">'.e((string) $node['structure']).'</p>'
            : '';

        return '<article class="tdg-hierarchy-flowchart__node '.$tonePalette.$kindClass.$highlightClass.'">'
            .'<div class="tdg-hierarchy-flowchart__node-glow" aria-hidden="true"></div>'
            .'<header class="tdg-hierarchy-flowchart__node-header">'
            .'<div class="tdg-hierarchy-flowchart__node-icon">'.$iconMarkup.'</div>'
            .'<div class="tdg-hierarchy-flowchart__node-header-text">'
            .'<span class="tdg-hierarchy-flowchart__node-eyebrow">'.e($title).'</span>'
            .'<span class="tdg-hierarchy-flowchart__node-code">'.e((string) ($node['subtitle'] ?? '')).'</span>'
            .'</div>'
            .$statusBadge
            .'</header>'
            .'<h4 class="tdg-hierarchy-flowchart__node-name">'.e($name).'</h4>'
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
            'agent' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-5" aria-hidden="true"><path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a.75.75 0 0 1 .664-.991 16.5 16.5 0 0 1 16.17 0 .75.75 0 0 1-.664.991 15.001 15.001 0 0 0-15.472 0Z" clip-rule="evenodd"/></svg>',
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
     * Códigos posibles en agents.owner_code para una agencia (p. ej. TDG-104 y 104).
     *
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

        return "Agentes: {$agentsCount} | Subagentes: {$subagentsCount}";
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

        return '<span class="tdg-hierarchy-flowchart__node-status inline-flex shrink-0 items-center rounded-full px-2 py-1 text-[10px] font-semibold ring-1 whitespace-nowrap '.$statusClass.'">'
            .e($normalizedStatus !== '' ? $normalizedStatus : 'SIN ESTADO')
            .'</span>';
    }
}
