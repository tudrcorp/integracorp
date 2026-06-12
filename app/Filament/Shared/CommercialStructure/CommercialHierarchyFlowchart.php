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
    private const AGENCY_TYPE_MASTER = 1;

    private const AGENCY_TYPE_GENERAL = 3;

    private const SLIDER_THRESHOLD = 5;

    private static int $sliderSequence = 0;

    public static function renderForAgency(Agency $agency): HtmlString
    {
        $tree = self::buildInteractiveHierarchyTree($agency);

        return self::renderDiagramShell($tree);
    }

    public static function renderForAgent(Agent $agent): HtmlString
    {
        $superiorAgent = null;
        $agentTypeId = (int) ($agent->agent_type_id ?? 0);

        if ($agentTypeId === 3 && filled($agent->owner_agent)) {
            $superiorAgent = Agent::query()
                ->select(['id', 'name', 'status', 'owner_code'])
                ->find((int) $agent->owner_agent);
        }

        $agencyCode = trim((string) ($superiorAgent?->owner_code ?? $agent->owner_code ?? ''));
        $linkedAgency = $agencyCode !== '' ? self::resolveAgencyByOwnerCode($agencyCode) : null;

        if (! $linkedAgency instanceof Agency) {
            return self::renderDiagramShell([]);
        }

        $highlightAgentId = (int) ($agent->id ?? 0);
        $highlightAgentId = $highlightAgentId > 0 ? $highlightAgentId : null;
        $tree = self::buildInteractiveHierarchyTree($linkedAgency, $highlightAgentId);

        return self::renderDiagramShell($tree);
    }

    /**
     * @param  array{
     *     headquarters?: array<string, mixed>|null,
     *     master?: array<string, mixed>|null,
     *     master_direct_agents?: list<array{agent: array<string, mixed>, subagents: list<array<string, mixed>>}>,
     *     generals?: list<array{agency: array<string, mixed>, agents: list<array{agent: array<string, mixed>, subagents: list<array<string, mixed>>}>}>
     * }  $tree
     */
    private static function renderDiagramShell(array $tree): HtmlString
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
            .self::renderInteractiveHierarchyTree($tree)
            .'</div>'
            .'</div>';

        return new HtmlString($diagram);
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
     */
    private static function renderInteractiveHierarchyTree(array $tree): string
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

        $html = '<div class="tdg-hierarchy-flowchart tdg-hierarchy-flowchart--interactive" x-data="{ masterAgentsOpen: false, activeGeneralBranch: null, activeSubagentBranch: null, toggleGeneralAgents(branchKey) { this.masterAgentsOpen = false; this.activeSubagentBranch = null; this.activeGeneralBranch = this.activeGeneralBranch === branchKey ? null : branchKey; }, toggleMasterAgents() { this.activeGeneralBranch = null; this.activeSubagentBranch = null; this.masterAgentsOpen = ! this.masterAgentsOpen; }, toggleSubagents(branchKey) { this.activeSubagentBranch = this.activeSubagentBranch === branchKey ? null : branchKey; } }">';

        if (($tree['headquarters'] ?? null) !== null) {
            $html .= self::renderHierarchyTier('Matriz', self::renderHierarchyFlowNode($tree['headquarters']));
        }

        if (($tree['master'] ?? null) !== null) {
            if (($tree['headquarters'] ?? null) !== null) {
                $html .= self::renderHierarchyFlowConnector();
            }

            $html .= self::renderHierarchyTier('Master', self::renderMasterHierarchyNode($tree));

            $masterAgents = $tree['master_direct_agents'] ?? [];

            if ($masterAgents !== []) {
                $html .= self::renderExpandableAgentBranch(
                    label: count($masterAgents).' agente(s) directo(s) de master',
                    agents: $masterAgents,
                    alpineToggle: 'masterAgentsOpen',
                    branchKey: 'master-direct',
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

        $html = '<div class="tdg-hierarchy-flowchart__general-stack" :class="{ \'is-active\': '.$openExpression.' }" data-general-branch="'.$branchKey.'">'
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
    ): string {
        return self::renderExpandableConnector(
            label: $label,
            openExpression: 'masterAgentsOpen',
            toggleClick: 'toggleMasterAgents()',
            sectionLabel: 'Equipo directo',
            panelContent: self::renderAgentTreeCollection($agents, $branchKey),
            tone: 'emerald',
            horizontalAgentsLayout: true,
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
    ): string {
        $nestedClass = $nested ? ' tdg-hierarchy-flowchart__expandable--nested' : '';
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

        $triggerHtml = self::renderHierarchyFlowConnector()
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
                .'<div class="'.$panelWrapperClass.'"'.$sourceKeyAttr.' x-show="'.$openExpression.'" x-collapse>'
                .'<div class="tdg-hierarchy-flowchart__branch-section tdg-hierarchy-flowchart__branch-section--horizontal">'
                .'<span class="tdg-hierarchy-flowchart__branch-section-label">'.e($sectionLabel).'</span>'
                .$panelContent
                .'</div>'
                .'</div>'
                .'</template>';

            return '<div class="tdg-hierarchy-flowchart__expandable'.$nestedClass.$teleportClass.$horizontalLayoutClass.'" :class="{ \'is-open\': '.$openExpression.' }">'
                .$triggerHtml
                .$panelHtml
                .'</div>';
        }

        return '<div class="tdg-hierarchy-flowchart__expandable'.$nestedClass.$teleportClass.$horizontalLayoutClass.'" :class="{ \'is-open\': '.$openExpression.' }">'
            .$triggerHtml
            .'<div class="tdg-hierarchy-flowchart__expand-panel" x-show="'.$openExpression.'" x-collapse>'
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

        $html = '<div class="tdg-hierarchy-flowchart__agent-branch" :class="{ \'is-active\': '.$openExpression.' }">'
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
            return '<div class="tdg-hierarchy-flowchart__nodes tdg-hierarchy-flowchart__nodes--inline">'
                .implode('', $nodeHtmlBlocks)
                .'</div>';
        }

        $sliderId = 'hierarchy-slider-'.(++self::$sliderSequence).'-'.substr(md5($collectionKey), 0, 8);
        $sliderAlpine = '{ canScrollPrev: false, canScrollNext: true, counterLabel: \'1 / '.$count.'\', '
            .'updateScrollState(el) { if (! el) return; const max = el.scrollWidth - el.clientWidth; this.canScrollPrev = el.scrollLeft > 4; this.canScrollNext = el.scrollLeft < max - 4; '
            .'const idx = Math.round(el.scrollLeft / Math.max(el.clientWidth * 0.82, 1)) + 1; this.counterLabel = idx + \' / '.$count.'\'; }, '
            .'scrollPrev(el) { if (! el) return; el.scrollBy({ left: -(el.clientWidth * 0.82), behavior: \'smooth\' }); }, '
            .'scrollNext(el) { if (! el) return; el.scrollBy({ left: el.clientWidth * 0.82, behavior: \'smooth\' }); } }';

        $html = '<div class="tdg-hierarchy-slider" x-data="'.$sliderAlpine.'" x-init="updateScrollState($refs.viewport)" id="'.$sliderId.'">'
            .'<div class="tdg-hierarchy-slider__controls">'
            .'<button type="button" class="tdg-hierarchy-slider__btn" @click="scrollPrev($refs.viewport)" :disabled="!canScrollPrev" aria-label="Anterior">'
            .self::hierarchyIconSvg('chevron-left')
            .'</button>'
            .'<span class="tdg-hierarchy-slider__counter" x-text="counterLabel"></span>'
            .'<button type="button" class="tdg-hierarchy-slider__btn" @click="scrollNext($refs.viewport)" :disabled="!canScrollNext" aria-label="Siguiente">'
            .self::hierarchyIconSvg('chevron-right')
            .'</button>'
            .'</div>'
            .'<div class="tdg-hierarchy-slider__viewport" x-ref="viewport" @scroll="updateScrollState($refs.viewport)">'
            .'<div class="tdg-hierarchy-slider__track">';

        foreach ($nodeHtmlBlocks as $block) {
            $html .= '<div class="tdg-hierarchy-slider__slide">'.$block.'</div>';
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

    private static function renderHierarchyTier(string $label, string $content, ?string $tierKey = null): string
    {
        $tierClass = $tierKey !== null
            ? ' tdg-hierarchy-flowchart__tier--'.$tierKey
            : '';
        $agentsDock = $tierKey === 'general'
            ? '<div class="tdg-hierarchy-flowchart__general-agents-dock" id="hierarchy-general-agents-dock"></div>'
            : '';

        return '<div class="tdg-hierarchy-flowchart__tier'.$tierClass.'">'
            .'<div class="tdg-hierarchy-flowchart__tier-label">'
            .'<span class="tdg-hierarchy-flowchart__tier-label-text">'.e($label).'</span>'
            .'</div>'
            .'<div class="tdg-hierarchy-flowchart__tier-body">'.$content.$agentsDock.'</div>'
            .'</div>';
    }

    private static function renderHierarchyFlowConnector(int $childCount = 1): string
    {
        $branchClass = $childCount > 1 ? ' tdg-hierarchy-flowchart__connector--branch' : '';

        return '<div class="tdg-hierarchy-flowchart__connector'.$branchClass.'" aria-hidden="true">'
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
        $highlightClass = $isHighlighted ? ' tdg-hierarchy-flowchart__node--highlighted' : '';
        $statusBadge = self::renderStatusBadge((string) ($node['status'] ?? 'Sin estado'));
        $iconMarkup = $kind === 'agent'
            ? '<span class="tdg-hierarchy-flowchart__node-avatar">'.e(self::nodeInitials($name)).'</span>'
            : self::hierarchyNodeIconMarkup($title, $tone);

        $structure = $node['structure'] ?? null;
        $structureMarkup = filled($structure) && $structure !== 'Sin estructura de agentes/subagentes'
            ? '<p class="tdg-hierarchy-flowchart__node-meta">'.e((string) $structure).'</p>'
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
            'chevron' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/></svg>',
            'chevron-left' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4" aria-hidden="true"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 0 1-.02 1.06L8.832 10l3.938 3.71a.75.75 0 1 1-1.04 1.08l-4.5-4.25a.75.75 0 0 1 0-1.08l4.5-4.25a.75.75 0 0 1 1.06.02Z" clip-rule="evenodd"/></svg>',
            'chevron-right' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="size-4" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd"/></svg>',
            'empty' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-8 opacity-40" aria-hidden="true"><path fill-rule="evenodd" d="M3 6a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3V6ZM3 15.75a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-2.25Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3V18a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3v-2.25Z" clip-rule="evenodd"/></svg>',
            default => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-5" aria-hidden="true"><path fill-rule="evenodd" d="M3 6a3 3 0 0 1 3-3h2.25a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V6Zm9.75 0a3 3 0 0 1 3-3H18a3 3 0 0 1 3 3v2.25a3 3 0 0 1-3 3h-2.25a3 3 0 0 1-3-3V6Z" clip-rule="evenodd"/></svg>',
        };
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
