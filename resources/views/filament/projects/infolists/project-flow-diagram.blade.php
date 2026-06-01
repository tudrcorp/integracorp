@php
    $project = $project ?? [];
    $stats = $stats ?? [];
    $subprojects = $subprojects ?? [];
    $hasSubprojects = (bool) ($has_subprojects ?? false);
    $createSubprojectUrl = (string) ($create_subproject_url ?? '#');
    $subprojectsIndexUrl = (string) ($subprojects_index_url ?? '#');

    $projectColor = (string) ($project['color'] ?? '#6366f1');
    $projectIcon = (string) ($project['icon'] ?? 'heroicon-o-folder');
    $projectName = (string) ($project['name'] ?? 'Proyecto');
    $projectStatusLabel = (string) ($project['status_label'] ?? '—');
    $timelineLabel = (string) ($project['timeline_label'] ?? 'Planificación');
@endphp

<div
    class="project-flow-diagram fi-scoped"
    style="--project-color: {{ $projectColor }};"
    x-data="{ statusFilter: 'all' }"
>
    <div class="project-flow-diagram__header">
        <div class="min-w-0 flex-1">
            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                Mapa operativo del proyecto
            </p>
            <h3 class="mt-1 text-base font-bold tracking-tight text-gray-950 dark:text-white sm:text-lg">
                Diagrama de flujo · {{ $projectName }}
            </h3>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Visualiza la relación entre el proyecto maestro y sus subproyectos, con avance por actividades.
            </p>
        </div>

        <div class="project-flow-diagram__legend" aria-label="Leyenda de estatus">
            <span class="project-flow-diagram__legend-item">
                <span class="project-flow-diagram__dot project-flow-diagram__dot--active"></span>
                Activo
            </span>
            <span class="project-flow-diagram__legend-item">
                <span class="project-flow-diagram__dot project-flow-diagram__dot--pending"></span>
                Pendiente
            </span>
            <span class="project-flow-diagram__legend-item">
                <span class="project-flow-diagram__dot project-flow-diagram__dot--completed"></span>
                Completado
            </span>
        </div>
    </div>

    <div class="project-flow-diagram__stats" role="list" aria-label="Resumen del diagrama">
        <article class="project-flow-diagram__stat" role="listitem">
            <p class="project-flow-diagram__stat-label">Subproyectos</p>
            <p class="project-flow-diagram__stat-value">{{ (int) ($stats['subprojects_total'] ?? 0) }}</p>
        </article>
        <article class="project-flow-diagram__stat" role="listitem">
            <p class="project-flow-diagram__stat-label">Activos</p>
            <p class="project-flow-diagram__stat-value">{{ (int) ($stats['subprojects_active'] ?? 0) }}</p>
        </article>
        <article class="project-flow-diagram__stat" role="listitem">
            <p class="project-flow-diagram__stat-label">Actividades</p>
            <p class="project-flow-diagram__stat-value">{{ (int) ($stats['activities_total'] ?? 0) }}</p>
        </article>
        <article class="project-flow-diagram__stat" role="listitem">
            <p class="project-flow-diagram__stat-label">Avance global</p>
            <p class="project-flow-diagram__stat-value">
                @if (($stats['overall_percent'] ?? null) !== null)
                    {{ (int) $stats['overall_percent'] }}%
                @else
                    —
                @endif
            </p>
        </article>
    </div>

    @if ($hasSubprojects)
        <div class="project-flow-diagram__toolbar">
            <div class="project-flow-diagram__filters" role="tablist" aria-label="Filtrar subproyectos por estatus">
                @foreach ([
                    'all' => 'Todos',
                    'active' => 'Activos',
                    'pending' => 'Pendientes',
                    'completed' => 'Completados',
                ] as $filterValue => $filterLabel)
                    <button
                        type="button"
                        class="project-flow-diagram__filter"
                        :class="{ 'project-flow-diagram__filter--active': statusFilter === @js($filterValue) }"
                        @click="statusFilter = @js($filterValue)"
                        role="tab"
                        :aria-selected="statusFilter === @js($filterValue)"
                    >
                        {{ $filterLabel }}
                    </button>
                @endforeach
            </div>

            <div class="project-flow-diagram__actions">
                <a href="{{ $subprojectsIndexUrl }}" class="project-flow-diagram__action project-flow-diagram__action--ghost">
                    Ver listado
                </a>
                <a href="{{ $createSubprojectUrl }}" class="project-flow-diagram__action project-flow-diagram__action--primary">
                    Nuevo subproyecto
                </a>
            </div>
        </div>

        <div class="project-flow-diagram__canvas" aria-label="Diagrama de flujo del proyecto">
            <div class="project-flow-diagram__root-wrap">
                <article class="project-flow-diagram__node project-flow-diagram__node--root">
                    <div class="project-flow-diagram__node-icon">
                        <x-filament::icon :icon="$projectIcon" class="size-6" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="project-flow-diagram__node-kicker">Proyecto maestro</p>
                        <h4 class="project-flow-diagram__node-title">{{ $projectName }}</h4>
                        <div class="project-flow-diagram__node-meta">
                            <span class="project-flow-diagram__pill">{{ $projectStatusLabel }}</span>
                            <span class="project-flow-diagram__pill project-flow-diagram__pill--muted">{{ $timelineLabel }}</span>
                        </div>
                    </div>
                </article>
                <div class="project-flow-diagram__stem" aria-hidden="true"></div>
            </div>

            <div class="project-flow-diagram__branch-rail" aria-hidden="true">
                <span class="project-flow-diagram__branch-line"></span>
            </div>

            <div class="project-flow-diagram__children">
                @foreach ($subprojects as $subproject)
                    @php
                        $workload = $subproject['workload'] ?? [];
                        $percent = $workload['percent'] ?? null;
                        $status = (string) ($subproject['status'] ?? 'pending');
                    @endphp
                    <div
                        class="project-flow-diagram__child-wrap"
                        wire:key="project-flow-subproject-{{ $subproject['id'] }}"
                        x-show="statusFilter === 'all' || statusFilter === @js($status)"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 translate-y-2"
                        x-transition:enter-end="opacity-100 translate-y-0"
                    >
                        <div class="project-flow-diagram__child-stem" aria-hidden="true"></div>

                        <a
                            href="{{ $subproject['view_url'] }}"
                            class="project-flow-diagram__node project-flow-diagram__node--child project-flow-diagram__node--{{ $status }}"
                            title="Abrir subproyecto {{ $subproject['name'] }}"
                        >
                            <div class="project-flow-diagram__node-head">
                                <span class="project-flow-diagram__step">Fase {{ $subproject['position'] }}</span>
                                <span class="project-flow-diagram__pill">{{ $subproject['status_label'] }}</span>
                            </div>

                            <h4 class="project-flow-diagram__node-title">{{ $subproject['name'] }}</h4>

                            <p class="project-flow-diagram__node-desc">
                                @if ($subproject['has_description'])
                                    {{ $subproject['description_preview'] }}
                                @else
                                    Sin descripción registrada.
                                @endif
                            </p>

                            <div class="project-flow-diagram__progress-wrap">
                                <div class="project-flow-diagram__progress-meta">
                                    <span>{{ $workload['label'] ?? 'Sin actividades' }}</span>
                                    @if ($percent !== null)
                                        <span>{{ $percent }}%</span>
                                    @endif
                                </div>
                                <div class="project-flow-diagram__progress-track">
                                    <div
                                        class="project-flow-diagram__progress-bar project-flow-diagram__progress-bar--{{ $workload['tone'] ?? 'muted' }}"
                                        @if ($percent !== null)
                                            style="width: {{ $percent }}%;"
                                        @endif
                                    ></div>
                                </div>
                            </div>

                            <div class="project-flow-diagram__node-foot">
                                <span>{{ (int) ($workload['done'] ?? 0) }} cerradas</span>
                                <span>{{ (int) ($workload['open'] ?? 0) }} abiertas</span>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="project-flow-diagram__empty">
            <div class="project-flow-diagram__empty-icon">
                <x-filament::icon icon="heroicon-o-squares-2x2" class="size-8" />
            </div>
            <p class="project-flow-diagram__empty-title">Aún no hay subproyectos en el diagrama</p>
            <p class="project-flow-diagram__empty-copy">
                Crea la primera fase para visualizar el flujo operativo desde el proyecto maestro hacia sus componentes.
            </p>
            <div class="project-flow-diagram__empty-actions">
                <a href="{{ $createSubprojectUrl }}" class="project-flow-diagram__action project-flow-diagram__action--primary">
                    Crear subproyecto
                </a>
                <a href="{{ $subprojectsIndexUrl }}" class="project-flow-diagram__action project-flow-diagram__action--ghost">
                    Ir al listado
                </a>
            </div>
        </div>
    @endif

    <style>
        .project-flow-diagram {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .project-flow-diagram__header {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
        }

        .project-flow-diagram__legend {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: center;
        }

        .project-flow-diagram__legend-item {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.6875rem;
            font-weight: 600;
            color: rgb(100 116 139);
        }

        .project-flow-diagram__dot {
            width: 0.55rem;
            height: 0.55rem;
            border-radius: 999px;
        }

        .project-flow-diagram__dot--active { background: rgb(16 185 129); }
        .project-flow-diagram__dot--pending { background: rgb(245 158 11); }
        .project-flow-diagram__dot--completed { background: rgb(148 163 184); }

        .project-flow-diagram__stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
        }

        @media (min-width: 768px) {
            .project-flow-diagram__stats {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        .project-flow-diagram__stat {
            border-radius: 1rem;
            border: 1px solid color-mix(in srgb, var(--project-color) 18%, rgb(226 232 240));
            background: color-mix(in srgb, var(--project-color) 5%, #ffffff);
            padding: 0.85rem 1rem;
        }

        .project-flow-diagram__stat-label {
            margin: 0;
            font-size: 0.6875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: rgb(100 116 139);
        }

        .project-flow-diagram__stat-value {
            margin: 0.35rem 0 0;
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: color-mix(in srgb, var(--project-color) 72%, #0f172a);
        }

        .project-flow-diagram__toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
        }

        .project-flow-diagram__filters {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
        }

        .project-flow-diagram__filter {
            border-radius: 999px;
            border: 1px solid rgb(226 232 240);
            background: rgb(255 255 255);
            padding: 0.35rem 0.8rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: rgb(71 85 105);
            transition: all 0.18s ease;
        }

        .project-flow-diagram__filter:hover {
            border-color: color-mix(in srgb, var(--project-color) 35%, rgb(226 232 240));
            color: rgb(15 23 42);
        }

        .project-flow-diagram__filter--active {
            border-color: color-mix(in srgb, var(--project-color) 45%, transparent);
            background: color-mix(in srgb, var(--project-color) 14%, #ffffff);
            color: color-mix(in srgb, var(--project-color) 78%, #0f172a);
            box-shadow: 0 8px 20px -14px color-mix(in srgb, var(--project-color) 55%, transparent);
        }

        .project-flow-diagram__actions,
        .project-flow-diagram__empty-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .project-flow-diagram__action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            padding: 0.45rem 0.95rem;
            font-size: 0.75rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.18s ease;
        }

        .project-flow-diagram__action--primary {
            background: color-mix(in srgb, var(--project-color) 88%, #0f172a);
            color: #fff;
            box-shadow: 0 10px 24px -14px color-mix(in srgb, var(--project-color) 70%, transparent);
        }

        .project-flow-diagram__action--primary:hover {
            transform: translateY(-1px);
            filter: brightness(1.03);
        }

        .project-flow-diagram__action--ghost {
            border: 1px solid rgb(226 232 240);
            background: rgb(255 255 255);
            color: rgb(51 65 85);
        }

        .project-flow-diagram__canvas {
            overflow-x: auto;
            border-radius: 1.25rem;
            border: 1px solid color-mix(in srgb, var(--project-color) 20%, rgb(226 232 240));
            background:
                radial-gradient(circle at top, color-mix(in srgb, var(--project-color) 8%, #ffffff), #ffffff 42%),
                linear-gradient(180deg, rgb(248 250 252), #ffffff);
            padding: 1.25rem 1rem 1.5rem;
        }

        .project-flow-diagram__root-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .project-flow-diagram__node {
            position: relative;
            display: flex;
            gap: 0.85rem;
            border-radius: 1.15rem;
            border: 1px solid rgb(226 232 240);
            background: rgb(255 255 255);
            box-shadow: 0 14px 34px -24px rgb(15 23 42 / 0.45);
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        }

        .project-flow-diagram__node--root {
            width: min(100%, 34rem);
            padding: 1rem 1.1rem;
            border-color: color-mix(in srgb, var(--project-color) 32%, rgb(226 232 240));
            background: linear-gradient(
                135deg,
                color-mix(in srgb, var(--project-color) 12%, #ffffff),
                #ffffff
            );
        }

        .project-flow-diagram__node--child {
            flex-direction: column;
            width: min(100%, 18rem);
            min-height: 11.5rem;
            padding: 0.95rem;
            text-decoration: none;
            color: inherit;
        }

        .project-flow-diagram__node--child:hover {
            transform: translateY(-2px);
            border-color: color-mix(in srgb, var(--project-color) 38%, rgb(226 232 240));
            box-shadow: 0 18px 36px -22px color-mix(in srgb, var(--project-color) 45%, transparent);
        }

        .project-flow-diagram__node--active { border-left: 4px solid rgb(16 185 129); }
        .project-flow-diagram__node--pending { border-left: 4px solid rgb(245 158 11); }
        .project-flow-diagram__node--completed { border-left: 4px solid rgb(148 163 184); }

        .project-flow-diagram__node-icon {
            display: flex;
            height: 3rem;
            width: 3rem;
            shrink: 0;
            align-items: center;
            justify-content: center;
            border-radius: 1rem;
            border: 1px solid color-mix(in srgb, var(--project-color) 28%, transparent);
            background: color-mix(in srgb, var(--project-color) 14%, #ffffff);
            color: color-mix(in srgb, var(--project-color) 78%, #0f172a);
        }

        .project-flow-diagram__node-kicker {
            margin: 0;
            font-size: 0.6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            color: rgb(100 116 139);
        }

        .project-flow-diagram__node-title {
            margin: 0.2rem 0 0;
            font-size: 1rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: rgb(15 23 42);
        }

        .project-flow-diagram__node-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            margin-top: 0.55rem;
        }

        .project-flow-diagram__node-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .project-flow-diagram__step {
            font-size: 0.6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: rgb(100 116 139);
        }

        .project-flow-diagram__pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.15rem 0.55rem;
            font-size: 0.625rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            background: color-mix(in srgb, var(--project-color) 12%, #ffffff);
            color: color-mix(in srgb, var(--project-color) 75%, #0f172a);
        }

        .project-flow-diagram__pill--muted {
            background: rgb(241 245 249);
            color: rgb(100 116 139);
        }

        .project-flow-diagram__node-desc {
            margin: 0.55rem 0 0;
            min-height: 2.5rem;
            font-size: 0.75rem;
            line-height: 1.45;
            color: rgb(100 116 139);
        }

        .project-flow-diagram__progress-wrap {
            margin-top: auto;
            padding-top: 0.75rem;
        }

        .project-flow-diagram__progress-meta {
            display: flex;
            justify-content: space-between;
            gap: 0.5rem;
            font-size: 0.6875rem;
            font-weight: 600;
            color: rgb(100 116 139);
        }

        .project-flow-diagram__progress-track {
            margin-top: 0.35rem;
            height: 0.45rem;
            overflow: hidden;
            border-radius: 999px;
            background: rgb(226 232 240 / 0.85);
        }

        .project-flow-diagram__progress-bar {
            height: 100%;
            border-radius: inherit;
            width: 18%;
            background: rgb(148 163 184);
        }

        .project-flow-diagram__progress-bar--success { background: rgb(16 185 129); }
        .project-flow-diagram__progress-bar--warning { background: rgb(245 158 11); }
        .project-flow-diagram__progress-bar--primary { background: color-mix(in srgb, var(--project-color) 78%, #0f172a); }
        .project-flow-diagram__progress-bar--muted { background: rgb(148 163 184); width: 18%; }

        .project-flow-diagram__node-foot {
            display: flex;
            justify-content: space-between;
            gap: 0.5rem;
            margin-top: 0.55rem;
            font-size: 0.6875rem;
            font-weight: 600;
            color: rgb(100 116 139);
        }

        .project-flow-diagram__stem,
        .project-flow-diagram__child-stem {
            width: 2px;
            background: color-mix(in srgb, var(--project-color) 45%, rgb(203 213 225));
        }

        .project-flow-diagram__stem {
            height: 1.75rem;
        }

        .project-flow-diagram__child-stem {
            height: 1.25rem;
        }

        .project-flow-diagram__branch-rail {
            display: none;
        }

        .project-flow-diagram__children {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
            margin-top: 0.25rem;
        }

        .project-flow-diagram__child-wrap {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: min(100%, 18rem);
        }

        @media (min-width: 768px) {
            .project-flow-diagram__canvas {
                padding: 1.5rem 1.25rem 1.75rem;
            }

            .project-flow-diagram__branch-rail {
                display: block;
                position: relative;
                width: min(100%, calc({{ max(count($subprojects), 1) }} * 19rem));
                height: 2px;
                margin: 0 auto;
            }

            .project-flow-diagram__branch-line {
                display: block;
                height: 2px;
                border-radius: 999px;
                background: linear-gradient(
                    90deg,
                    transparent,
                    color-mix(in srgb, var(--project-color) 55%, rgb(203 213 225)) 12%,
                    color-mix(in srgb, var(--project-color) 55%, rgb(203 213 225)) 88%,
                    transparent
                );
            }

            .project-flow-diagram__children {
                flex-direction: row;
                flex-wrap: nowrap;
                justify-content: center;
                align-items: flex-start;
                gap: 1rem;
                width: max-content;
                min-width: 100%;
                margin-inline: auto;
                padding-bottom: 0.25rem;
            }

            .project-flow-diagram__child-wrap {
                flex: 0 0 18rem;
            }
        }

        .project-flow-diagram__empty {
            border-radius: 1.25rem;
            border: 1px dashed color-mix(in srgb, var(--project-color) 28%, rgb(203 213 225));
            background: color-mix(in srgb, var(--project-color) 4%, #ffffff);
            padding: 2.5rem 1.25rem;
            text-align: center;
        }

        .project-flow-diagram__empty-icon {
            display: inline-flex;
            height: 4rem;
            width: 4rem;
            align-items: center;
            justify-content: center;
            border-radius: 1.25rem;
            border: 1px solid color-mix(in srgb, var(--project-color) 24%, transparent);
            background: color-mix(in srgb, var(--project-color) 10%, #ffffff);
            color: color-mix(in srgb, var(--project-color) 72%, #0f172a);
        }

        .project-flow-diagram__empty-title {
            margin: 1rem 0 0;
            font-size: 0.95rem;
            font-weight: 800;
            color: rgb(15 23 42);
        }

        .project-flow-diagram__empty-copy {
            margin: 0.45rem auto 0;
            max-width: 28rem;
            font-size: 0.8125rem;
            line-height: 1.55;
            color: rgb(100 116 139);
        }

        .project-flow-diagram__empty-actions {
            justify-content: center;
            margin-top: 1rem;
        }

        :is(.dark, .dark *) .project-flow-diagram__stat,
        :is(.dark, .dark *) .project-flow-diagram__filter,
        :is(.dark, .dark *) .project-flow-diagram__action--ghost,
        :is(.dark, .dark *) .project-flow-diagram__node,
        :is(.dark, .dark *) .project-flow-diagram__empty {
            background: rgb(15 23 42 / 0.92);
            border-color: rgb(255 255 255 / 0.12);
            color: rgb(226 232 240);
        }

        :is(.dark, .dark *) .project-flow-diagram__canvas {
            background:
                radial-gradient(circle at top, color-mix(in srgb, var(--project-color) 16%, #0f172a), rgb(2 6 23) 48%),
                linear-gradient(180deg, rgb(15 23 42), rgb(2 6 23));
        }

        :is(.dark, .dark *) .project-flow-diagram__node-title,
        :is(.dark, .dark *) .project-flow-diagram__empty-title {
            color: rgb(248 250 252);
        }

        :is(.dark, .dark *) .project-flow-diagram__stat-value {
            color: color-mix(in srgb, var(--project-color) 55%, #f8fafc);
        }
    </style>
</div>
