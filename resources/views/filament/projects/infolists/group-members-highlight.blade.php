@php
    $groupName = (string) ($group_name ?? 'Equipo');
    $groupColor = (string) ($group_color ?? '#6366f1');
    $total = (int) ($total ?? 0);
    $label = (string) ($label ?? 'Sin integrantes');
    $tone = (string) ($tone ?? 'gray');
    $hasMembers = (bool) ($has_members ?? false);
    $members = $members ?? [];

    $toneClass = match ($tone) {
        'success' => 'text-success-600 dark:text-success-400',
        'info' => 'text-info-600 dark:text-info-400',
        default => 'text-gray-500 dark:text-gray-400',
    };
@endphp

<div class="fi-scoped">
    @if (! $hasMembers)
        <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50/90 p-8 text-center dark:border-white/15 dark:bg-white/[0.03]">
            <x-filament::icon icon="heroicon-o-user-plus" class="mx-auto size-10 text-gray-400 dark:text-gray-500" />
            <p class="mt-3 text-sm font-semibold text-gray-700 dark:text-gray-200">Sin integrantes asignados</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Edita el grupo para asociar colaboradores al equipo.
            </p>
        </div>
    @else
        <div
            class="group-members-highlight overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-sm dark:border-white/10 dark:bg-gray-950/90 dark:shadow-none"
            style="--group-color: {{ $groupColor }};"
        >
            <div class="group-members-highlight__header border-b border-gray-100 px-4 py-3 sm:px-5 dark:border-white/10">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                            Integrantes del equipo
                        </p>
                        <p class="mt-0.5 truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $groupName }}</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="group-members-highlight__badge inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-wide">
                            <x-filament::icon icon="heroicon-m-users" class="size-3.5" />
                            {{ $label }}
                        </span>
                        <span
                            class="inline-flex shrink-0 items-center justify-center rounded-full border px-3 py-1 text-xs font-bold tabular-nums text-gray-950 dark:text-white"
                            style="border-color: color-mix(in srgb, var(--group-color) 35%, transparent); background: color-mix(in srgb, var(--group-color) 14%, #ffffff);"
                        >
                            {{ $total }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="group-members-highlight__body px-4 py-4 sm:px-5 sm:py-5">
                <p class="mb-4 text-xs font-medium {{ $toneClass }}">
                    Colaboradores activos vinculados a este grupo de trabajo.
                </p>

                <ul class="group-members-highlight__grid" role="list">
                    @foreach ($members as $index => $member)
                        <li
                            class="group-members-highlight__member"
                            wire:key="group-member-{{ $member['id'] ?? $index }}"
                        >
                            <div class="group-members-highlight__member-inner">
                                <span class="group-members-highlight__position" aria-hidden="true">
                                    {{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}
                                </span>

                                @if ($member['avatar_url'] ?? null)
                                    <img
                                        src="{{ $member['avatar_url'] }}"
                                        alt="{{ $member['name'] ?? 'Colaborador' }}"
                                        class="group-members-highlight__avatar size-12 rounded-2xl border-2 object-cover"
                                    >
                                @else
                                    <span class="group-members-highlight__avatar group-members-highlight__avatar--initials inline-flex size-12 items-center justify-center rounded-2xl border-2 text-sm font-bold">
                                        {{ $member['initials'] ?? 'NA' }}
                                    </span>
                                @endif

                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">
                                        {{ $member['name'] ?? 'Colaborador' }}
                                    </p>
                                    <p class="mt-0.5 text-[11px] font-medium text-gray-500 dark:text-gray-400">
                                        Integrante del equipo
                                    </p>
                                </div>

                                <x-filament::icon
                                    icon="heroicon-m-check-badge"
                                    class="group-members-highlight__check size-5 shrink-0"
                                />
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        <style>
            .group-members-highlight {
                border-color: color-mix(in srgb, var(--group-color) 28%, rgb(226 232 240));
            }

            .group-members-highlight__header {
                background: linear-gradient(
                    135deg,
                    color-mix(in srgb, var(--group-color) 10%, #ffffff),
                    color-mix(in srgb, var(--group-color) 4%, #f8fafc)
                );
            }

            .group-members-highlight__badge {
                border-color: color-mix(in srgb, var(--group-color) 35%, transparent);
                background: color-mix(in srgb, var(--group-color) 14%, #ffffff);
                color: color-mix(in srgb, var(--group-color) 82%, #0f172a);
            }

            .group-members-highlight__body {
                background: linear-gradient(
                    180deg,
                    color-mix(in srgb, var(--group-color) 5%, #ffffff) 0%,
                    #ffffff 100%
                );
            }

            .group-members-highlight__grid {
                display: grid;
                gap: 0.75rem;
                margin: 0;
                padding: 0;
                list-style: none;
            }

            @media (min-width: 640px) {
                .group-members-highlight__grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            @media (min-width: 1024px) {
                .group-members-highlight__grid {
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                }
            }

            .group-members-highlight__member-inner {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                min-height: 4.5rem;
                padding: 0.875rem 1rem;
                border-radius: 1rem;
                border: 1px solid color-mix(in srgb, var(--group-color) 18%, rgb(226 232 240));
                background: color-mix(in srgb, var(--group-color) 4%, #ffffff);
                transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
            }

            .group-members-highlight__member-inner:hover {
                transform: translateY(-1px);
                border-color: color-mix(in srgb, var(--group-color) 34%, rgb(226 232 240));
                box-shadow: 0 10px 24px -18px color-mix(in srgb, var(--group-color) 55%, #0f172a);
            }

            .group-members-highlight__position {
                display: inline-flex;
                width: 1.75rem;
                shrink: 0;
                justify-content: center;
                font-size: 0.6875rem;
                font-weight: 700;
                letter-spacing: 0.08em;
                color: color-mix(in srgb, var(--group-color) 70%, #64748b);
            }

            .group-members-highlight__avatar {
                border-color: color-mix(in srgb, var(--group-color) 28%, #ffffff);
            }

            .group-members-highlight__avatar--initials {
                background: color-mix(in srgb, var(--group-color) 16%, #f8fafc);
                color: color-mix(in srgb, var(--group-color) 78%, #0f172a);
            }

            .group-members-highlight__check {
                color: color-mix(in srgb, var(--group-color) 72%, #64748b);
            }

            :is(.dark, .dark *) .group-members-highlight {
                border-color: color-mix(in srgb, var(--group-color) 38%, rgb(255 255 255 / 0.12));
                background: rgb(3 7 18 / 0.92);
            }

            :is(.dark, .dark *) .group-members-highlight__header {
                background: linear-gradient(
                    135deg,
                    color-mix(in srgb, var(--group-color) 22%, #0f172a),
                    color-mix(in srgb, var(--group-color) 10%, #020617)
                );
            }

            :is(.dark, .dark *) .group-members-highlight__badge {
                border-color: color-mix(in srgb, var(--group-color) 45%, transparent);
                background: color-mix(in srgb, var(--group-color) 24%, #0f172a);
                color: color-mix(in srgb, var(--group-color) 55%, #f8fafc);
            }

            :is(.dark, .dark *) .group-members-highlight__body {
                background: linear-gradient(
                    180deg,
                    color-mix(in srgb, var(--group-color) 12%, #0f172a) 0%,
                    rgb(3 7 18) 100%
                );
            }

            :is(.dark, .dark *) .group-members-highlight__member-inner {
                border-color: color-mix(in srgb, var(--group-color) 28%, rgb(255 255 255 / 0.1));
                background: color-mix(in srgb, var(--group-color) 10%, #0f172a);
            }

            :is(.dark, .dark *) .group-members-highlight__avatar--initials {
                background: color-mix(in srgb, var(--group-color) 24%, #0f172a);
                color: color-mix(in srgb, var(--group-color) 45%, #f8fafc);
            }
        </style>
    @endif
</div>
