@php
    $projectName = (string) ($project_name ?? 'Proyecto');
    $projectColor = (string) ($project_color ?? '#6366f1');
    $startLabel = (string) ($start_label ?? '—');
    $endLabel = (string) ($end_label ?? '—');
    $hasStart = (bool) ($has_start ?? false);
    $hasEnd = (bool) ($has_end ?? false);
    $timelineLabel = (string) ($timeline_label ?? 'Planificación');
    $timelineTone = (string) ($timeline_tone ?? 'muted');
@endphp

<div class="fi-scoped">
    <div
        class="project-dates-highlight overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-sm dark:border-white/10 dark:bg-gray-950/90 dark:shadow-none"
        style="--project-color: {{ $projectColor }};"
    >
        <div class="project-dates-highlight__header border-b border-gray-100 px-4 py-3 sm:px-5 dark:border-white/10">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                        Ventana temporal del proyecto
                    </p>
                    <p class="mt-0.5 truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $projectName }}</p>
                </div>
                <span class="project-dates-highlight__badge inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-wide">
                    <x-filament::icon icon="heroicon-m-calendar-days" class="size-3.5" />
                    {{ $timelineLabel }}
                </span>
            </div>
        </div>

        <div class="project-dates-highlight__body grid gap-3 px-4 py-4 sm:grid-cols-2 sm:px-5 sm:py-5">
            <div class="project-dates-highlight__card rounded-2xl border px-4 py-4">
                <div class="flex items-start gap-3">
                    <span class="project-dates-highlight__icon inline-flex size-10 shrink-0 items-center justify-center rounded-2xl">
                        <x-filament::icon icon="heroicon-m-play" class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">
                            Fecha de inicio
                        </p>
                        <p class="project-dates-highlight__value mt-1 text-lg font-bold tracking-tight sm:text-xl">
                            {{ $hasStart ? $startLabel : '—' }}
                        </p>
                        @if (! $hasStart)
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Sin fecha de inicio registrada</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="project-dates-highlight__card rounded-2xl border px-4 py-4">
                <div class="flex items-start gap-3">
                    <span class="project-dates-highlight__icon inline-flex size-10 shrink-0 items-center justify-center rounded-2xl">
                        <x-filament::icon icon="heroicon-m-flag" class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500 dark:text-gray-400">
                            Fecha de fin
                        </p>
                        <p class="project-dates-highlight__value mt-1 text-lg font-bold tracking-tight sm:text-xl">
                            {{ $hasEnd ? $endLabel : '—' }}
                        </p>
                        @if (! $hasEnd)
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Sin fecha de fin registrada</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .project-dates-highlight {
            border-color: color-mix(in srgb, var(--project-color) 28%, rgb(226 232 240));
        }

        .project-dates-highlight__header {
            background: linear-gradient(
                135deg,
                color-mix(in srgb, var(--project-color) 10%, #ffffff),
                color-mix(in srgb, var(--project-color) 4%, #f8fafc)
            );
        }

        .project-dates-highlight__badge {
            border-color: color-mix(in srgb, var(--project-color) 35%, transparent);
            background: color-mix(in srgb, var(--project-color) 14%, #ffffff);
            color: color-mix(in srgb, var(--project-color) 82%, #0f172a);
        }

        .project-dates-highlight__body {
            background: linear-gradient(
                180deg,
                color-mix(in srgb, var(--project-color) 5%, #ffffff) 0%,
                #ffffff 100%
            );
        }

        .project-dates-highlight__card {
            border-color: color-mix(in srgb, var(--project-color) 24%, rgb(226 232 240));
            background: color-mix(in srgb, var(--project-color) 6%, #ffffff);
            box-shadow: inset 0 1px 0 rgb(255 255 255 / 0.65);
        }

        .project-dates-highlight__icon {
            border: 1px solid color-mix(in srgb, var(--project-color) 28%, transparent);
            background: color-mix(in srgb, var(--project-color) 16%, #ffffff);
            color: color-mix(in srgb, var(--project-color) 78%, #0f172a);
        }

        .project-dates-highlight__value {
            color: color-mix(in srgb, var(--project-color) 70%, #0f172a);
        }

        :is(.dark, .dark *) .project-dates-highlight {
            border-color: color-mix(in srgb, var(--project-color) 38%, rgb(255 255 255 / 0.12));
            background: rgb(3 7 18 / 0.92);
        }

        :is(.dark, .dark *) .project-dates-highlight__header {
            background: linear-gradient(
                135deg,
                color-mix(in srgb, var(--project-color) 22%, #0f172a),
                color-mix(in srgb, var(--project-color) 10%, #020617)
            );
        }

        :is(.dark, .dark *) .project-dates-highlight__badge {
            border-color: color-mix(in srgb, var(--project-color) 45%, transparent);
            background: color-mix(in srgb, var(--project-color) 24%, #0f172a);
            color: color-mix(in srgb, var(--project-color) 55%, #f8fafc);
        }

        :is(.dark, .dark *) .project-dates-highlight__body {
            background: linear-gradient(
                180deg,
                color-mix(in srgb, var(--project-color) 12%, #0f172a) 0%,
                rgb(3 7 18) 100%
            );
        }

        :is(.dark, .dark *) .project-dates-highlight__card {
            border-color: color-mix(in srgb, var(--project-color) 34%, rgb(255 255 255 / 0.12));
            background: color-mix(in srgb, var(--project-color) 14%, #0f172a);
            box-shadow: none;
        }

        :is(.dark, .dark *) .project-dates-highlight__icon {
            border-color: color-mix(in srgb, var(--project-color) 40%, transparent);
            background: color-mix(in srgb, var(--project-color) 24%, #0f172a);
            color: color-mix(in srgb, var(--project-color) 58%, #f8fafc);
        }

        :is(.dark, .dark *) .project-dates-highlight__value {
            color: color-mix(in srgb, var(--project-color) 52%, #f8fafc);
        }
    </style>
</div>
