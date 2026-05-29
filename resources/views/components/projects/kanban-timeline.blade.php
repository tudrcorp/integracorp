@props([
    'timeline',
])

@php
    $dayCount = (int) ($timeline['day_count'] ?? 0);
    $todayIndex = $timeline['today_index'] ?? null;
    $todayLabel = now()->translatedFormat('j');

    $weekBoundaryIndices = [];
    $weekCursor = 0;

    foreach ($timeline['weeks'] ?? [] as $week) {
        $weekBoundaryIndices[] = $weekCursor;
        $weekCursor += (int) ($week['span'] ?? 0);
    }

    $weekendGradientStops = collect($timeline['days'] ?? [])
        ->map(fn (array $day, int $index): ?string => $day['is_weekend']
            ? sprintf(
                'var(--tl-weekend-wash) calc(var(--timeline-day-width) * %d), var(--tl-weekend-wash) calc(var(--timeline-day-width) * %d + var(--timeline-day-width))',
                $index,
                $index,
            )
            : null)
        ->filter()
        ->implode(', ');

    $statusTone = static function (string $label): string {
        return match ($label) {
            'En progreso' => 'kanban-timeline-status--progress',
            'En revisión' => 'kanban-timeline-status--review',
            'Finalizada' => 'kanban-timeline-status--done',
            default => 'kanban-timeline-status--todo',
        };
    };
@endphp

<section
    class="kanban-timeline overflow-hidden rounded-3xl"
    style="--timeline-days: {{ max($dayCount, 1) }}; @if ($todayIndex !== null) --today-index: {{ $todayIndex }}; @endif @if ($weekendGradientStops !== '') --timeline-weekend-wash: linear-gradient(90deg, {{ $weekendGradientStops }}); @endif"
>
    <div class="kanban-timeline-scroll overflow-x-auto">
        <div class="kanban-timeline-canvas relative min-w-max">
            @if ($todayIndex !== null)
                <div class="kanban-timeline-today-marker pointer-events-none absolute inset-0 z-30" aria-hidden="true">
                    <div class="kanban-timeline-today-glow"></div>
                    <div class="kanban-timeline-today-line">
                        <span class="kanban-timeline-today-dot kanban-timeline-today-dot--top"></span>
                        <span class="kanban-timeline-today-dot kanban-timeline-today-dot--bottom"></span>
                    </div>
                    <span class="kanban-timeline-today-badge">
                        Hoy {{ $todayLabel }}
                    </span>
                </div>
            @endif

            <div class="kanban-timeline-grid-overlay pointer-events-none absolute inset-0 z-[5]" aria-hidden="true"></div>

            <div class="kanban-timeline-grid sticky top-0 z-20 backdrop-blur-xl">
                <div class="kanban-timeline-weeks grid" style="grid-template-columns: var(--timeline-sidebar) repeat({{ $dayCount }}, minmax(3rem, 1fr));">
                    <div class="kanban-timeline-sidebar-header kanban-timeline-sidebar-header--weeks flex items-center gap-2 px-4 py-3">
                        <span class="kanban-timeline-icon-box flex size-6 items-center justify-center rounded-lg">
                            <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.75 3a.75.75 0 0 0-.75.75v12.5c0 .414.336.75.75.75h8.5a.75.75 0 0 0 .75-.75V3.75a.75.75 0 0 0-.75-.75h-8.5ZM4.5 3.75A2.25 2.25 0 0 1 6.75 1.5h6.5A2.25 2.25 0 0 1 15.5 3.75v12.5a2.25 2.25 0 0 1-2.25 2.25h-6.5A2.25 2.25 0 0 1 4.5 16.25V3.75Z" clip-rule="evenodd" />
                            </svg>
                        </span>
                        <span class="kanban-timeline-label uppercase tracking-[0.2em]">Timeline</span>
                    </div>
                    @foreach ($timeline['weeks'] ?? [] as $week)
                        <div
                            class="kanban-timeline-week-cell border-l px-2 py-3 text-center text-[11px] font-medium tracking-wide"
                            style="grid-column: span {{ $week['span'] }};"
                        >
                            {{ $week['label'] }}
                        </div>
                    @endforeach
                </div>

                <div class="kanban-timeline-days grid" style="grid-template-columns: var(--timeline-sidebar) repeat({{ $dayCount }}, minmax(3rem, 1fr));">
                    <div class="kanban-timeline-sidebar-header px-4 py-2.5 text-[11px] font-medium">Actividades</div>
                    @foreach ($timeline['days'] ?? [] as $index => $day)
                        <div @class([
                            'kanban-timeline-day-cell relative border-l py-2.5 text-center text-xs font-semibold tabular-nums',
                            'kanban-timeline-day-cell--today' => $day['is_today'],
                            'kanban-timeline-day-cell--week-start' => ! $day['is_today'] && in_array($index, $weekBoundaryIndices, true),
                            'kanban-timeline-day-cell--weekend' => $day['is_weekend'] && ! $day['is_today'],
                        ])>
                            {{ $day['label'] }}
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="kanban-timeline-body relative">
                @forelse ($timeline['groups'] ?? [] as $group)
                    @php
                        $projectColor = $group['project_color'];
                        $projectInitial = mb_strtoupper(mb_substr($group['project_name'], 0, 1));
                    @endphp

                    <div class="kanban-timeline-group">
                        <div
                            class="kanban-timeline-phase grid min-h-[3.75rem] items-center"
                            style="grid-template-columns: var(--timeline-sidebar) repeat({{ $dayCount }}, minmax(3rem, 1fr)); --project-color: {{ $projectColor }};"
                        >
                            <div class="kanban-timeline-sidebar kanban-timeline-sidebar--project px-3 py-3">
                                <div class="flex items-start gap-3">
                                    <span
                                        class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl text-xs font-bold text-white shadow-lg ring-1 ring-black/10"
                                        style="background: linear-gradient(145deg, {{ $projectColor }}, color-mix(in srgb, {{ $projectColor }} 72%, #000)); box-shadow: 0 8px 20px -8px color-mix(in srgb, {{ $projectColor }} 75%, transparent);"
                                    >
                                        {{ $projectInitial }}
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="kanban-timeline-title truncate text-sm font-semibold tracking-tight">{{ $group['project_name'] }}</p>
                                        <p class="kanban-timeline-count-badge mt-1 inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[10px] font-medium">
                                            <span class="size-1.5 rounded-full" style="background: {{ $projectColor }};"></span>
                                            {{ count($group['rows']) }} {{ count($group['rows']) === 1 ? 'actividad' : 'actividades' }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="kanban-timeline-phase-rail mx-2"
                                style="grid-column: {{ $group['phase_start'] + 2 }} / span {{ $group['phase_span'] }};"
                            >
                                <span class="kanban-timeline-phase-rail__glow"></span>
                                <span class="kanban-timeline-phase-rail__track"></span>
                            </div>
                        </div>

                        @foreach ($group['rows'] as $row)
                            @php
                                $statusClass = $statusTone($row['status_label']);
                            @endphp

                            <div
                                class="kanban-timeline-row grid min-h-[3.75rem] items-center"
                                style="grid-template-columns: var(--timeline-sidebar) repeat({{ $dayCount }}, minmax(3rem, 1fr)); --bar-color: {{ $row['color'] }};"
                            >
                                <div class="kanban-timeline-sidebar kanban-timeline-sidebar--row px-4 py-2">
                                    <span @class([
                                        'kanban-timeline-status inline-flex max-w-full items-center gap-1.5 truncate rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.12em] ring-1 ring-inset',
                                        $statusClass,
                                    ])>
                                        <span class="kanban-timeline-status__dot size-1.5 shrink-0 rounded-full"></span>
                                        {{ $row['status_label'] }}
                                    </span>
                                </div>

                                @if ($row['is_milestone'])
                                    <div
                                        class="kanban-timeline-milestone relative flex items-center gap-2.5 px-1"
                                        style="grid-column: {{ $row['start_index'] + 2 }};"
                                    >
                                        <span class="kanban-timeline-milestone__node">
                                            <span class="kanban-timeline-milestone__core" style="background: {{ $row['color'] }};"></span>
                                        </span>
                                        <a
                                            href="{{ $row['view_url'] }}"
                                            class="kanban-timeline-milestone-link truncate text-xs font-medium transition"
                                        >
                                            {{ $row['title'] }}
                                        </a>
                                    </div>
                                @else
                                    <a
                                        href="{{ $row['view_url'] }}"
                                        class="kanban-timeline-bar group/bar mx-1.5 flex min-h-[2.5rem] items-center gap-2 overflow-hidden rounded-full px-2.5 py-1.5"
                                        style="grid-column: {{ $row['start_index'] + 2 }} / span {{ $row['span'] }};"
                                    >
                                        <span class="kanban-timeline-bar__sheen" aria-hidden="true"></span>
                                        @if (($row['assignees']['total_count'] ?? 0) > 0)
                                            @php
                                                $assigneeTooltipItems = collect($row['assignees']['all_members'] ?? $row['assignees']['visible_members'] ?? [])
                                                    ->map(fn (array $member): array => ['name' => $member['name']])
                                                    ->all();
                                                $assigneeTooltipTitle = trim(($row['assignees']['heading'] ?? '').' · '.($row['assignees']['title'] ?? ''));
                                            @endphp
                                            <x-collaborator-avatar-stack
                                                class="kanban-timeline-bar__avatars relative z-[1] shrink-0"
                                                align="start"
                                                :avatars="$row['assignees']['visible_members'] ?? []"
                                                :overflow-count="$row['assignees']['overflow_count'] ?? 0"
                                                :tooltip-title="$assigneeTooltipTitle"
                                                :tooltip-items="$assigneeTooltipItems"
                                            />
                                        @endif
                                        <span class="relative z-[1] min-w-0 truncate text-xs font-semibold tracking-tight text-white">{{ $row['title'] }}</span>
                                    </a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @empty
                    <div class="kanban-timeline-empty px-6 py-20 text-center">
                        <div class="kanban-timeline-empty-icon mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl">
                            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                            </svg>
                        </div>
                        <p class="kanban-timeline-empty-title text-sm font-medium">Sin actividades en el timeline</p>
                        <p class="kanban-timeline-empty-text mt-1 text-xs">Ajusta los filtros o crea actividades con fechas límite.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</section>

<style>
    .kanban-timeline {
        --tl-surface-border: rgba(15, 23, 42, 0.1);
        --tl-surface-ring: rgba(15, 23, 42, 0.05);
        --tl-surface-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
        --tl-weekend-wash: rgba(148, 163, 184, 0.14);
        --tl-grid-day: rgba(15, 23, 42, 0.08);
        --tl-grid-week: rgba(15, 23, 42, 0.16);
        --tl-grid-row: rgba(15, 23, 42, 0.06);
        --tl-grid-overlay-bg: rgba(248, 250, 252, 0.35);
        --tl-header-bg: rgba(255, 255, 255, 0.96);
        --tl-header-border: rgba(15, 23, 42, 0.08);
        --tl-header-cell-border: rgba(15, 23, 42, 0.08);
        --tl-header-cell-bg: rgba(248, 250, 252, 0.65);
        --tl-header-cell-text: rgb(71, 85, 105);
        --tl-header-day-today-bg: rgba(255, 237, 213, 0.85);
        --tl-header-day-today-text: rgb(194, 65, 12);
        --tl-header-day-today-border: rgba(251, 146, 60, 0.45);
        --tl-header-day-weekend-bg: rgba(241, 245, 249, 0.9);
        --tl-header-day-weekend-text: rgb(100, 116, 139);
        --tl-sidebar-bg: linear-gradient(90deg, rgba(255, 255, 255, 0.98) 0%, rgba(249, 250, 251, 0.96) 100%);
        --tl-sidebar-border: rgba(15, 23, 42, 0.08);
        --tl-sidebar-shadow: 8px 0 24px -18px rgba(15, 23, 42, 0.12);
        --tl-sidebar-row-border: rgba(15, 23, 42, 0.06);
        --tl-sidebar-project-border: rgba(15, 23, 42, 0.06);
        --tl-sidebar-text: rgb(100, 116, 139);
        --tl-title-text: rgb(15, 23, 42);
        --tl-muted-text: rgb(100, 116, 139);
        --tl-count-badge-bg: rgba(241, 245, 249, 0.95);
        --tl-count-badge-text: rgb(100, 116, 139);
        --tl-count-badge-ring: rgba(15, 23, 42, 0.08);
        --tl-icon-box-bg: rgba(241, 245, 249, 0.95);
        --tl-icon-box-ring: rgba(15, 23, 42, 0.08);
        --tl-icon-box-text: rgb(100, 116, 139);
        --tl-label-text: rgb(100, 116, 139);
        --tl-phase-bg: linear-gradient(90deg, rgba(15, 23, 42, 0.025) var(--timeline-sidebar), rgba(15, 23, 42, 0.01) 100%);
        --tl-phase-border: rgba(15, 23, 42, 0.07);
        --tl-group-border: rgba(15, 23, 42, 0.07);
        --tl-row-border: rgba(15, 23, 42, 0.05);
        --tl-row-stripe: rgba(15, 23, 42, 0.018);
        --tl-milestone-node-bg: rgba(255, 255, 255, 0.92);
        --tl-milestone-node-border: rgba(15, 23, 42, 0.12);
        --tl-milestone-node-shadow: rgba(15, 23, 42, 0.04);
        --tl-milestone-link: rgb(71, 85, 105);
        --tl-milestone-link-hover: rgb(15, 23, 42);
        --tl-bar-highlight: rgba(255, 255, 255, 0.34);
        --tl-bar-inset: rgba(255, 255, 255, 0.18);
        --tl-bar-ring: rgba(255, 255, 255, 0.06);
        --tl-today-column-start: rgba(249, 115, 22, 0.06);
        --tl-today-column-mid: rgba(249, 115, 22, 0.1);
        --tl-today-glow-mid: rgba(249, 115, 22, 0.14);
        --tl-empty-icon-bg: rgba(241, 245, 249, 0.95);
        --tl-empty-icon-ring: rgba(15, 23, 42, 0.08);
        --tl-empty-icon-text: rgb(148, 163, 184);
        --tl-empty-title: rgb(51, 65, 85);
        --tl-empty-text: rgb(100, 116, 139);
        --tl-status-todo-bg: rgba(241, 245, 249, 0.95);
        --tl-status-todo-text: rgb(71, 85, 105);
        --tl-status-todo-ring: rgba(148, 163, 184, 0.35);
        --tl-status-todo-dot: rgb(100, 116, 139);
        --tl-status-progress-bg: rgba(224, 242, 254, 0.95);
        --tl-status-progress-text: rgb(3, 105, 161);
        --tl-status-progress-ring: rgba(56, 189, 248, 0.35);
        --tl-status-progress-dot: rgb(14, 165, 233);
        --tl-status-review-bg: rgba(254, 243, 199, 0.95);
        --tl-status-review-text: rgb(180, 83, 9);
        --tl-status-review-ring: rgba(251, 191, 36, 0.35);
        --tl-status-review-dot: rgb(245, 158, 11);
        --tl-status-done-bg: rgba(209, 250, 229, 0.95);
        --tl-status-done-text: rgb(4, 120, 87);
        --tl-status-done-ring: rgba(52, 211, 153, 0.35);
        --tl-status-done-dot: rgb(16, 185, 129);

        border: 1px solid var(--tl-surface-border);
        background: #ffffff;
        box-shadow: var(--tl-surface-shadow), inset 0 0 0 1px var(--tl-surface-ring);
        color-scheme: light;
    }

    :is(.dark, .dark *) .kanban-timeline {
        --tl-surface-border: rgba(255, 255, 255, 0.08);
        --tl-surface-ring: rgba(255, 255, 255, 0.04);
        --tl-surface-shadow: 0 24px 80px -24px rgba(0, 0, 0, 0.85);
        --tl-weekend-wash: rgba(8, 12, 20, 0.42);
        --tl-grid-day: rgba(255, 255, 255, 0.055);
        --tl-grid-week: rgba(255, 255, 255, 0.16);
        --tl-grid-row: rgba(255, 255, 255, 0.04);
        --tl-grid-overlay-bg: rgba(4, 7, 12, 0.22);
        --tl-header-bg: rgba(10, 14, 22, 0.98);
        --tl-header-border: rgba(255, 255, 255, 0.08);
        --tl-header-cell-border: rgba(255, 255, 255, 0.08);
        --tl-header-cell-bg: rgba(255, 255, 255, 0.015);
        --tl-header-cell-text: rgb(148, 163, 184);
        --tl-header-day-today-bg: rgba(249, 115, 22, 0.12);
        --tl-header-day-today-text: rgb(254, 215, 170);
        --tl-header-day-today-border: rgba(249, 115, 22, 0.4);
        --tl-header-day-weekend-bg: rgba(0, 0, 0, 0.2);
        --tl-header-day-weekend-text: rgb(100, 116, 139);
        --tl-sidebar-bg: linear-gradient(90deg, rgba(10, 14, 22, 0.98) 0%, rgba(10, 14, 22, 0.94) 100%);
        --tl-sidebar-border: rgba(255, 255, 255, 0.07);
        --tl-sidebar-shadow: 8px 0 24px -18px rgba(0, 0, 0, 0.65);
        --tl-sidebar-row-border: rgba(255, 255, 255, 0.035);
        --tl-sidebar-project-border: rgba(255, 255, 255, 0.04);
        --tl-sidebar-text: rgb(100, 116, 139);
        --tl-title-text: rgb(248, 250, 252);
        --tl-muted-text: rgb(148, 163, 184);
        --tl-count-badge-bg: rgba(255, 255, 255, 0.05);
        --tl-count-badge-text: rgb(148, 163, 184);
        --tl-count-badge-ring: rgba(255, 255, 255, 0.06);
        --tl-icon-box-bg: rgba(255, 255, 255, 0.06);
        --tl-icon-box-ring: rgba(255, 255, 255, 0.1);
        --tl-icon-box-text: rgb(148, 163, 184);
        --tl-label-text: rgb(148, 163, 184);
        --tl-phase-bg: linear-gradient(90deg, rgba(255, 255, 255, 0.025) var(--timeline-sidebar), rgba(255, 255, 255, 0.01) 100%);
        --tl-phase-border: rgba(255, 255, 255, 0.05);
        --tl-group-border: rgba(255, 255, 255, 0.06);
        --tl-row-border: rgba(255, 255, 255, 0.035);
        --tl-row-stripe: rgba(255, 255, 255, 0.018);
        --tl-milestone-node-bg: rgba(255, 255, 255, 0.08);
        --tl-milestone-node-border: rgba(255, 255, 255, 0.12);
        --tl-milestone-node-shadow: rgba(255, 255, 255, 0.03);
        --tl-milestone-link: rgb(203, 213, 225);
        --tl-milestone-link-hover: rgb(248, 250, 252);
        --tl-today-column-start: rgba(249, 115, 22, 0.05);
        --tl-today-column-mid: rgba(249, 115, 22, 0.09);
        --tl-today-glow-mid: rgba(249, 115, 22, 0.18);
        --tl-empty-icon-bg: rgba(255, 255, 255, 0.04);
        --tl-empty-icon-ring: rgba(255, 255, 255, 0.1);
        --tl-empty-icon-text: rgb(100, 116, 139);
        --tl-empty-title: rgb(226, 232, 240);
        --tl-empty-text: rgb(100, 116, 139);
        --tl-status-todo-bg: rgba(100, 116, 139, 0.14);
        --tl-status-todo-text: rgb(203, 213, 225);
        --tl-status-todo-ring: rgba(148, 163, 184, 0.22);
        --tl-status-todo-dot: rgb(148, 163, 184);
        --tl-status-progress-bg: rgba(14, 165, 233, 0.14);
        --tl-status-progress-text: rgb(125, 211, 252);
        --tl-status-progress-ring: rgba(56, 189, 248, 0.22);
        --tl-status-progress-dot: rgb(56, 189, 248);
        --tl-status-review-bg: rgba(245, 158, 11, 0.14);
        --tl-status-review-text: rgb(252, 211, 77);
        --tl-status-review-ring: rgba(251, 191, 36, 0.22);
        --tl-status-review-dot: rgb(251, 191, 36);
        --tl-status-done-bg: rgba(16, 185, 129, 0.14);
        --tl-status-done-text: rgb(110, 231, 183);
        --tl-status-done-ring: rgba(52, 211, 153, 0.22);
        --tl-status-done-dot: rgb(52, 211, 153);

        background: #070a10;
        color-scheme: dark;
    }

    .kanban-timeline-label {
        font-size: 11px;
        font-weight: 600;
        color: var(--tl-label-text);
    }

    .kanban-timeline-icon-box {
        background: var(--tl-icon-box-bg);
        color: var(--tl-icon-box-text);
        box-shadow: inset 0 0 0 1px var(--tl-icon-box-ring);
    }

    .kanban-timeline-title {
        color: var(--tl-title-text);
    }

    .kanban-timeline-count-badge {
        background: var(--tl-count-badge-bg);
        color: var(--tl-count-badge-text);
        box-shadow: inset 0 0 0 1px var(--tl-count-badge-ring);
    }

    .kanban-timeline-milestone-link {
        color: var(--tl-milestone-link);
    }

    .kanban-timeline-milestone-link:hover {
        color: var(--tl-milestone-link-hover);
    }

    .kanban-timeline-empty-icon {
        background: var(--tl-empty-icon-bg);
        color: var(--tl-empty-icon-text);
        box-shadow: inset 0 0 0 1px var(--tl-empty-icon-ring);
    }

    .kanban-timeline-empty-title {
        color: var(--tl-empty-title);
    }

    .kanban-timeline-empty-text {
        color: var(--tl-empty-text);
    }

    .kanban-timeline-status {
        background: var(--tl-status-todo-bg);
        color: var(--tl-status-todo-text);
        box-shadow: inset 0 0 0 1px var(--tl-status-todo-ring);
    }

    .kanban-timeline-status__dot {
        background: var(--tl-status-todo-dot);
    }

    .kanban-timeline-status--progress {
        background: var(--tl-status-progress-bg);
        color: var(--tl-status-progress-text);
        box-shadow: inset 0 0 0 1px var(--tl-status-progress-ring);
    }

    .kanban-timeline-status--progress .kanban-timeline-status__dot {
        background: var(--tl-status-progress-dot);
    }

    .kanban-timeline-status--review {
        background: var(--tl-status-review-bg);
        color: var(--tl-status-review-text);
        box-shadow: inset 0 0 0 1px var(--tl-status-review-ring);
    }

    .kanban-timeline-status--review .kanban-timeline-status__dot {
        background: var(--tl-status-review-dot);
    }

    .kanban-timeline-status--done {
        background: var(--tl-status-done-bg);
        color: var(--tl-status-done-text);
        box-shadow: inset 0 0 0 1px var(--tl-status-done-ring);
    }

    .kanban-timeline-status--done .kanban-timeline-status__dot {
        background: var(--tl-status-done-dot);
    }

    .kanban-timeline-grid {
        background: var(--tl-header-bg);
        border-bottom: 1px solid var(--tl-header-border);
        box-shadow:
            inset 0 -1px 0 var(--tl-header-border),
            0 8px 24px -20px rgba(15, 23, 42, 0.08);
    }

    :is(.dark, .dark *) .kanban-timeline-grid {
        box-shadow:
            inset 0 -1px 0 var(--tl-header-border),
            0 8px 24px -20px rgba(0, 0, 0, 0.8);
    }

    .kanban-timeline-weeks {
        border-bottom: 1px solid var(--tl-header-border);
    }

    .kanban-timeline-week-cell,
    .kanban-timeline-day-cell {
        border-color: var(--tl-header-cell-border);
        background: var(--tl-header-cell-bg);
        color: var(--tl-header-cell-text);
    }

    .kanban-timeline-day-cell--week-start {
        background: color-mix(in srgb, var(--tl-header-cell-bg) 70%, var(--tl-header-border));
    }

    .kanban-timeline-day-cell--weekend {
        background: var(--tl-header-day-weekend-bg);
        color: var(--tl-header-day-weekend-text);
    }

    .kanban-timeline-day-cell--today {
        background: var(--tl-header-day-today-bg);
        border-color: var(--tl-header-day-today-border);
        color: var(--tl-header-day-today-text);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
    }

    .kanban-timeline-group {
        border-bottom: 1px solid var(--tl-group-border);
    }

    .kanban-timeline-sidebar-header {
        color: var(--tl-sidebar-text);
    }

    .kanban-timeline-canvas {
        --timeline-sidebar: 15.5rem;
        --timeline-day-width: calc((100% - var(--timeline-sidebar)) / var(--timeline-days));
        --timeline-today-x: calc(var(--timeline-sidebar) + (var(--timeline-day-width) * var(--today-index, 0)) + (var(--timeline-day-width) / 2));
        background:
            radial-gradient(1200px 420px at 18% -10%, rgba(99, 102, 241, 0.05), transparent 55%),
            radial-gradient(900px 360px at 88% 0%, rgba(249, 115, 22, 0.04), transparent 50%),
            linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
    }

    :is(.dark, .dark *) .kanban-timeline-canvas {
        background:
            radial-gradient(1200px 420px at 18% -10%, rgba(99, 102, 241, 0.07), transparent 55%),
            radial-gradient(900px 360px at 88% 0%, rgba(249, 115, 22, 0.05), transparent 50%),
            linear-gradient(180deg, #0a0e16 0%, #070a10 100%);
    }

    .kanban-timeline-grid-overlay::before {
        content: '';
        position: absolute;
        inset: 0 0 0 var(--timeline-sidebar);
        background-color: var(--tl-grid-overlay-bg);
        background-image:
            var(--timeline-weekend-wash, none),
            repeating-linear-gradient(
                to right,
                var(--tl-grid-day) 0,
                var(--tl-grid-day) 1px,
                transparent 1px,
                transparent var(--timeline-day-width)
            ),
            repeating-linear-gradient(
                to right,
                transparent 0,
                transparent calc(var(--timeline-day-width) * 7 - 1px),
                var(--tl-grid-week) calc(var(--timeline-day-width) * 7 - 1px),
                var(--tl-grid-week) calc(var(--timeline-day-width) * 7)
            );
        background-repeat: no-repeat, repeat, repeat;
    }

    .kanban-timeline-grid-overlay::after {
        content: '';
        position: absolute;
        inset: 0 0 0 var(--timeline-sidebar);
        background-image: repeating-linear-gradient(
            to bottom,
            transparent 0,
            transparent calc(3.75rem - 1px),
            var(--tl-grid-row) calc(3.75rem - 1px),
            var(--tl-grid-row) 3.75rem
        );
        opacity: 0.9;
    }

    .kanban-timeline-sidebar-header,
    .kanban-timeline-sidebar {
        position: relative;
        z-index: 6;
        background: var(--tl-sidebar-bg);
        box-shadow: 1px 0 0 var(--tl-sidebar-border), var(--tl-sidebar-shadow);
    }

    .kanban-timeline-sidebar--project {
        border-top: 1px solid var(--tl-sidebar-project-border);
    }

    .kanban-timeline-sidebar--project::before {
        content: '';
        position: absolute;
        inset: 0 auto 0 0;
        width: 3px;
        background: linear-gradient(180deg, var(--project-color), color-mix(in srgb, var(--project-color) 35%, transparent));
        box-shadow: 0 0 18px color-mix(in srgb, var(--project-color) 55%, transparent);
    }

    .kanban-timeline-sidebar--row {
        border-top: 1px solid var(--tl-sidebar-row-border);
    }

    .kanban-timeline-scroll {
        scrollbar-width: thin;
        scrollbar-color: rgba(148, 163, 184, 0.45) transparent;
    }

    .kanban-timeline-scroll::-webkit-scrollbar {
        height: 8px;
    }

    .kanban-timeline-scroll::-webkit-scrollbar-thumb {
        border-radius: 9999px;
        background: rgba(148, 163, 184, 0.45);
    }

    .kanban-timeline-phase-rail {
        position: relative;
        display: flex;
        align-items: center;
        height: 100%;
        min-height: 1.25rem;
    }

    .kanban-timeline-phase-rail__track {
        position: relative;
        z-index: 1;
        display: block;
        width: 100%;
        height: 3px;
        border-radius: 9999px;
        background: linear-gradient(90deg, var(--project-color), color-mix(in srgb, var(--project-color) 55%, #ffffff));
        box-shadow:
            0 0 0 1px color-mix(in srgb, var(--project-color) 20%, transparent) inset,
            0 0 16px color-mix(in srgb, var(--project-color) 45%, transparent);
    }

    .kanban-timeline-phase-rail__glow {
        position: absolute;
        inset: 50% 0 auto;
        height: 14px;
        transform: translateY(-50%);
        border-radius: 9999px;
        background: linear-gradient(90deg, transparent, color-mix(in srgb, var(--project-color) 28%, transparent), transparent);
        filter: blur(6px);
    }

    .kanban-timeline-row {
        position: relative;
        z-index: 4;
        border-top: 1px solid var(--tl-row-border);
    }

    .kanban-timeline-row:nth-child(even) {
        background: linear-gradient(90deg, transparent var(--timeline-sidebar), var(--tl-row-stripe) var(--timeline-sidebar));
    }

    .kanban-timeline-phase {
        position: relative;
        z-index: 5;
        background: var(--tl-phase-bg);
        border-bottom: 1px solid var(--tl-phase-border);
    }

    .kanban-timeline-bar {
        position: relative;
        isolation: isolate;
        background: linear-gradient(180deg, color-mix(in srgb, var(--bar-color) 92%, #ffffff), var(--bar-color));
        border: 1px solid color-mix(in srgb, var(--bar-color) 70%, #ffffff);
        box-shadow:
            0 0 0 1px var(--tl-bar-ring) inset,
            0 1px 0 var(--tl-bar-inset) inset,
            0 12px 28px -14px color-mix(in srgb, var(--bar-color) 70%, transparent);
        transition:
            transform 220ms cubic-bezier(0.25, 1, 0.5, 1),
            box-shadow 220ms cubic-bezier(0.25, 1, 0.5, 1),
            filter 220ms ease;
        animation: kanban-timeline-bar-in 460ms cubic-bezier(0.25, 1, 0.5, 1) both;
    }

    .kanban-timeline-bar__sheen {
        position: absolute;
        inset: 1px 12% auto;
        height: 42%;
        border-radius: 9999px;
        background: linear-gradient(180deg, var(--tl-bar-highlight), transparent);
        opacity: 0.55;
        pointer-events: none;
    }

    .kanban-timeline-bar:hover {
        transform: translateY(-1px);
        filter: brightness(1.06);
        box-shadow:
            0 0 0 1px color-mix(in srgb, var(--tl-bar-ring) 160%, transparent) inset,
            0 1px 0 var(--tl-bar-inset) inset,
            0 16px 34px -14px color-mix(in srgb, var(--bar-color) 78%, transparent);
    }

    .kanban-timeline-bar__avatars .tdg-calendar-avatar-stack__item {
        width: 1.375rem;
        height: 1.375rem;
        border-width: 1.5px;
        border-color: rgba(255, 255, 255, 0.55) !important;
        font-size: 9px;
    }

    .kanban-timeline-bar__avatars span.tdg-calendar-avatar-stack__item:not(.tdg-calendar-avatar-stack__overflow) {
        background: rgba(15, 23, 42, 0.35) !important;
        color: #fff !important;
    }

    .kanban-timeline-bar__avatars .tdg-calendar-avatar-stack__overflow {
        background: rgba(15, 23, 42, 0.72) !important;
        border-color: rgba(255, 255, 255, 0.55) !important;
        color: #fff !important;
        font-size: 8px;
    }

    .kanban-timeline-milestone__node {
        position: relative;
        display: flex;
        width: 14px;
        height: 14px;
        align-items: center;
        justify-content: center;
        border-radius: 9999px;
        background: var(--tl-milestone-node-bg);
        border: 1px solid var(--tl-milestone-node-border);
        box-shadow: 0 0 0 4px var(--tl-milestone-node-shadow);
    }

    .kanban-timeline-milestone__core {
        width: 8px;
        height: 8px;
        border-radius: 9999px;
        box-shadow: 0 0 14px color-mix(in srgb, var(--bar-color) 75%, transparent);
    }

    .kanban-timeline-today-glow {
        position: absolute;
        top: 0;
        bottom: 0;
        left: var(--timeline-today-x);
        width: 3rem;
        transform: translateX(-50%);
        background: linear-gradient(
            90deg,
            transparent 0%,
            rgba(249, 115, 22, 0.05) 18%,
            var(--tl-today-glow-mid) 50%,
            rgba(249, 115, 22, 0.05) 82%,
            transparent 100%
        );
    }

    .kanban-timeline-today-line {
        position: absolute;
        top: 0;
        bottom: 0;
        left: var(--timeline-today-x);
        width: 2px;
        transform: translateX(-50%);
        border-radius: 9999px;
        background: linear-gradient(
            180deg,
            rgba(249, 115, 22, 0.15) 0%,
            #fb923c 12%,
            #f97316 50%,
            #fb923c 88%,
            rgba(249, 115, 22, 0.15) 100%
        );
        box-shadow:
            0 0 0 1px rgba(251, 146, 60, 0.35),
            0 0 10px rgba(249, 115, 22, 0.95),
            0 0 28px rgba(249, 115, 22, 0.55),
            0 0 56px rgba(249, 115, 22, 0.22);
    }

    .kanban-timeline-today-dot {
        position: absolute;
        left: 50%;
        width: 7px;
        height: 7px;
        transform: translateX(-50%);
        border-radius: 9999px;
        background: #f97316;
        box-shadow:
            0 0 0 2px rgba(255, 255, 255, 0.18),
            0 0 12px rgba(249, 115, 22, 0.95);
    }

    .kanban-timeline-today-dot--top {
        top: 0.35rem;
    }

    .kanban-timeline-today-dot--bottom {
        bottom: 0.35rem;
    }

    .kanban-timeline-today-badge {
        position: absolute;
        top: 0.55rem;
        left: var(--timeline-today-x);
        z-index: 1;
        display: inline-flex;
        transform: translateX(-50%);
        align-items: center;
        gap: 0.25rem;
        border-radius: 9999px;
        border: 1px solid rgba(251, 146, 60, 0.55);
        background: linear-gradient(180deg, #fb923c, #ea580c);
        padding: 0.2rem 0.65rem;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: 0.04em;
        color: #fff;
        white-space: nowrap;
        box-shadow:
            0 0 0 1px rgba(255, 255, 255, 0.12) inset,
            0 4px 14px -4px rgba(249, 115, 22, 0.85),
            0 0 22px rgba(249, 115, 22, 0.45);
    }

    .kanban-timeline-today-badge::before {
        content: '';
        width: 5px;
        height: 5px;
        border-radius: 9999px;
        background: #fff;
        box-shadow: 0 0 8px rgba(255, 255, 255, 0.85);
        animation: kanban-timeline-today-pulse 2.4s ease-in-out infinite;
    }

    @keyframes kanban-timeline-today-pulse {
        0%,
        100% {
            opacity: 1;
            transform: scale(1);
        }

        50% {
            opacity: 0.55;
            transform: scale(0.82);
        }
    }

    .kanban-timeline-body::after {
        content: '';
        position: absolute;
        inset: 0 0 0 var(--timeline-sidebar);
        pointer-events: none;
        z-index: 1;
        background: linear-gradient(
            90deg,
            transparent calc(var(--timeline-day-width) * var(--today-index, 0)),
            var(--tl-today-column-start) calc(var(--timeline-day-width) * var(--today-index, 0)),
            var(--tl-today-column-mid) calc(var(--timeline-day-width) * var(--today-index, 0) + var(--timeline-day-width)),
            var(--tl-today-column-start) calc(var(--timeline-day-width) * var(--today-index, 0) + var(--timeline-day-width)),
            transparent calc(var(--timeline-day-width) * var(--today-index, 0) + var(--timeline-day-width))
        );
        opacity: 0;
        animation: kanban-timeline-today-column 480ms cubic-bezier(0.25, 1, 0.5, 1) forwards;
    }

    @keyframes kanban-timeline-today-column {
        to {
            opacity: 1;
        }
    }

    @keyframes kanban-timeline-bar-in {
        from {
            opacity: 0;
            transform: translateY(6px) scale(0.98);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
</style>
