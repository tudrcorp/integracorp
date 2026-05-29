<?php

declare(strict_types=1);

it('construye payload de timeline agrupado por proyecto con semanas y barras', function (): void {
    $timelinePath = dirname(__DIR__, 2).'/app/Support/Filament/ProjectManagement/ProjectManagementKanbanTimeline.php';
    $componentPath = dirname(__DIR__, 2).'/resources/views/components/projects/kanban-timeline.blade.php';

    expect(file_exists($timelinePath))->toBeTrue();
    expect(file_exists($componentPath))->toBeTrue();

    expect(file_get_contents($timelinePath))
        ->toContain('final class ProjectManagementKanbanTimeline')
        ->toContain('public static function build(Collection $activities): array')
        ->toContain('buildWeeks(Carbon $rangeStart, array $days)')
        ->toContain("'label' => \$weekStart->format('d M').' - '.\$weekEnd->format('d M')")
        ->toContain("'is_milestone' => \$isMilestone")
        ->toContain('phase_start')
        ->toContain('phase_span')
        ->toContain('ProjectManagementActivityAssignmentDisplay::for')
        ->toContain("'visible_members' => \$assignment['visible_members']")
        ->toContain("'overflow_count' => \$assignment['overflow_count']");

    expect(file_get_contents($componentPath))
        ->toContain('kanban-timeline')
        ->toContain('kanban-timeline-today-marker')
        ->toContain('kanban-timeline-today-line')
        ->toContain('kanban-timeline-today-glow')
        ->toContain('kanban-timeline-grid-overlay')
        ->toContain('kanban-timeline-sidebar--project')
        ->toContain('kanban-timeline-bar__sheen')
        ->toContain('kanban-timeline-phase-rail')
        ->toContain('kanban-timeline-status--todo')
        ->toContain(':is(.dark, .dark *) .kanban-timeline')
        ->toContain('kanban-timeline-day-cell--today')
        ->toContain('--tl-weekend-wash')
        ->toContain('Hoy')
        ->toContain('kanban-timeline-bar')
        ->toContain('collaborator-avatar-stack')
        ->toContain('kanban-timeline-bar__avatars')
        ->toContain('overflow-count')
        ->toContain('kanban-timeline-phase')
        ->toContain('is_milestone');
});

it('expone timeline en kanban con cambio de vista board y timeline', function (): void {
    $pagePath = dirname(__DIR__, 2).'/app/Filament/Projects/Pages/Kanban.php';
    $viewPath = dirname(__DIR__, 2).'/resources/views/filament/projects/pages/kanban.blade.php';

    expect(file_get_contents($pagePath))
        ->toContain("public string \$viewMode = 'board'")
        ->toContain('getTimelinePayloadProperty')
        ->toContain('ProjectManagementKanbanTimeline::build')
        ->toContain("in_array(\$viewMode, ['board', 'timeline', 'files'], true)");

    expect(file_get_contents($viewPath))
        ->toContain("wire:click=\"setViewMode('board')\"")
        ->toContain("wire:click=\"setViewMode('timeline')\"")
        ->toContain("\$viewMode === 'board'")
        ->toContain('x-projects.kanban-timeline')
        ->toContain(':timeline="$this->timelinePayload"');
});
