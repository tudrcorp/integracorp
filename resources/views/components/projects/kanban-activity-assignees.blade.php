@props([
    'activity',
    'inline' => false,
    'align' => 'start',
])

@php
    use App\Support\Filament\ProjectManagement\ProjectManagementActivityAssignmentDisplay;

    $assignment = $activity->kanban_assignment ?? ProjectManagementActivityAssignmentDisplay::for($activity);

    $tooltipItems = collect($assignment['all_members'] ?? $assignment['visible_members'] ?? [])
        ->map(fn (array $member): array => ['name' => $member['name']])
        ->all();

    $tooltipTitle = ($assignment['total_count'] ?? 0) > 0
        ? trim($assignment['heading'].' · '.$assignment['title'])
        : null;
@endphp

<x-collaborator-avatar-stack
    @class([
        'kanban-activity-assignees',
        'mt-3' => ! $inline,
        'shrink-0' => $inline,
    ])
    :align="$align"
    :avatars="$assignment['visible_members'] ?? []"
    :overflow-count="$assignment['overflow_count'] ?? 0"
    :tooltip-title="$tooltipTitle"
    :tooltip-items="$tooltipItems"
/>
