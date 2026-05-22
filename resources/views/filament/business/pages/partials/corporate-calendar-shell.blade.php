        <section class="relative overflow-hidden rounded-[2rem] border border-slate-200/70 bg-white/90 p-4 shadow-[0_8px_32px_rgba(15,23,42,0.08)] backdrop-blur-xl dark:border-white/10 dark:bg-slate-900/70 dark:shadow-[0_16px_48px_rgba(0,0,0,0.5)] sm:p-6">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(56,189,248,0.14),transparent_45%)] dark:bg-[radial-gradient(circle_at_top,rgba(56,189,248,0.18),transparent_45%)]"></div>

            <header class="relative z-10 mb-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">
                        {{ $this->corporateCalendarHeading() }}
                    </p>
                    <h2 class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100 sm:text-4xl">
                        {{ \Illuminate\Support\Str::headline($this->monthLabel) }}
                    </h2>
                </div>

                <div class="flex items-center gap-2 self-start rounded-2xl border border-slate-200/80 bg-white/90 p-1.5 shadow-sm dark:border-white/10 dark:bg-slate-900/80">
                    <button
                        type="button"
                        wire:click="previousMonth"
                        wire:loading.attr="disabled"
                        wire:target="previousMonth"
                        class="inline-flex size-10 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white"
                        aria-label="Mes anterior"
                    >
                        <x-filament::icon icon="heroicon-o-arrow-path" wire:loading wire:target="previousMonth" class="size-5 animate-spin" />
                        <x-filament::icon icon="heroicon-o-chevron-left" wire:loading.remove wire:target="previousMonth" class="size-5" />
                    </button>

                    <button
                        type="button"
                        wire:click="goToday"
                        wire:loading.attr="disabled"
                        wire:target="goToday"
                        class="inline-flex min-w-24 items-center justify-center rounded-xl bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                    >
                        <x-filament::icon icon="heroicon-o-arrow-path" wire:loading wire:target="goToday" class="mr-1 size-4 animate-spin" />
                        <span>Hoy</span>
                    </button>

                    <button
                        type="button"
                        wire:click="nextMonth"
                        wire:loading.attr="disabled"
                        wire:target="nextMonth"
                        class="inline-flex size-10 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white"
                        aria-label="Mes siguiente"
                    >
                        <x-filament::icon icon="heroicon-o-arrow-path" wire:loading wire:target="nextMonth" class="size-5 animate-spin" />
                        <x-filament::icon icon="heroicon-o-chevron-right" wire:loading.remove wire:target="nextMonth" class="size-5" />
                    </button>

                    @if ($this->viewMode === 'month')
                        <button
                            type="button"
                            wire:click="setWeekView"
                            wire:loading.attr="disabled"
                            wire:target="setWeekView"
                            class="inline-flex min-w-36 items-center justify-center rounded-xl bg-cyan-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-cyan-400"
                        >
                            <x-filament::icon icon="heroicon-o-arrow-path" wire:loading wire:target="setWeekView" class="mr-1 size-4 animate-spin" />
                            <span>Ver por semana</span>
                        </button>
                    @else
                        <button
                            type="button"
                            wire:click="setMonthView"
                            wire:loading.attr="disabled"
                            wire:target="setMonthView"
                            class="inline-flex min-w-32 items-center justify-center rounded-xl bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                        >
                            <x-filament::icon icon="heroicon-o-arrow-path" wire:loading wire:target="setMonthView" class="mr-1 size-4 animate-spin" />
                            <span>Ver por mes</span>
                        </button>
                    @endif
                </div>
            </header>

            @if (method_exists($this, 'shouldShowTdgAgendaFilters') && $this->shouldShowTdgAgendaFilters())
                @include('filament.business.pages.partials.tdg-calendar-header-filters')
            @endif

            <div class="relative z-10 overflow-x-auto">
                @if ($this->viewMode === 'week')
                    @php
                        $timelineStartMinutes = 6 * 60;
                        $timelineEndMinutes = 22 * 60;
                        $minutesPerPixelBlock = 60;
                        $hourRowHeight = 56;
                        $timelineHours = range(6, 22);
                        $timelineHeight = (count($timelineHours) - 1) * $hourRowHeight;
                        $weekActivityThemes = [
                            'from-cyan-400/95 to-sky-500/95 shadow-[0_10px_30px_rgba(56,189,248,0.28)]',
                            'from-teal-400/95 to-cyan-500/95 shadow-[0_10px_30px_rgba(20,184,166,0.28)]',
                            'from-emerald-400/95 to-teal-500/95 shadow-[0_10px_30px_rgba(16,185,129,0.26)]',
                            'from-amber-400/95 to-orange-500/95 shadow-[0_10px_30px_rgba(245,158,11,0.28)]',
                            'from-rose-400/95 to-orange-500/95 shadow-[0_10px_30px_rgba(251,113,133,0.28)]',
                        ];
                    @endphp

                    <div class="min-w-[980px] rounded-[1.65rem] border border-slate-200/80 bg-slate-50/70 p-3 shadow-[0_2px_16px_rgba(15,23,42,0.06)] dark:border-white/10 dark:bg-slate-950/40">
                        <div class="mb-3 grid grid-cols-7 gap-2">
                            @foreach ($this->currentWeekDays as $day)
                                <button
                                    type="button"
                                    wire:click="selectWeekDate('{{ $day['date'] }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="selectWeekDate"
                                    class="group rounded-2xl border px-2 py-2 text-center transition-all duration-200
                                    {{ $day['is_selected']
                                        ? 'border-cyan-400/55 bg-white text-slate-900 shadow-[0_8px_22px_rgba(15,23,42,0.12)] ring-2 ring-cyan-400/45 dark:border-cyan-400/40 dark:bg-slate-900/90 dark:text-slate-100 dark:shadow-[0_12px_28px_rgba(0,0,0,0.45)] dark:ring-cyan-300/50'
                                        : 'border-slate-200/80 bg-white/90 text-slate-700 shadow-[0_1px_6px_rgba(15,23,42,0.04)] hover:border-cyan-200/70 hover:bg-slate-50 dark:border-white/10 dark:bg-slate-900/60 dark:text-slate-200 dark:hover:border-cyan-500/30 dark:hover:bg-slate-800/80' }}"
                                >
                                    <p class="text-[11px] font-semibold tracking-[0.14em] {{ $day['is_selected'] ? 'text-cyan-600 dark:text-cyan-300' : 'text-slate-500 dark:text-slate-400' }}">
                                        {{ $day['day_label'] }}
                                    </p>
                                    <p class="mt-1 text-2xl font-semibold leading-none {{ $day['is_selected'] ? 'text-slate-900 dark:text-white' : 'text-slate-800 dark:text-slate-100' }}">{{ $day['day_number'] }}</p>
                                    <div class="mt-2 flex flex-col items-center gap-1.5">
                                        <div class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $day['is_selected'] ? 'bg-cyan-100 text-cyan-800 dark:bg-cyan-500/25 dark:text-cyan-100' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">
                                            {{ $day['activity_count'] }} {{ $day['activity_count'] === 1 ? 'asignación' : 'asignaciones' }}
                                        </div>
                                        @if (
                                            ! empty($day['social_badges'])
                                            && isset($day['social_badges'][0]['chip_class'])
                                            && ($day['department_label_mode'] ?? 'short') !== 'full'
                                        )
                                            <div class="flex flex-wrap items-center justify-center gap-1">
                                                @foreach ($day['social_badges'] as $badge)
                                                    <span
                                                        title="{{ $badge['label'] ?? 'Departamento' }}"
                                                        class="inline-flex size-5 items-center justify-center rounded-full text-[8px] font-bold text-white ring-2 {{ $badge['dot_class'] ?? 'bg-slate-500 ring-slate-300/70' }}"
                                                    >
                                                        {{ $badge['short_label'] ?? '' }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                        @if ($this->canManageSocialPublications() && ! empty($day['social_badges']) && ! isset($day['social_badges'][0]['chip_class']))
                                            <div class="flex flex-wrap items-center justify-center gap-1">
                                                @foreach ($day['social_badges'] as $badge)
                                                    <div class="group/social relative inline-flex">
                                                        <x-corporate-agenda-social-icon :platform="$badge['platform']" size="sm" />
                                                        @if (! empty($badge['media']))
                                                            @php
                                                                $mediaCount = count($badge['media']);
                                                                $previewWidthClass = match (true) {
                                                                    $mediaCount <= 1 => 'w-44',
                                                                    $mediaCount === 2 => 'w-64',
                                                                    default => 'w-80',
                                                                };
                                                                $previewGridClass = match (true) {
                                                                    $mediaCount <= 1 => 'grid-cols-1',
                                                                    $mediaCount === 2 => 'grid-cols-2',
                                                                    default => 'grid-cols-3',
                                                                };
                                                                $previewMediaHeightClass = $mediaCount <= 1 ? 'h-28' : 'h-20';
                                                            @endphp
                                                            <div class="absolute bottom-full left-1/2 z-40 mb-2 hidden max-w-[calc(100vw-2rem)] -translate-x-1/2 overflow-hidden rounded-xl border border-slate-200/80 bg-white/95 p-2 shadow-xl group-hover/social:block dark:border-white/15 dark:bg-slate-900/95 {{ $previewWidthClass }}">
                                                                <div class="mb-1 flex items-center justify-between">
                                                                    <p class="truncate text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-700 dark:text-slate-200">{{ \App\Support\CorporateAgendaSocialPlatformCatalog::for((string) ($badge['platform'] ?? ''))['label'] ?? 'Publicación' }}</p>
                                                                    <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ count($badge['media']) }}</span>
                                                                </div>
                                                                <div class="grid gap-2 pb-1 {{ $previewGridClass }}">
                                                                    @foreach ($badge['media'] as $media)
                                                                        <div class="overflow-hidden rounded-lg border border-slate-200/70 bg-slate-50 dark:border-white/10 dark:bg-slate-800/70">
                                                                            @if (($media['type'] ?? 'image') === 'video')
                                                                                <div class="relative">
                                                                                    <video
                                                                                        src="{{ $media['url'] }}"
                                                                                        class="w-full object-cover {{ $previewMediaHeightClass }}"
                                                                                        controls
                                                                                        autoplay
                                                                                        muted
                                                                                        loop
                                                                                        playsinline
                                                                                        preload="metadata"
                                                                                    ></video>
                                                                                </div>
                                                                            @else
                                                                                <img
                                                                                    src="{{ $media['url'] }}"
                                                                                    alt="{{ $media['name'] ?? 'Vista previa' }}"
                                                                                    class="w-full object-cover {{ $previewMediaHeightClass }}"
                                                                                >
                                                                            @endif
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </button>
                            @endforeach
                        </div>

                        <div class="rounded-[1.4rem] border border-slate-200/80 bg-white p-3 shadow-[inset_0_1px_0_rgba(255,255,255,0.85)] dark:border-white/10 dark:bg-slate-900/50 dark:shadow-none">
                            <div class="grid grid-cols-[84px_minmax(0,1fr)] gap-3">
                                <div class="pt-1">
                                    @foreach ($timelineHours as $hour)
                                        <div class="flex h-14 items-start justify-end pr-2 text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                                            {{ \Carbon\Carbon::createFromTime($hour)->format('g A') }}
                                        </div>
                                    @endforeach
                                </div>

                                <div class="relative min-h-[{{ $timelineHeight }}px] rounded-2xl border border-slate-200/70 bg-slate-50/90 px-2 py-1 dark:border-white/10 dark:bg-slate-950/50">
                                    @foreach (range(0, count($timelineHours) - 1) as $index)
                                        <div
                                            class="pointer-events-none absolute left-0 right-0 border-t border-slate-200/80 dark:border-white/10"
                                            style="top: {{ $index * $hourRowHeight }}px;"
                                        ></div>
                                    @endforeach

                                    @forelse ($this->weekSelectedDayActivities as $activity)
                                        @php
                                            $startParts = array_map('intval', explode(':', (string) $activity->start_time));
                                            $endParts = array_map('intval', explode(':', (string) $activity->end_time));
                                            $startMinutes = (($startParts[0] ?? 0) * 60) + ($startParts[1] ?? 0);
                                            $endMinutes = (($endParts[0] ?? 0) * 60) + ($endParts[1] ?? 0);
                                            $safeStartMinutes = max($timelineStartMinutes, min($timelineEndMinutes, $startMinutes));
                                            $safeEndMinutes = max($safeStartMinutes + 30, min($timelineEndMinutes, $endMinutes));
                                            $topOffsetPx = (($safeStartMinutes - $timelineStartMinutes) / $minutesPerPixelBlock) * $hourRowHeight;
                                            $heightPx = max((($safeEndMinutes - $safeStartMinutes) / $minutesPerPixelBlock) * $hourRowHeight, 44);
                                            $activityThemeClass = $weekActivityThemes[crc32((string) $activity->id) % count($weekActivityThemes)];
                                            $participantsWithStatus = $activity->participants
                                                ->filter(fn ($participant) => $participant->colaborador !== null);
                                        @endphp

                                        <button
                                            type="button"
                                            @if ($this->calendarDayInteractionsEnabled())
                                                wire:click="openDayModal('{{ $activity->activity_date->toDateString() }}')"
                                            @endif
                                            class="group absolute left-3 right-4 overflow-hidden rounded-2xl border border-white/20 bg-gradient-to-r px-4 py-2 text-left text-white transition hover:scale-[1.01] hover:border-white/50 {{ $activityThemeClass }}"
                                            style="top: {{ $topOffsetPx }}px; min-height: {{ $heightPx }}px;"
                                        >
                                            <p class="text-[11px] font-semibold tracking-[0.08em] text-white/90">
                                                {{ \Carbon\Carbon::parse((string) $activity->start_time)->format('g:i A') }}
                                                -
                                                {{ \Carbon\Carbon::parse((string) $activity->end_time)->format('g:i A') }}
                                            </p>
                                            <p class="mt-0.5 truncate text-sm font-semibold">{{ $activity->title }}</p>

                                            @if (filled($activity->description))
                                                <p class="mt-1 line-clamp-2 text-xs text-white/85">
                                                    {{ \Illuminate\Support\Str::limit(strip_tags((string) $activity->description), 120) }}
                                                </p>
                                            @endif

                                            @if ($participantsWithStatus->isNotEmpty())
                                                <div class="mt-2 flex items-center justify-end -space-x-1.5">
                                                    @foreach ($participantsWithStatus as $participant)
                                                        @php
                                                            $colaborador = $participant->colaborador;
                                                            $avatarPath = is_string($colaborador?->avatar) ? ltrim((string) $colaborador->avatar, '/') : '';
                                                            $avatarUrl = ($avatarPath !== '' && \Illuminate\Support\Facades\Storage::disk('public')->exists($avatarPath))
                                                                ? url('storage/'.$avatarPath)
                                                                : null;
                                                            $fullName = (string) ($colaborador?->fullName ?? '');
                                                            $nameParts = collect(preg_split('/\s+/', trim($fullName)) ?: [])->filter();
                                                            $initials = $nameParts->count() > 1
                                                                ? \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr((string) $nameParts->first(), 0, 1).\Illuminate\Support\Str::substr((string) $nameParts->last(), 0, 1))
                                                                : \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr((string) $nameParts->first(), 0, 2));
                                                            $initials = $initials !== '' ? $initials : '--';
                                                            $invitationStatusValue = $participant->invitation_status?->value ?? \App\Enums\CorporateAgendaInvitationStatus::Pending->value;
                                                            $avatarStatusClass = match ($invitationStatusValue) {
                                                                \App\Enums\CorporateAgendaInvitationStatus::Accepted->value => 'border-emerald-300 ring-2 ring-emerald-300/70 shadow-[0_10px_18px_rgba(16,185,129,0.32)]',
                                                                \App\Enums\CorporateAgendaInvitationStatus::Rejected->value => 'border-rose-300 ring-2 ring-rose-300/70 shadow-[0_10px_18px_rgba(244,63,94,0.30)]',
                                                                default => 'border-white/85 ring-2 ring-cyan-200/35 shadow-[0_8px_16px_rgba(15,23,42,0.28)]',
                                                            };
                                                        @endphp

                                                        @if ($avatarUrl)
                                                            <img
                                                                src="{{ $avatarUrl }}"
                                                                alt="{{ $fullName !== '' ? $fullName : 'Colaborador' }}"
                                                                class="size-7 rounded-full border object-cover {{ $avatarStatusClass }}"
                                                            >
                                                        @else
                                                            <span class="inline-flex size-7 items-center justify-center rounded-full border bg-white/20 text-[10px] font-semibold text-white {{ $avatarStatusClass }}">
                                                                {{ $initials }}
                                                            </span>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif
                                        </button>
                                    @empty
                                        <div class="absolute inset-0 flex items-center justify-center">
                                            <div class="rounded-2xl border border-slate-200/80 bg-white/95 px-4 py-3 text-center shadow-sm dark:border-white/10 dark:bg-slate-900/80">
                                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">Sin actividades en este día</p>
                                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Selecciona otra fecha de la semana actual.</p>
                                            </div>
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="min-w-[960px] rounded-[1.65rem] border border-slate-200/80 bg-slate-50/70 p-3 dark:border-white/10 dark:bg-slate-950/40">
                        <div class="mb-3 grid grid-cols-7 gap-2">
                            @foreach ($this->weekdays as $weekday)
                                <div class="rounded-xl bg-slate-100/80 px-3 py-2 text-center text-sm font-semibold text-slate-600 dark:bg-slate-800/80 dark:text-slate-300">
                                    {{ $weekday }}
                                </div>
                            @endforeach
                        </div>

                        <div class="grid grid-cols-7 gap-2">
                            @foreach ($this->calendarDays as $day)
                                @php
                                    $progressFillClass = match (true) {
                                        ($day['activity_count'] ?? 0) >= 5 => 'bg-gradient-to-r from-[#191e2b] to-[#00c6e6] shadow-[0_4px_14px_rgba(0,198,230,0.35)]',
                                        ($day['activity_count'] ?? 0) === 4 => 'bg-gradient-to-r from-[#052659] to-[#3fb2dc] shadow-[0_4px_12px_rgba(63,178,220,0.30)]',
                                        ($day['activity_count'] ?? 0) === 3 => 'bg-gradient-to-r from-[#163a6b] to-[#7DA0CA] shadow-[0_3px_10px_rgba(84,134,179,0.28)]',
                                        ($day['activity_count'] ?? 0) === 2 => 'bg-gradient-to-r from-[#5486B3] to-[#9FC1DE] shadow-[0_3px_8px_rgba(84,134,179,0.22)]',
                                        ($day['activity_count'] ?? 0) === 1 => 'bg-gradient-to-r from-[#7DA0CA] to-[#C1E8FF] shadow-[0_3px_8px_rgba(125,160,202,0.20)]',
                                        default => 'bg-transparent',
                                    };
                                @endphp

                                <article
                                    @if ($this->calendarDayInteractionsEnabled() && $day['is_current_month'] && ! $day['is_past_date'])
                                        wire:click="openDayModal('{{ $day['date'] }}')"
                                    @endif
                                    class="group flex min-h-[130px] flex-col rounded-2xl border p-3 transition-all duration-200 ease-out
                                    {{ $day['is_current_month']
                                        ? 'border-slate-200/80 bg-white/90 shadow-[0_2px_12px_rgba(15,23,42,0.05)] hover:-translate-y-0.5 hover:shadow-[0_10px_24px_rgba(15,23,42,0.1)] dark:border-white/10 dark:bg-slate-900/85 dark:shadow-[0_8px_20px_rgba(0,0,0,0.35)] dark:hover:shadow-[0_16px_30px_rgba(0,0,0,0.45)]'
                                        : 'border-transparent bg-slate-100/55 opacity-55 dark:bg-slate-900/40 dark:opacity-45' }}
                                    {{ ($day['is_filtered_out'] ?? false) ? 'opacity-35 saturate-50' : '' }}
                                    {{ $day['is_past_date'] ? 'cursor-not-allowed opacity-75 hover:translate-y-0 hover:shadow-[0_2px_12px_rgba(15,23,42,0.05)] dark:hover:shadow-[0_8px_20px_rgba(0,0,0,0.35)]' : ($day['is_current_month'] ? 'cursor-pointer' : 'cursor-default') }}
                                    {{ $day['is_today'] ? 'ring-2 ring-cyan-400/60 dark:ring-cyan-300/70' : '' }}"
                                >
                                    <div class="mb-2 flex items-center justify-between gap-2">
                                        <span class="text-2xl font-semibold leading-none tracking-tight {{ $day['is_current_month'] ? 'text-slate-900 dark:text-slate-100' : 'text-slate-400 dark:text-slate-600' }}">
                                            {{ $day['day_number'] }}
                                        </span>

                                        <div class="flex items-center gap-1.5">
                                            @if ($this->canManageSocialPublications() && ! empty($day['social_badges']) && $day['is_current_month'])
                                                <div class="flex flex-wrap justify-end gap-1">
                                                    @foreach ($day['social_badges'] as $badge)
                                                        <div class="group/social relative inline-flex">
                                                            <x-corporate-agenda-social-icon :platform="$badge['platform']" size="sm" />
                                                            @if (! empty($badge['media']))
                                                                @php
                                                                    $mediaCount = count($badge['media']);
                                                                    $previewWidthClass = match (true) {
                                                                        $mediaCount <= 1 => 'w-48',
                                                                        $mediaCount === 2 => 'w-72',
                                                                        default => 'w-[22rem]',
                                                                    };
                                                                    $previewGridClass = match (true) {
                                                                        $mediaCount <= 1 => 'grid-cols-1',
                                                                        $mediaCount === 2 => 'grid-cols-2',
                                                                        default => 'grid-cols-3',
                                                                    };
                                                                    $previewMediaHeightClass = $mediaCount <= 1 ? 'h-32' : 'h-24';
                                                                @endphp
                                                                <div class="absolute bottom-full right-0 z-40 mb-2 hidden max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-slate-200/80 bg-white/95 p-2 shadow-xl group-hover/social:block dark:border-white/15 dark:bg-slate-900/95 {{ $previewWidthClass }}">
                                                                    <div class="mb-1 flex items-center justify-between">
                                                                        <p class="truncate text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-700 dark:text-slate-200">{{ \App\Support\CorporateAgendaSocialPlatformCatalog::for((string) ($badge['platform'] ?? ''))['label'] ?? 'Publicación' }}</p>
                                                                        <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ count($badge['media']) }}</span>
                                                                    </div>
                                                                    <div class="grid gap-2 pb-1 {{ $previewGridClass }}">
                                                                        @foreach ($badge['media'] as $media)
                                                                            <div class="overflow-hidden rounded-lg border border-slate-200/70 bg-slate-50 dark:border-white/10 dark:bg-slate-800/70">
                                                                                @if (($media['type'] ?? 'image') === 'video')
                                                                                    <div class="relative">
                                                                                        <video
                                                                                            src="{{ $media['url'] }}"
                                                                                            class="w-full object-cover {{ $previewMediaHeightClass }}"
                                                                                            controls
                                                                                            autoplay
                                                                                            muted
                                                                                            loop
                                                                                            playsinline
                                                                                            preload="metadata"
                                                                                        ></video>
                                                                                    </div>
                                                                                @else
                                                                                    <img
                                                                                        src="{{ $media['url'] }}"
                                                                                        alt="{{ $media['name'] ?? 'Vista previa' }}"
                                                                                        class="w-full object-cover {{ $previewMediaHeightClass }}"
                                                                                    >
                                                                                @endif
                                                                            </div>
                                                                        @endforeach
                                                                    </div>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif

                                            @if (
                                                ! empty($day['department_badges'])
                                                && $day['is_current_month']
                                                && ($day['department_label_mode'] ?? 'short') !== 'full'
                                            )
                                                <div class="flex flex-wrap justify-end gap-1">
                                                    @foreach ($day['department_badges'] as $badge)
                                                        <span
                                                            title="{{ $badge['label'] ?? 'Departamento' }}"
                                                            class="inline-flex size-5 items-center justify-center rounded-full text-[8px] font-bold text-white ring-2 {{ $badge['dot_class'] ?? 'bg-slate-500 ring-slate-300/70' }}"
                                                        >
                                                            {{ $badge['short_label'] ?? '' }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    @if ($day['is_current_month'])
                                        <div class="mt-auto space-y-2">
                                            @if (
                                                method_exists($this, 'shouldShowTdgAgendaFilters')
                                                && $this->shouldShowTdgAgendaFilters()
                                                && (($day['office_count'] ?? 0) > 0 || ($day['guard_count'] ?? 0) > 0)
                                            )
                                                <div class="flex flex-wrap justify-end gap-1">
                                                    @if (($day['office_count'] ?? 0) > 0)
                                                        <span class="inline-flex items-center rounded-full bg-cyan-100 px-2 py-0.5 text-[10px] font-semibold text-cyan-900 dark:bg-cyan-500/20 dark:text-cyan-100">
                                                            {{ $day['office_count'] }} {{ ($day['office_count'] ?? 0) === 1 ? 'oficina' : 'oficinas' }}
                                                        </span>
                                                    @endif
                                                    @if (($day['guard_count'] ?? 0) > 0)
                                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-900 dark:bg-amber-500/20 dark:text-amber-100">
                                                            {{ $day['guard_count'] }} guardia{{ ($day['guard_count'] ?? 0) === 1 ? '' : 's' }}
                                                        </span>
                                                    @endif
                                                </div>
                                            @endif

                                            @if (($day['activity_count'] ?? 0) > 0 && ! empty($day['avatars']) && ! ($day['is_filtered_out'] ?? false))
                                                <div class="mt-2 flex items-center justify-end gap-2">
                                                    @if (method_exists($this, 'shouldShowTdgAgendaFilters') && $this->shouldShowTdgAgendaFilters())
                                                        @include('filament.business.pages.partials.tdg-calendar-day-avatars', [
                                                            'avatars' => collect($day['avatars'] ?? [])->take(4)->all(),
                                                            'overflowCount' => $day['avatars_overflow'] ?? 0,
                                                            'tooltipLines' => $day['avatars_tooltip'] ?? [],
                                                        ])
                                                    @else
                                                    <div class="flex -space-x-2">
                                                        @foreach ($day['avatars'] as $avatar)
                                                        @php
                                                            $calendarAvatarStatusClass = match ($avatar['status'] ?? \App\Enums\CorporateAgendaInvitationStatus::Pending->value) {
                                                                \App\Enums\CorporateAgendaInvitationStatus::Accepted->value => 'border-emerald-600 dark:border-emerald-400 ring-1 ring-emerald-500/65 dark:ring-emerald-300/70 shadow-[0_8px_16px_rgba(5,150,105,0.32)]',
                                                                \App\Enums\CorporateAgendaInvitationStatus::Rejected->value => 'border-rose-600 dark:border-rose-400 ring-1 ring-rose-500/65 dark:ring-rose-300/70 shadow-[0_8px_16px_rgba(225,29,72,0.32)]',
                                                                default => 'border-slate-300 dark:border-slate-600 ring-1 ring-slate-300/70 dark:ring-slate-600/70 shadow-sm',
                                                            };
                                                        @endphp
                                                            <div class="group/av relative inline-flex">
                                                                @if ($avatar['avatar_url'])
                                                                    <img
                                                                        src="{{ $avatar['avatar_url'] }}"
                                                                        alt="{{ $avatar['name'] }}"
                                                                    class="size-6 rounded-full border object-cover {{ $calendarAvatarStatusClass }}"
                                                                    >
                                                                @else
                                                                <span class="inline-flex size-6 items-center justify-center rounded-full border bg-slate-200 text-[10px] font-semibold text-slate-700 dark:bg-slate-700 dark:text-slate-100 {{ $calendarAvatarStatusClass }}">
                                                                        {{ $avatar['initials'] }}
                                                                    </span>
                                                                @endif

                                                                <div class="pointer-events-none absolute bottom-full left-1/2 z-30 mb-2 hidden w-56 -translate-x-1/2 rounded-xl border border-slate-200/80 bg-white/95 p-2 text-left shadow-xl group-hover/av:block dark:border-white/10 dark:bg-slate-900/95">
                                                                    <p class="truncate text-[11px] font-semibold uppercase tracking-[0.04em] text-slate-800 dark:text-slate-100">
                                                                        {{ $avatar['name'] ?: 'Colaborador' }}
                                                                    </p>
                                                                    <p class="mt-1 truncate text-[11px] text-slate-600 dark:text-slate-300">
                                                                        {{ $avatar['email'] ?: 'Sin correo corporativo' }}
                                                                    </p>
                                                                    @if (! empty($avatar['activity_titles']))
                                                                        <div class="mt-2 border-t border-slate-200/80 pt-2 dark:border-slate-700/80">
                                                                            @foreach ($avatar['activity_titles'] as $title)
                                                                                <p class="truncate text-[11px] text-slate-700 dark:text-slate-200">{{ $title }}</p>
                                                                            @endforeach
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                    @endif
                                                </div>
                                            @elseif (filled($day['task_primary']) || filled($day['task_secondary']))
                                                <div class="space-y-1">
                                                    @if (filled($day['task_primary']))
                                                        <p class="truncate text-xs font-semibold text-slate-700 dark:text-slate-200">{{ $day['task_primary'] }}</p>
                                                    @endif
                                                    @if (filled($day['task_secondary']))
                                                        <p class="truncate text-[11px] text-slate-500 dark:text-slate-400">{{ $day['task_secondary'] }}</p>
                                                    @endif
                                                </div>
                                            @endif

                                            <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-slate-200/90 dark:bg-[#1a2233]">
                                                <div
                                                    class="h-full rounded-full transition-all duration-300 {{ $progressFillClass }}"
                                                    style="width: {{ $day['progress_width'] }}%;"
                                                ></div>
                                            </div>
                                        </div>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </section>
