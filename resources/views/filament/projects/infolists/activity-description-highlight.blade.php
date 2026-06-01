@php
    $activityTitle = (string) ($activity_title ?? 'Actividad');
    $activityColor = (string) ($activity_color ?? '#6366f1');
    $description = (string) ($description ?? '');
    $hasDescription = (bool) ($has_description ?? filled($description));
@endphp

<div class="fi-scoped">
    @if (! $hasDescription)
        <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50/90 p-8 text-center dark:border-white/15 dark:bg-white/[0.03]">
            <x-filament::icon icon="heroicon-o-document-text" class="mx-auto size-10 text-gray-400 dark:text-gray-500" />
            <p class="mt-3 text-sm font-semibold text-gray-700 dark:text-gray-200">Sin descripción registrada</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Agrega el alcance o detalle de la tarea al editar la actividad.
            </p>
        </div>
    @else
        <div
            class="activity-description-highlight overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-sm dark:border-white/10 dark:bg-gray-950/90 dark:shadow-none"
            style="--activity-color: {{ $activityColor }};"
        >
            <div class="activity-description-highlight__header border-b border-gray-100 px-4 py-3 sm:px-5 dark:border-white/10">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                            Descripción de la actividad
                        </p>
                        <p class="mt-0.5 truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $activityTitle }}</p>
                    </div>
                    <span class="activity-description-highlight__badge inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-wide">
                        <x-filament::icon icon="heroicon-m-sparkles" class="size-3.5" />
                        Detalle resaltado
                    </span>
                </div>
            </div>

            <div class="activity-description-highlight__body px-4 py-4 sm:px-5 sm:py-5">
                <p class="activity-description-highlight__text text-justify text-sm font-medium leading-7 sm:text-[0.9375rem] sm:leading-8">
                    {{ $description }}
                </p>
            </div>
        </div>

        <style>
            .activity-description-highlight {
                border-color: color-mix(in srgb, var(--activity-color) 28%, rgb(226 232 240));
            }

            .activity-description-highlight__header {
                background: linear-gradient(
                    135deg,
                    color-mix(in srgb, var(--activity-color) 10%, #ffffff),
                    color-mix(in srgb, var(--activity-color) 4%, #f8fafc)
                );
            }

            .activity-description-highlight__badge {
                border-color: color-mix(in srgb, var(--activity-color) 35%, transparent);
                background: color-mix(in srgb, var(--activity-color) 14%, #ffffff);
                color: color-mix(in srgb, var(--activity-color) 82%, #0f172a);
            }

            .activity-description-highlight__body {
                background: linear-gradient(
                    180deg,
                    color-mix(in srgb, var(--activity-color) 5%, #ffffff) 0%,
                    #ffffff 100%
                );
            }

            .activity-description-highlight__text {
                margin: 0;
                padding: 0;
                text-indent: 0;
                color: rgb(30 41 59);
                text-align: justify;
                text-justify: inter-word;
            }

            :is(.dark, .dark *) .activity-description-highlight {
                border-color: color-mix(in srgb, var(--activity-color) 38%, rgb(255 255 255 / 0.12));
                background: rgb(3 7 18 / 0.92);
            }

            :is(.dark, .dark *) .activity-description-highlight__header {
                background: linear-gradient(
                    135deg,
                    color-mix(in srgb, var(--activity-color) 22%, #0f172a),
                    color-mix(in srgb, var(--activity-color) 10%, #020617)
                );
            }

            :is(.dark, .dark *) .activity-description-highlight__badge {
                border-color: color-mix(in srgb, var(--activity-color) 45%, transparent);
                background: color-mix(in srgb, var(--activity-color) 24%, #0f172a);
                color: color-mix(in srgb, var(--activity-color) 55%, #f8fafc);
            }

            :is(.dark, .dark *) .activity-description-highlight__body {
                background: linear-gradient(
                    180deg,
                    color-mix(in srgb, var(--activity-color) 12%, #0f172a) 0%,
                    rgb(3 7 18) 100%
                );
            }

            :is(.dark, .dark *) .activity-description-highlight__text {
                color: rgb(241 245 249);
            }
        </style>
    @endif
</div>
