<x-filament-panels::page>
    <div class="w-full">
        @include('filament.business.pages.partials.corporate-calendar-shell')
        <div
            x-data="{ open: $wire.entangle('isActivityModalOpen') }"
            x-show="open"
            x-cloak
            x-transition.opacity
            class="fixed inset-0 z-[80] flex items-center justify-center p-4 sm:p-6"
        >
            <div class="absolute inset-0 bg-slate-950/55 backdrop-blur-[2px]" wire:click="closeActivityModal"></div>

            <section class="relative z-[81] flex w-full max-w-7xl max-h-[90vh] flex-col overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-2xl dark:border-white/10 dark:bg-slate-900">
                <header class="shrink-0 border-b border-slate-200/80 px-5 py-4 dark:border-white/10">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">
                                {{ \Carbon\Carbon::parse($selectedDate)->translatedFormat('l d \\d\\e F Y') }}
                            </p>
                            <h3 class="mt-1 text-base font-semibold text-slate-900 dark:text-slate-100">
                                Gestión del día
                            </h3>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                Alterna entre actividades corporativas y el calendario publicitario de marketing.
                            </p>
                        </div>

                        <button
                            type="button"
                            wire:click="closeActivityModal"
                            class="inline-flex items-center justify-center rounded-full p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700 dark:text-slate-300 dark:hover:bg-white/10 dark:hover:text-white"
                        >
                            <x-filament::icon icon="heroicon-o-x-mark" class="size-5" />
                        </button>
                    </div>

                    <div class="mt-4 grid gap-2 sm:grid-cols-2" role="tablist" aria-label="Espacios de trabajo de la agenda">
                        <button
                            type="button"
                            wire:click="setModalWorkspace('activities')"
                            class="rounded-2xl border px-3 py-3 text-left transition
                            {{ $modalWorkspace === 'activities'
                                ? 'border-[#4E8EA2]/80 bg-[#BDD8E9]/90 shadow-[0_8px_22px_rgba(78,142,162,0.25)] dark:border-[#7BBDE8]/70 dark:bg-[#0A4174]/55'
                                : 'border-slate-200/80 bg-white/80 hover:border-[#7BBDE8]/80 hover:bg-[#BDD8E9]/35 dark:border-white/10 dark:bg-slate-900/70 dark:hover:border-[#6EA2B3]/60 dark:hover:bg-[#0A4174]/25' }}"
                            role="tab"
                            aria-selected="{{ $modalWorkspace === 'activities' ? 'true' : 'false' }}"
                        >
                            <div class="flex items-center gap-2">
                                <span class="inline-flex size-8 items-center justify-center rounded-xl bg-[#7BBDE8]/40 text-[#0A4174] dark:bg-[#4E8EA2]/35 dark:text-[#BDD8E9]">
                                    <x-filament::icon icon="heroicon-o-calendar-days" class="size-4" />
                                </span>
                                <span>
                                    <span class="block text-xs font-semibold text-slate-900 dark:text-slate-100">Actividades corporativas</span>
                                    <span class="mt-0.5 block text-[11px] text-slate-500 dark:text-slate-400">Reuniones, meet y participantes</span>
                                </span>
                            </div>
                        </button>

                        @if ($this->canManageSocialPublications())
                            <button
                                type="button"
                                wire:click="setModalWorkspace('marketing')"
                                class="rounded-2xl border px-3 py-3 text-left transition
                                {{ $modalWorkspace === 'marketing'
                                    ? 'border-[#4E8EA2]/80 bg-[#BDD8E9]/90 shadow-[0_8px_22px_rgba(78,142,162,0.25)] dark:border-[#7BBDE8]/70 dark:bg-[#0A4174]/55'
                                    : 'border-slate-200/80 bg-white/80 hover:border-[#7BBDE8]/80 hover:bg-[#BDD8E9]/35 dark:border-white/10 dark:bg-slate-900/70 dark:hover:border-[#6EA2B3]/60 dark:hover:bg-[#0A4174]/25' }}"
                                role="tab"
                                aria-selected="{{ $modalWorkspace === 'marketing' ? 'true' : 'false' }}"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex size-8 items-center justify-center rounded-xl bg-[#7BBDE8]/40 text-[#0A4174] dark:bg-[#4E8EA2]/35 dark:text-[#BDD8E9]">
                                        <x-filament::icon icon="heroicon-o-megaphone" class="size-4" />
                                    </span>
                                    <span>
                                        <span class="block text-xs font-semibold text-slate-900 dark:text-slate-100">Calendario publicitario</span>
                                        <span class="mt-0.5 block text-[11px] text-slate-500 dark:text-slate-400">Instagram, YouTube, X y Facebook</span>
                                    </span>
                                </div>
                            </button>
                        @endif
                    </div>
                </header>

                <div class="grid min-h-0 flex-1 grid-cols-1 overflow-hidden lg:grid-cols-12">
                    <aside class="flex h-full min-h-0 flex-col border-b border-slate-200/80 bg-slate-50/70 p-4 dark:border-white/10 dark:bg-slate-950/50 lg:col-span-4 lg:border-b-0 lg:border-r">
                        @if ($modalWorkspace === 'marketing' && $this->canManageSocialPublications())
                            @include('filament.business.pages.partials.agenda-corporativa-marketing-sidebar')
                        @else
                        <div class="mb-3 flex items-center justify-between">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Agenda del día</p>
                            <button
                                type="button"
                                wire:click="startCreateActivity"
                                wire:loading.attr="disabled"
                                wire:target="startCreateActivity"
                                class="rounded-xl border border-[#4E8EA2] bg-[#0A4174] px-3 py-1.5 text-[11px] font-semibold text-[#BDD8E9] transition hover:bg-[#49769F]"
                            >
                                <span class="inline-flex items-center gap-1">
                                    <x-filament::icon icon="heroicon-o-arrow-path" wire:loading wire:target="startCreateActivity" class="size-3.5 animate-spin" />
                                    <span>Nueva actividad</span>
                                </span>
                            </button>
                        </div>

                        <div class="min-h-0 flex-1 space-y-2 overflow-y-auto pr-1">
                            @forelse ($this->selectedDateActivities as $activity)
                                @php
                                    $activityColorThemes = [
                                        [
                                            'idle' => 'border-cyan-200/80 bg-cyan-50/45 hover:border-cyan-300/70 hover:bg-cyan-50/70 dark:border-cyan-300/25 dark:bg-cyan-500/8 dark:hover:border-cyan-300/45 dark:hover:bg-cyan-500/14',
                                            'selected' => 'border-cyan-400/70 bg-cyan-50/90 shadow-[0_8px_24px_rgba(6,182,212,0.14)] dark:border-cyan-300/70 dark:bg-cyan-500/14',
                                            'pill' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-500/25 dark:text-cyan-200',
                                            'number' => 'bg-cyan-100 text-cyan-700 dark:bg-cyan-500/25 dark:text-cyan-200',
                                        ],
                                        [
                                            'idle' => 'border-violet-200/80 bg-violet-50/45 hover:border-violet-300/70 hover:bg-violet-50/70 dark:border-violet-300/25 dark:bg-violet-500/8 dark:hover:border-violet-300/45 dark:hover:bg-violet-500/14',
                                            'selected' => 'border-violet-400/70 bg-violet-50/90 shadow-[0_8px_24px_rgba(139,92,246,0.16)] dark:border-violet-300/70 dark:bg-violet-500/14',
                                            'pill' => 'bg-violet-100 text-violet-700 dark:bg-violet-500/25 dark:text-violet-200',
                                            'number' => 'bg-violet-100 text-violet-700 dark:bg-violet-500/25 dark:text-violet-200',
                                        ],
                                        [
                                            'idle' => 'border-emerald-200/80 bg-emerald-50/45 hover:border-emerald-300/70 hover:bg-emerald-50/70 dark:border-emerald-300/25 dark:bg-emerald-500/8 dark:hover:border-emerald-300/45 dark:hover:bg-emerald-500/14',
                                            'selected' => 'border-emerald-400/70 bg-emerald-50/90 shadow-[0_8px_24px_rgba(16,185,129,0.16)] dark:border-emerald-300/70 dark:bg-emerald-500/14',
                                            'pill' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/25 dark:text-emerald-200',
                                            'number' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/25 dark:text-emerald-200',
                                        ],
                                        [
                                            'idle' => 'border-amber-200/80 bg-amber-50/45 hover:border-amber-300/70 hover:bg-amber-50/70 dark:border-amber-300/25 dark:bg-amber-500/8 dark:hover:border-amber-300/45 dark:hover:bg-amber-500/14',
                                            'selected' => 'border-amber-400/70 bg-amber-50/90 shadow-[0_8px_24px_rgba(245,158,11,0.16)] dark:border-amber-300/70 dark:bg-amber-500/14',
                                            'pill' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/25 dark:text-amber-200',
                                            'number' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/25 dark:text-amber-200',
                                        ],
                                        [
                                            'idle' => 'border-rose-200/80 bg-rose-50/45 hover:border-rose-300/70 hover:bg-rose-50/70 dark:border-rose-300/25 dark:bg-rose-500/8 dark:hover:border-rose-300/45 dark:hover:bg-rose-500/14',
                                            'selected' => 'border-rose-400/70 bg-rose-50/90 shadow-[0_8px_24px_rgba(244,63,94,0.16)] dark:border-rose-300/70 dark:bg-rose-500/14',
                                            'pill' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/25 dark:text-rose-200',
                                            'number' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/25 dark:text-rose-200',
                                        ],
                                    ];
                                    $activityTheme = $activityColorThemes[abs(crc32((string) $activity->id)) % count($activityColorThemes)];
                                @endphp
                                <article
                                    class="w-full rounded-2xl border p-3 text-left transition
                                    {{ $selectedActivityId === $activity->id
                                        ? $activityTheme['selected']
                                        : $activityTheme['idle'] }}"
                                >
                                    <button
                                        type="button"
                                        wire:click="selectActivity({{ $activity->id }})"
                                        class="w-full"
                                    >
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <div class="flex min-w-0 flex-wrap items-center gap-2">
                                                <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold {{ $activityTheme['pill'] }}">
                                                    {{ $activity->activity_type?->value ?? 'Actividad' }}
                                                </span>
                                                @if ($activity->has_google_meet)
                                                    <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-semibold text-amber-700 dark:bg-amber-500/20 dark:text-amber-200">
                                                        Google Meet
                                                    </span>
                                                @endif
                                            </div>
                                            <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold {{ $activityTheme['number'] }}">
                                                #{{ $activity->id }}
                                            </span>
                                        </div>
                                        <div class="mt-2 flex items-center justify-between gap-2">
                                            <p class="truncate text-[11px] font-medium text-slate-600 dark:text-slate-300">
                                                {{ $activity->short_description ?: 'Actividad sin descripción' }}
                                            </p>
                                            <x-filament::icon
                                                icon="heroicon-o-chevron-down"
                                                class="{{ $selectedActivityId === $activity->id ? 'rotate-180' : '' }} size-4 shrink-0 text-slate-500 transition-transform duration-200"
                                            />
                                        </div>
                                    </button>

                                    @if ($selectedActivityId === $activity->id)
                                        @php
                                            $selectedActivityDetails = $this->selectedActivity;
                                            $participants = $selectedActivityDetails?->participants ?? collect();
                                            $pendingCount = $participants->filter(fn ($participant) => $participant->invitation_status?->value === \App\Enums\CorporateAgendaInvitationStatus::Pending->value)->count();
                                            $acceptedCount = $participants->filter(fn ($participant) => $participant->invitation_status?->value === \App\Enums\CorporateAgendaInvitationStatus::Accepted->value)->count();
                                            $rejectedCount = $participants->filter(fn ($participant) => $participant->invitation_status?->value === \App\Enums\CorporateAgendaInvitationStatus::Rejected->value)->count();
                                        @endphp
                                        <div class="mt-3 space-y-3">
                                        <div class="grid gap-2 text-[11px] text-slate-600 dark:text-slate-300 sm:grid-cols-2">
                                            <div class="rounded-xl border border-slate-200/80 bg-slate-50/70 px-2.5 py-2 dark:border-white/10 dark:bg-slate-800/60">
                                                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Creador</p>
                                                <p class="mt-1 font-medium text-slate-800 dark:text-slate-100">{{ $selectedActivityDetails?->creator?->name ?: 'Sin dato' }}</p>
                                            </div>
                                            <div class="rounded-xl border border-slate-200/80 bg-slate-50/70 px-2.5 py-2 dark:border-white/10 dark:bg-slate-800/60">
                                                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Fecha</p>
                                                <p class="mt-1 font-medium text-slate-800 dark:text-slate-100">{{ $activity->activity_date?->format('d/m/Y') }}</p>
                                            </div>
                                            <div class="rounded-xl border border-slate-200/80 bg-slate-50/70 px-2.5 py-2 dark:border-white/10 dark:bg-slate-800/60">
                                                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Horario</p>
                                                <p class="mt-1 font-medium text-slate-800 dark:text-slate-100">
                                                    {{ \Illuminate\Support\Str::of((string) $activity->start_time)->substr(0, 5) }} - {{ \Illuminate\Support\Str::of((string) $activity->end_time)->substr(0, 5) }}
                                                </p>
                                            </div>
                                            <div class="rounded-xl border border-slate-200/80 bg-slate-50/70 px-2.5 py-2 dark:border-white/10 dark:bg-slate-800/60">
                                                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Participantes</p>
                                                <p class="mt-1 font-medium text-slate-800 dark:text-slate-100">{{ $participants->count() }}</p>
                                            </div>
                                            <div class="rounded-xl border border-slate-200/80 bg-slate-50/70 px-2.5 py-2 dark:border-white/10 dark:bg-slate-800/60">
                                                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Notas</p>
                                                <p class="mt-1 font-medium text-slate-800 dark:text-slate-100">{{ $selectedActivityDetails?->notes?->count() ?? 0 }}</p>
                                            </div>
                                        </div>

                                        @if ($activity->has_google_meet)
                                            <div class="rounded-xl border border-amber-200/70 bg-amber-50/80 px-2.5 py-2 dark:border-amber-400/30 dark:bg-amber-500/10">
                                                <p class="text-[10px] font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-200">URL Meet</p>
                                                <p class="mt-1 break-all text-[11px] font-medium text-amber-800 dark:text-amber-100">
                                                    {{ $activity->google_meet_url ?: 'Sin URL registrada' }}
                                                </p>
                                            </div>
                                        @endif

                                        @if ($participants->isNotEmpty())
                                            <div class="space-y-2 rounded-xl border border-slate-200/80 bg-white/80 px-2.5 py-2 dark:border-white/10 dark:bg-slate-900/70">
                                                <div class="flex flex-wrap items-center gap-1.5 text-[10px]">
                                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 font-semibold text-slate-600 dark:bg-slate-700/80 dark:text-slate-200">Pendiente: {{ $pendingCount }}</span>
                                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 font-semibold text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200">Aceptada: {{ $acceptedCount }}</span>
                                                    <span class="rounded-full bg-rose-100 px-2 py-0.5 font-semibold text-rose-700 dark:bg-rose-500/20 dark:text-rose-200">Rechazada: {{ $rejectedCount }}</span>
                                                </div>

                                                <div class="space-y-1.5">
                                                    @foreach ($participants as $participant)
                                                        @php
                                                            $statusValue = $participant->invitation_status?->value ?? \App\Enums\CorporateAgendaInvitationStatus::Pending->value;
                                                            $statusLabel = match ($statusValue) {
                                                                \App\Enums\CorporateAgendaInvitationStatus::Accepted->value => 'Aceptada',
                                                                \App\Enums\CorporateAgendaInvitationStatus::Rejected->value => 'Rechazada',
                                                                default => 'Pendiente',
                                                            };
                                                            $statusClass = match ($statusValue) {
                                                                \App\Enums\CorporateAgendaInvitationStatus::Accepted->value => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200',
                                                                \App\Enums\CorporateAgendaInvitationStatus::Rejected->value => 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-200',
                                                                default => 'bg-slate-100 text-slate-700 dark:bg-slate-700/80 dark:text-slate-200',
                                                            };
                                                            $avatarStatusClass = match ($statusValue) {
                                                                \App\Enums\CorporateAgendaInvitationStatus::Accepted->value => 'border-emerald-600 dark:border-emerald-400 ring-1 ring-emerald-500/65 dark:ring-emerald-300/70 shadow-[0_8px_16px_rgba(5,150,105,0.32)]',
                                                                \App\Enums\CorporateAgendaInvitationStatus::Rejected->value => 'border-rose-600 dark:border-rose-400 ring-1 ring-rose-500/65 dark:ring-rose-300/70 shadow-[0_8px_16px_rgba(225,29,72,0.32)]',
                                                                default => 'border-slate-300 dark:border-slate-600 ring-1 ring-slate-300/70 dark:ring-slate-600/70 shadow-sm',
                                                            };
                                                            $participantColaborador = $participant->colaborador;
                                                            $avatarPath = is_string($participantColaborador?->avatar) ? ltrim((string) $participantColaborador->avatar, '/') : '';
                                                            $avatarUrl = $avatarPath !== '' ? url('storage/'.$avatarPath) : null;
                                                            $fullName = (string) ($participantColaborador?->fullName ?? '');
                                                            $nameParts = collect(preg_split('/\s+/', trim($fullName)) ?: [])->filter();
                                                            $initials = $nameParts->count() > 1
                                                                ? \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr((string) $nameParts->first(), 0, 1).\Illuminate\Support\Str::substr((string) $nameParts->last(), 0, 1))
                                                                : \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr((string) $nameParts->first(), 0, 2));
                                                            $initials = $initials !== '' ? $initials : '--';
                                                        @endphp
                                                        <div class="flex items-start justify-between gap-2 rounded-lg border border-slate-200/80 bg-slate-50/80 px-2 py-1.5 dark:border-white/10 dark:bg-slate-800/70">
                                                            <div class="flex min-w-0 items-start gap-2">
                                                                @if ($avatarUrl)
                                                                    <img
                                                                        src="{{ $avatarUrl }}"
                                                                        alt="{{ $fullName !== '' ? $fullName : 'Colaborador' }}"
                                                                    class="size-8 shrink-0 rounded-full border object-cover {{ $avatarStatusClass }}"
                                                                    >
                                                                @else
                                                                <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-full border bg-slate-200 text-[10px] font-semibold text-slate-700 dark:bg-slate-700 dark:text-slate-100 {{ $avatarStatusClass }}">
                                                                        {{ $initials }}
                                                                    </span>
                                                                @endif

                                                                <div class="min-w-0">
                                                                    <p class="break-words text-[11px] font-semibold text-slate-800 dark:text-slate-100">
                                                                        {{ $participant->colaborador?->fullName ?: 'Sin nombre de colaborador' }}
                                                                    </p>
                                                                    <p class="break-all text-[10px] text-slate-500 dark:text-slate-400">
                                                                        {{ $participant->colaborador?->emailCorporativo ?: ($participant->colaborador?->emailPersonal ?: 'Sin correo') }}
                                                                    </p>
                                                                    @if ($statusValue === \App\Enums\CorporateAgendaInvitationStatus::Rejected->value && filled($participant->response_note))
                                                                        <p class="mt-1 whitespace-pre-line break-words text-[10px] text-rose-700 dark:text-rose-200">
                                                                            Motivo: {{ $participant->response_note }}
                                                                        </p>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        <div class="rounded-xl border border-slate-200/80 bg-slate-50/70 px-2.5 py-2 dark:border-white/10 dark:bg-slate-800/60">
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Descripción</p>
                                            <p class="mt-1 whitespace-pre-line break-words text-[11px] leading-relaxed text-slate-700 dark:text-slate-200">
                                                {{ $activity->description ?: 'Sin descripción registrada.' }}
                                            </p>
                                        </div>
                                    </div>
                                    @endif
                                </article>
                            @empty
                                <div class="rounded-2xl border border-dashed border-slate-300/80 bg-white/70 px-4 py-8 text-center dark:border-white/10 dark:bg-slate-900/50">
                                    <p class="text-xs text-slate-500 dark:text-slate-400">No hay actividades registradas en este día.</p>
                                </div>
                            @endforelse
                        </div>
                        @endif
                    </aside>

                    <main class="h-full overflow-y-auto p-4 pb-8 lg:col-span-8 lg:p-5 lg:pb-10">
                        @if ($modalWorkspace === 'marketing' && $this->canManageSocialPublications())
                            @include('filament.business.pages.partials.agenda-corporativa-marketing-form')
                        @else
                        @php
                            $selectedActivity = $this->selectedActivity;
                        @endphp

                        @if ($isCreatingActivity || $this->canCurrentUserEdit($selectedActivity))
                            <form wire:submit.prevent="saveActivity" class="space-y-4">
                                <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-900/80">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                                        {{ $selectedActivityId ? 'Editar actividad' : 'Registrar actividad' }}
                                    </p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        Solo el creador o un usuario SUPERADMIN puede editarla, moverla de fecha o eliminarla.
                                    </p>
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Fecha de actividad</label>
                                        <input type="date" wire:model="activityForm.activity_date" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs dark:border-white/10 dark:bg-slate-800 dark:text-slate-100">
                                        @error('activityForm.activity_date') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Hora de inicio</label>
                                        <select wire:model="activityForm.start_time" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs dark:border-white/10 dark:bg-slate-800 dark:text-slate-100">
                                            <option value="">Seleccione...</option>
                                            @foreach ($this->timeOptions as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error('activityForm.start_time') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Hora de culminación</label>
                                        <select wire:model="activityForm.end_time" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs dark:border-white/10 dark:bg-slate-800 dark:text-slate-100">
                                            <option value="">Seleccione...</option>
                                            @foreach ($this->timeOptions as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error('activityForm.end_time') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Tipo de actividad</label>
                                        <select wire:model="activityForm.activity_type" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs dark:border-white/10 dark:bg-slate-800 dark:text-slate-100">
                                            <option value="">Seleccione...</option>
                                            @foreach ($this->activityTypeOptions as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error('activityForm.activity_type') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                    </div>

                                    <div class="sm:col-span-2">
                                        <label class="mb-2 inline-flex items-center gap-2 text-xs font-medium text-slate-700 dark:text-slate-300">
                                            <input type="checkbox" wire:model.live="activityForm.has_google_meet" class="rounded border-slate-300 text-cyan-600 focus:ring-cyan-500 dark:border-white/20 dark:bg-slate-800">
                                            Esta actividad implica Google Meet
                                        </label>
                                    </div>

                                    @if ($activityForm['has_google_meet'])
                                        <div class="sm:col-span-2">
                                            <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">URL Google Meet</label>
                                            <input type="url" wire:model.defer="activityForm.google_meet_url" placeholder="https://meet.google.com/..." class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs dark:border-white/10 dark:bg-slate-800 dark:text-slate-100">
                                            @error('activityForm.google_meet_url') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                        </div>
                                    @endif

                                    <div class="sm:col-span-2">
                                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Colaboradores participantes</label>
                                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/70 p-3 dark:border-white/10 dark:bg-slate-900/60">
                                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                                <div class="relative min-w-56 flex-1">
                                                    <x-filament::icon icon="heroicon-o-magnifying-glass" class="pointer-events-none absolute left-2 top-2.5 size-4 text-slate-400" />
                                                    <input
                                                        type="text"
                                                        wire:model.live.debounce.250ms="collaboratorSearch"
                                                        placeholder="Buscar colaborador por nombre o correo..."
                                                        class="w-full rounded-xl border border-slate-300 bg-white py-2 pl-8 pr-3 text-xs dark:border-white/10 dark:bg-slate-800 dark:text-slate-100"
                                                    >
                                                </div>
                                                <button
                                                    type="button"
                                                    wire:click="selectAllFilteredCollaborators"
                                                    wire:loading.attr="disabled"
                                                    wire:target="selectAllFilteredCollaborators"
                                                    class="rounded-xl bg-cyan-600 px-3 py-2 text-[11px] font-semibold text-white transition hover:bg-cyan-500"
                                                >
                                                    <span class="inline-flex items-center gap-1">
                                                        <x-filament::icon icon="heroicon-o-arrow-path" wire:loading wire:target="selectAllFilteredCollaborators" class="size-3 animate-spin" />
                                                        <span>Seleccionar visibles</span>
                                                    </span>
                                                </button>
                                                <button
                                                    type="button"
                                                    wire:click="clearCollaboratorsSelection"
                                                    wire:loading.attr="disabled"
                                                    wire:target="clearCollaboratorsSelection"
                                                    class="rounded-xl bg-slate-200 px-3 py-2 text-[11px] font-semibold text-slate-700 transition hover:bg-slate-300 dark:bg-slate-700 dark:text-slate-100 dark:hover:bg-slate-600"
                                                >
                                                    <span class="inline-flex items-center gap-1">
                                                        <x-filament::icon icon="heroicon-o-arrow-path" wire:loading wire:target="clearCollaboratorsSelection" class="size-3 animate-spin" />
                                                        <span>Limpiar</span>
                                                    </span>
                                                </button>
                                            </div>

                                            <div class="mb-2 text-[11px] text-slate-500 dark:text-slate-400">
                                                Seleccionados: <span class="font-semibold">{{ count($activityForm['participant_ids'] ?? []) }}</span>
                                            </div>

                                            <div class="max-h-48 space-y-2 overflow-y-auto pr-1">
                                                @php
                                                    $selectedCollaboratorIds = collect($activityForm['participant_ids'] ?? [])
                                                        ->map(fn (mixed $id): string => (string) $id)
                                                        ->values()
                                                        ->all();
                                                @endphp
                                                @forelse ($this->filteredCollaboratorOptions as $collaborator)
                                                    @php
                                                        $isCollaboratorSelected = in_array((string) $collaborator['id'], $selectedCollaboratorIds, true);
                                                    @endphp
                                                    <label class="flex cursor-pointer items-start gap-3">
                                                        <input
                                                            type="checkbox"
                                                            value="{{ $collaborator['id'] }}"
                                                            wire:model.live="activityForm.participant_ids"
                                                            @checked($isCollaboratorSelected)
                                                            class="mt-3 size-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 dark:border-white/20 dark:bg-slate-700"
                                                        >
                                                        <div class="flex-1 rounded-xl border px-3 py-2 transition
                                                            {{ $isCollaboratorSelected
                                                                ? 'border-emerald-300 bg-emerald-50/90 shadow-[0_8px_18px_rgba(16,185,129,0.18)] dark:border-emerald-300/50 dark:bg-emerald-500/15'
                                                                : 'border-slate-200 bg-white hover:border-cyan-300 hover:bg-cyan-50/70 dark:border-white/10 dark:bg-slate-800/80 dark:hover:border-cyan-300/40 dark:hover:bg-cyan-500/10' }}">
                                                            <span class="min-w-0">
                                                                <span class="block truncate text-xs font-semibold {{ $isCollaboratorSelected ? 'text-emerald-800 dark:text-emerald-200' : 'text-slate-800 dark:text-slate-100' }}">{{ $collaborator['name'] }}</span>
                                                                <span class="block truncate text-[11px] {{ $isCollaboratorSelected ? 'text-emerald-700/90 dark:text-emerald-200/80' : 'text-slate-500 dark:text-slate-400' }}">{{ $collaborator['email'] ?: 'Sin correo' }}</span>
                                                            </span>
                                                        </div>
                                                    </label>
                                                @empty
                                                    <p class="rounded-xl border border-dashed border-slate-300/80 bg-white/80 px-3 py-4 text-center text-xs text-slate-500 dark:border-white/15 dark:bg-slate-800/80 dark:text-slate-400">
                                                        No hay colaboradores para la búsqueda actual.
                                                    </p>
                                                @endforelse
                                            </div>
                                        </div>
                                        <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Opcional. Puedes dejar esta sección vacía si la actividad no requiere participantes.</p>
                                        @error('activityForm.participant_ids') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                    </div>

                                    <div class="sm:col-span-2">
                                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-300">Descripción detallada</label>
                                        <textarea wire:model.defer="activityForm.description" rows="6" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs dark:border-white/10 dark:bg-slate-800 dark:text-slate-100"></textarea>
                                        @error('activityForm.description') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-slate-200/80 bg-white/90 px-4 py-3 dark:border-white/10 dark:bg-slate-900/90">
                                    @if ($selectedActivityId)
                                        <button type="button" wire:click="deleteSelectedActivity" class="rounded-xl bg-rose-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-rose-500">
                                            <span class="inline-flex items-center gap-1">
                                                <x-filament::icon icon="heroicon-o-arrow-path" wire:loading wire:target="deleteSelectedActivity" class="size-3.5 animate-spin" />
                                                <span wire:loading.remove wire:target="deleteSelectedActivity">Eliminar actividad</span>
                                                <span wire:loading wire:target="deleteSelectedActivity">Eliminando...</span>
                                            </span>
                                        </button>
                                    @else
                                        <span class="text-xs text-slate-500 dark:text-slate-400">Registra primero la actividad para habilitar notas y estado de invitaciones.</span>
                                    @endif

                                    <button type="submit" wire:loading.attr="disabled" wire:target="saveActivity" class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-emerald-500">
                                        <span class="inline-flex items-center gap-1">
                                            <x-filament::icon icon="heroicon-o-arrow-path" wire:loading wire:target="saveActivity" class="size-3.5 animate-spin" />
                                            <span wire:loading.remove wire:target="saveActivity">{{ $selectedActivityId ? 'Guardar cambios' : 'Crear actividad' }}</span>
                                            <span wire:loading wire:target="saveActivity">{{ $selectedActivityId ? 'Guardando...' : 'Creando...' }}</span>
                                        </span>
                                    </button>
                                </div>
                            </form>

                            @php
                                $currentParticipant = $this->currentParticipantForSelectedActivity;
                                $currentParticipantStatus = $currentParticipant?->invitation_status?->value ?? \App\Enums\CorporateAgendaInvitationStatus::Pending->value;
                                $currentParticipantStatusLabel = match ($currentParticipantStatus) {
                                    \App\Enums\CorporateAgendaInvitationStatus::Accepted->value => 'Aceptada',
                                    \App\Enums\CorporateAgendaInvitationStatus::Rejected->value => 'Rechazada',
                                    default => 'Pendiente',
                                };
                                $currentParticipantStatusClass = match ($currentParticipantStatus) {
                                    \App\Enums\CorporateAgendaInvitationStatus::Accepted->value => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200',
                                    \App\Enums\CorporateAgendaInvitationStatus::Rejected->value => 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-200',
                                    default => 'bg-slate-100 text-slate-700 dark:bg-slate-700/80 dark:text-slate-200',
                                };
                            @endphp

                            @if ($selectedActivity && $this->canCurrentUserRespondToMeet($selectedActivity) && $currentParticipant)
                                <div class="mt-4 rounded-2xl border border-amber-200/70 bg-amber-50/70 p-4 dark:border-amber-400/30 dark:bg-amber-500/10">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <p class="text-xs font-semibold text-amber-800 dark:text-amber-200">Tu confirmación de participación</p>
                                            <span class="rounded-full border border-amber-300/70 bg-amber-100/70 px-2 py-0.5 text-[10px] font-semibold text-amber-800 dark:border-amber-300/30 dark:bg-amber-500/15 dark:text-amber-200">Respuesta personal</span>
                                        </div>
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $currentParticipantStatusClass }}">{{ $currentParticipantStatusLabel }}</span>
                                    </div>
                                    <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">Debes aceptar o rechazar tu participación. Si rechazas, es obligatorio indicar el motivo; el creador lo verá en el detalle.</p>
                                    @if ($currentParticipantStatus === \App\Enums\CorporateAgendaInvitationStatus::Rejected->value && filled($currentParticipant->response_note))
                                        <p class="mt-2 whitespace-pre-line rounded-xl border border-rose-200/80 bg-rose-50/90 px-3 py-2 text-[11px] text-rose-700 dark:border-rose-400/30 dark:bg-rose-500/10 dark:text-rose-200">
                                            Último motivo registrado: {{ $currentParticipant->response_note }}
                                        </p>
                                    @endif
                                    @if ($currentParticipantStatus !== \App\Enums\CorporateAgendaInvitationStatus::Accepted->value)
                                        <div class="mt-3">
                                            <label class="mb-1 block text-[11px] font-medium text-amber-800 dark:text-amber-200">Motivo de rechazo (obligatorio si rechazas)</label>
                                            <textarea wire:model.defer="invitationRejectionNote" rows="3" class="w-full rounded-xl border border-amber-300/70 bg-white/90 px-3 py-2 text-xs text-slate-700 dark:border-amber-300/40 dark:bg-slate-900/80 dark:text-slate-100"></textarea>
                                            @error('invitationRejectionNote') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                        </div>
                                    @else
                                        <p class="mt-3 rounded-xl border border-emerald-200/80 bg-emerald-50 px-3 py-2 text-[11px] text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-200">
                                            Ya confirmaste esta actividad. El campo de motivo de rechazo se oculta automáticamente.
                                        </p>
                                    @endif
                                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                        <button type="button" wire:click="acceptMeet({{ $selectedActivity->id }})" wire:loading.attr="disabled" wire:target="acceptMeet" class="rounded-xl bg-emerald-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-500">
                                            <span class="inline-flex items-center gap-1">
                                                <x-filament::icon icon="heroicon-o-arrow-path" wire:loading wire:target="acceptMeet" class="size-3.5 animate-spin" />
                                                <span wire:loading.remove wire:target="acceptMeet">Aceptar actividad</span>
                                                <span wire:loading wire:target="acceptMeet">Aceptando...</span>
                                            </span>
                                        </button>
                                        <button type="button" wire:click="rejectMeet({{ $selectedActivity->id }})" wire:loading.attr="disabled" wire:target="rejectMeet" class="rounded-xl bg-rose-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-rose-500">
                                            <span class="inline-flex items-center gap-1">
                                                <x-filament::icon icon="heroicon-o-arrow-path" wire:loading wire:target="rejectMeet" class="size-3.5 animate-spin" />
                                                <span wire:loading.remove wire:target="rejectMeet">Rechazar actividad</span>
                                                <span wire:loading wire:target="rejectMeet">Rechazando...</span>
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            @endif
                        @elseif ($selectedActivity)
                            <div class="space-y-4">
                                @php
                                    $currentParticipant = $this->currentParticipantForSelectedActivity;
                                    $currentParticipantStatus = $currentParticipant?->invitation_status?->value ?? \App\Enums\CorporateAgendaInvitationStatus::Pending->value;
                                    $currentParticipantStatusLabel = match ($currentParticipantStatus) {
                                        \App\Enums\CorporateAgendaInvitationStatus::Accepted->value => 'Aceptada',
                                        \App\Enums\CorporateAgendaInvitationStatus::Rejected->value => 'Rechazada',
                                        default => 'Pendiente',
                                    };
                                    $currentParticipantStatusClass = match ($currentParticipantStatus) {
                                        \App\Enums\CorporateAgendaInvitationStatus::Accepted->value => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-200',
                                        \App\Enums\CorporateAgendaInvitationStatus::Rejected->value => 'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-200',
                                        default => 'bg-slate-100 text-slate-700 dark:bg-slate-700/80 dark:text-slate-200',
                                    };
                                @endphp

                                <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-slate-900/80">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Detalle de actividad</p>
                                    <dl class="mt-2 grid gap-2 text-xs text-slate-600 dark:text-slate-300">
                                        <div><span class="font-semibold">Tipo:</span> {{ $selectedActivity->activity_type?->value }}</div>
                                        <div><span class="font-semibold">Fecha:</span> {{ $selectedActivity->activity_date?->format('d/m/Y') }}</div>
                                        <div><span class="font-semibold">Hora de inicio:</span> {{ \Illuminate\Support\Str::of((string) $selectedActivity->start_time)->substr(0, 5) }}</div>
                                        <div><span class="font-semibold">Hora de culminación:</span> {{ \Illuminate\Support\Str::of((string) $selectedActivity->end_time)->substr(0, 5) }}</div>
                                        <div><span class="font-semibold">Creador:</span> {{ $selectedActivity->creator?->name }}</div>
                                        <div><span class="font-semibold">Descripción:</span> {{ $selectedActivity->description }}</div>
                                        @if ($selectedActivity->has_google_meet)
                                            <div>
                                                <span class="font-semibold">Google Meet:</span>
                                                <a href="{{ $selectedActivity->google_meet_url }}" target="_blank" class="text-cyan-600 underline dark:text-cyan-300">{{ $selectedActivity->google_meet_url }}</a>
                                            </div>
                                        @endif
                                    </dl>

                                    @if ($selectedActivity->participants->isNotEmpty())
                                        <div class="mt-3 space-y-2 rounded-xl border border-slate-200/80 bg-slate-50/70 p-3 dark:border-white/10 dark:bg-slate-800/70">
                                            <p class="text-xs font-semibold text-slate-800 dark:text-slate-100">Estado de confirmación de colaboradores</p>
                                            @foreach ($selectedActivity->participants as $participant)
                                                @php
                                                    $participantStatusValue = $participant->invitation_status?->value ?? \App\Enums\CorporateAgendaInvitationStatus::Pending->value;
                                                    $participantStatusLabel = match ($participantStatusValue) {
                                                        \App\Enums\CorporateAgendaInvitationStatus::Accepted->value => 'Aceptada',
                                                        \App\Enums\CorporateAgendaInvitationStatus::Rejected->value => 'Rechazada',
                                                        default => 'Pendiente',
                                                    };
                                                    $avatarStatusClass = match ($participantStatusValue) {
                                                        \App\Enums\CorporateAgendaInvitationStatus::Accepted->value => 'border-emerald-600 dark:border-emerald-400 ring-1 ring-emerald-500/65 dark:ring-emerald-300/70 shadow-[0_8px_16px_rgba(5,150,105,0.32)]',
                                                        \App\Enums\CorporateAgendaInvitationStatus::Rejected->value => 'border-rose-600 dark:border-rose-400 ring-1 ring-rose-500/65 dark:ring-rose-300/70 shadow-[0_8px_16px_rgba(225,29,72,0.32)]',
                                                        default => 'border-slate-300 dark:border-slate-600 ring-1 ring-slate-300/70 dark:ring-slate-600/70 shadow-sm',
                                                    };
                                                    $participantColaborador = $participant->colaborador;
                                                    $avatarPath = is_string($participantColaborador?->avatar) ? ltrim((string) $participantColaborador->avatar, '/') : '';
                                                    $avatarUrl = ($avatarPath !== '' && \Illuminate\Support\Facades\Storage::disk('public')->exists($avatarPath))
                                                        ? url('storage/'.$avatarPath)
                                                        : null;
                                                    $fullName = (string) ($participantColaborador?->fullName ?? '');
                                                    $nameParts = collect(preg_split('/\s+/', trim($fullName)) ?: [])->filter();
                                                    $initials = $nameParts->count() > 1
                                                        ? \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr((string) $nameParts->first(), 0, 1).\Illuminate\Support\Str::substr((string) $nameParts->last(), 0, 1))
                                                        : \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr((string) $nameParts->first(), 0, 2));
                                                    $initials = $initials !== '' ? $initials : '--';
                                                @endphp
                                                <div class="rounded-lg border border-slate-200/70 bg-white px-3 py-2 text-[11px] dark:border-white/10 dark:bg-slate-900/80">
                                                    <div class="flex items-center justify-between gap-2">
                                                        <div class="flex min-w-0 items-center gap-2">
                                                            @if ($avatarUrl)
                                                                <img
                                                                    src="{{ $avatarUrl }}"
                                                                    alt="{{ $fullName !== '' ? $fullName : 'Colaborador' }}"
                                                                    class="size-8 shrink-0 rounded-full border object-cover {{ $avatarStatusClass }}"
                                                                >
                                                            @else
                                                                <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-full border bg-slate-100 text-[10px] font-semibold text-slate-700 dark:bg-slate-700 dark:text-slate-100 {{ $avatarStatusClass }}">
                                                                    {{ $initials }}
                                                                </span>
                                                            @endif
                                                            <span class="truncate font-semibold text-slate-800 dark:text-slate-100">{{ $participant->colaborador?->fullName ?: 'Colaborador' }}</span>
                                                        </div>
                                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-700 dark:bg-slate-700 dark:text-slate-200">{{ $participantStatusLabel }}</span>
                                                    </div>
                                                    @if ($participantStatusValue === \App\Enums\CorporateAgendaInvitationStatus::Rejected->value && filled($participant->response_note))
                                                        <p class="mt-1 whitespace-pre-line break-words text-[10px] text-rose-700 dark:text-rose-200">
                                                            Motivo de rechazo: {{ $participant->response_note }}
                                                        </p>
                                                    @endif

                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                @if ($this->canCurrentUserRespondToMeet($selectedActivity) && $currentParticipant)
                                    <div class="rounded-2xl border border-amber-200/70 bg-amber-50/70 p-4 dark:border-amber-400/30 dark:bg-amber-500/10">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <div class="flex items-center gap-2">
                                                <p class="text-xs font-semibold text-amber-800 dark:text-amber-200">Tu confirmación de participación</p>
                                                <span class="rounded-full border border-amber-300/70 bg-amber-100/70 px-2 py-0.5 text-[10px] font-semibold text-amber-800 dark:border-amber-300/30 dark:bg-amber-500/15 dark:text-amber-200">Respuesta personal</span>
                                            </div>
                                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $currentParticipantStatusClass }}">{{ $currentParticipantStatusLabel }}</span>
                                        </div>
                                        <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">Debes aceptar o rechazar tu participación. Si rechazas, es obligatorio indicar el motivo; el creador lo verá en el detalle.</p>
                                        @if ($currentParticipantStatus === \App\Enums\CorporateAgendaInvitationStatus::Rejected->value && filled($currentParticipant->response_note))
                                            <p class="mt-2 whitespace-pre-line rounded-xl border border-rose-200/80 bg-rose-50/90 px-3 py-2 text-[11px] text-rose-700 dark:border-rose-400/30 dark:bg-rose-500/10 dark:text-rose-200">
                                                Último motivo registrado: {{ $currentParticipant->response_note }}
                                            </p>
                                        @endif
                                        @if ($currentParticipantStatus !== \App\Enums\CorporateAgendaInvitationStatus::Accepted->value)
                                            <div class="mt-3">
                                                <label class="mb-1 block text-[11px] font-medium text-amber-800 dark:text-amber-200">Motivo de rechazo (obligatorio si rechazas)</label>
                                                <textarea wire:model.defer="invitationRejectionNote" rows="3" class="w-full rounded-xl border border-amber-300/70 bg-white/90 px-3 py-2 text-xs text-slate-700 dark:border-amber-300/40 dark:bg-slate-900/80 dark:text-slate-100"></textarea>
                                                @error('invitationRejectionNote') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                            </div>
                                        @else
                                            <p class="mt-3 rounded-xl border border-emerald-200/80 bg-emerald-50 px-3 py-2 text-[11px] text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-500/10 dark:text-emerald-200">
                                                Ya confirmaste esta actividad. El campo de motivo de rechazo se oculta automáticamente.
                                            </p>
                                        @endif
                                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                            <button type="button" wire:click="acceptMeet({{ $selectedActivity->id }})" wire:loading.attr="disabled" wire:target="acceptMeet" class="rounded-xl bg-emerald-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-500">
                                                <span class="inline-flex items-center gap-1">
                                                    <x-filament::icon icon="heroicon-o-arrow-path" wire:loading wire:target="acceptMeet" class="size-3.5 animate-spin" />
                                                    <span wire:loading.remove wire:target="acceptMeet">Aceptar actividad</span>
                                                    <span wire:loading wire:target="acceptMeet">Aceptando...</span>
                                                </span>
                                            </button>
                                            <button type="button" wire:click="rejectMeet({{ $selectedActivity->id }})" wire:loading.attr="disabled" wire:target="rejectMeet" class="rounded-xl bg-rose-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-rose-500">
                                                <span class="inline-flex items-center gap-1">
                                                    <x-filament::icon icon="heroicon-o-arrow-path" wire:loading wire:target="rejectMeet" class="size-3.5 animate-spin" />
                                                    <span wire:loading.remove wire:target="rejectMeet">Rechazar actividad</span>
                                                    <span wire:loading wire:target="rejectMeet">Rechazando...</span>
                                                </span>
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="rounded-2xl border border-dashed border-slate-300/80 bg-slate-50 px-4 py-8 text-center dark:border-white/10 dark:bg-slate-900/50">
                                <p class="text-xs text-slate-500 dark:text-slate-400">Selecciona una actividad de la lista o registra una nueva.</p>
                            </div>
                        @endif
                        @endif

                        @if ($modalWorkspace === 'activities' && $selectedActivity)
                            <div class="mt-4 rounded-2xl border border-slate-200/80 bg-white p-4 dark:border-white/10 dark:bg-slate-900/80">
                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Notas y comentarios</p>

                                <div class="mt-3 space-y-2">
                                    @forelse ($selectedActivity->notes as $note)
                                        <article class="rounded-xl border border-slate-200/80 bg-slate-50 px-3 py-2 text-xs dark:border-white/10 dark:bg-slate-800/70">
                                            <p class="text-slate-700 dark:text-slate-200">{{ $note->note }}</p>
                                            <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">{{ $note->user?->name }} · {{ $note->created_at?->format('d/m/Y H:i') }}</p>
                                        </article>
                                    @empty
                                        <p class="text-xs text-slate-500 dark:text-slate-400">Aún no hay notas para esta actividad.</p>
                                    @endforelse
                                </div>

                                @if ($this->canCurrentUserEdit($selectedActivity))
                                    <div class="mt-3 space-y-2">
                                        <textarea wire:model.defer="newNote" rows="3" placeholder="Escribe una nota sobre la actividad..." class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs dark:border-white/10 dark:bg-slate-800 dark:text-slate-100"></textarea>
                                        @error('newNote') <p class="text-xs text-rose-600">{{ $message }}</p> @enderror
                                        <button type="button" wire:click="addNote" wire:loading.attr="disabled" wire:target="addNote" class="rounded-xl bg-cyan-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-cyan-500">
                                            <span class="inline-flex items-center gap-1">
                                                <x-filament::icon icon="heroicon-o-arrow-path" wire:loading wire:target="addNote" class="size-3.5 animate-spin" />
                                                <span wire:loading.remove wire:target="addNote">Agregar nota</span>
                                                <span wire:loading wire:target="addNote">Agregando...</span>
                                            </span>
                                        </button>
                                    </div>
                                @else
                                    <p class="mt-3 text-[11px] text-slate-500 dark:text-slate-400">Modo solo lectura: solo el creador o un usuario SUPERADMIN puede agregar notas.</p>
                                @endif
                            </div>
                        @endif
                    </main>
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
