<?php

declare(strict_types=1);

namespace App\Filament\Business\Pages;

use App\Enums\CorporateAgendaActivityType;
use App\Enums\CorporateAgendaInvitationStatus;
use App\Enums\CorporateAgendaSocialPlatform;
use App\Models\CorporateAgendaActivity;
use App\Models\CorporateAgendaActivityParticipant;
use App\Models\CorporateAgendaSocialPublication;
use App\Models\RrhhColaborador;
use App\Services\CorporateAgendaInvitationWhatsAppService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\WithFileUploads;
use UnitEnum;

class AgendaCorporativa extends Page
{
    use WithFileUploads;

    // protected static string|UnitEnum|null $navigationGroup = 'SOLICITUDES';

    protected static ?string $navigationLabel = 'Agenda Corporativa';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.business.pages.agenda-corporativa';

    public string $cursorMonth = '';

    public string $viewMode = 'month';

    public string $selectedWeekDate = '';

    public bool $isActivityModalOpen = false;

    public string $selectedDate = '';

    public ?int $selectedActivityId = null;

    public bool $isCreatingActivity = false;

    public string $modalWorkspace = 'activities';

    public ?int $selectedSocialPublicationId = null;

    public bool $isCreatingSocialPublication = false;

    /** @var array<string, array<int, mixed>> */
    public array $socialPublicationUploadsByPlatform = [];

    /** @var array<string, array<int, string>> */
    public array $socialPublicationExistingAttachmentsByPlatform = [];

    /** @var array<string, string|null> */
    public array $socialPublicationBriefByPlatform = [];

    /** @var array{
     *     publication_date:string|null,
     *     platforms:array<int, string>,
     *     brief:string|null,
     *     attachments:array<int, string>
     * } */
    public array $socialPublicationForm = [
        'publication_date' => null,
        'platforms' => [],
        'brief' => null,
        'attachments' => [],
    ];

    /** @var array{
     *     activity_date:string|null,
     *     start_time:string|null,
     *     end_time:string|null,
     *     activity_type:string|null,
     *     has_google_meet:bool,
     *     google_meet_url:string|null,
     *     participant_ids:array<int>,
     *     description:string|null
     * } */
    public array $activityForm = [
        'activity_date' => null,
        'start_time' => '08:00',
        'end_time' => '09:00',
        'activity_type' => null,
        'has_google_meet' => false,
        'google_meet_url' => null,
        'participant_ids' => [],
        'description' => null,
    ];

    public string $newNote = '';

    public string $collaboratorSearch = '';

    public string $invitationRejectionNote = '';

    /** @var array<int>|null */
    protected ?array $currentCollaboratorIdsCache = null;

    /** @var array<int, array{id:int,name:string,email:string|null}>|null */
    protected ?array $collaboratorOptionsCache = null;

    protected ?string $socialPublicationFormHydratedForDate = null;

    public function mount(): void
    {
        $this->cursorMonth = now()->startOfMonth()->toDateString();
        $this->selectedDate = now()->toDateString();
        $this->selectedWeekDate = now()->toDateString();
    }

    public function previousMonth(): void
    {
        $this->cursorMonth = $this->resolveCursor()->subMonth()->toDateString();
    }

    public function nextMonth(): void
    {
        $this->cursorMonth = $this->resolveCursor()->addMonth()->toDateString();
    }

    public function goToday(): void
    {
        $this->cursorMonth = now()->startOfMonth()->toDateString();
        $this->selectedDate = now()->toDateString();
    }

    public function getMonthLabelProperty(): string
    {
        return (string) $this->resolveCursor()->translatedFormat('F Y');
    }

    public function setWeekView(): void
    {
        $this->viewMode = 'week';
        $this->selectedWeekDate = now()->toDateString();
    }

    public function setMonthView(): void
    {
        $this->viewMode = 'month';
    }

    public function selectWeekDate(string $date): void
    {
        $targetDate = Carbon::parse($date);
        $startOfWeek = now()->startOfWeek(Carbon::MONDAY);
        $endOfWeek = now()->endOfWeek(Carbon::SUNDAY);

        if ($targetDate->betweenIncluded($startOfWeek, $endOfWeek)) {
            $this->selectedWeekDate = $targetDate->toDateString();
        }
    }

    /**
     * @return array<int, string>
     */
    public function getWeekdaysProperty(): array
    {
        return ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCalendarDaysProperty(): array
    {
        $cursor = $this->resolveCursor();
        $start = $cursor->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $end = $cursor->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $monthCacheKey = sprintf(
            'agenda-corporativa:calendar-days:%s:%s:%s:%s',
            Auth::id() ?? 'guest',
            md5(json_encode($this->currentCollaboratorIds())),
            $start->toDateString(),
            $end->toDateString(),
        );

        $payload = Cache::remember($monthCacheKey, now()->addSeconds(45), function () use ($start, $end): array {
            $monthActivities = $this->visibleActivitiesBetween($start, $end)
                ->with([
                    'participants:id,activity_id,rrhh_colaborador_id,invitation_status',
                    'participants.colaborador:id,fullName,emailCorporativo,emailPersonal,avatar,user_id',
                ])
                ->orderBy('activity_date')
                ->get()
                ->groupBy(fn (CorporateAgendaActivity $activity): string => $activity->activity_date->toDateString());

            $monthPublications = $this->socialPublicationsBetween($start, $end)
                ->orderBy('platform')
                ->get()
                ->groupBy(fn (CorporateAgendaSocialPublication $publication): string => $publication->publication_date->toDateString());

            return [
                'activities' => $monthActivities,
                'publications' => $monthPublications,
            ];
        });

        /** @var Collection<string, Collection<int, CorporateAgendaActivity>> $monthActivities */
        $monthActivities = $payload['activities'];
        /** @var Collection<string, Collection<int, CorporateAgendaSocialPublication>> $monthPublications */
        $monthPublications = $payload['publications'];

        $days = [];
        $day = $start->copy();

        while ($day->lessThanOrEqualTo($end)) {
            $isCurrentMonth = $day->isSameMonth($cursor);
            $isToday = $day->isToday();
            $isPastDate = $day->lt(now()->startOfDay());
            $activities = $monthActivities->get($day->toDateString(), collect());
            $publications = $monthPublications->get($day->toDateString(), collect());

            $days[] = [
                'date' => $day->toDateString(),
                'day_number' => (int) $day->format('j'),
                'is_current_month' => $isCurrentMonth,
                'is_today' => $isToday,
                'is_past_date' => $isPastDate,
                ...$this->buildDayVisuals($activities, $publications, $isCurrentMonth),
            ];

            $day->addDay();
        }

        return $days;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCurrentWeekDaysProperty(): array
    {
        $baseDate = now();
        $startOfWeek = $baseDate->copy()->startOfWeek(Carbon::MONDAY);
        $endOfWeek = $baseDate->copy()->endOfWeek(Carbon::SUNDAY);

        $activitiesByDate = $this->visibleActivitiesBetween($startOfWeek, $endOfWeek)
            ->with(['participants:id,activity_id,rrhh_colaborador_id'])
            ->orderBy('activity_date')
            ->orderBy('start_time')
            ->get()
            ->groupBy(fn (CorporateAgendaActivity $activity): string => $activity->activity_date->toDateString());

        $publicationsByDate = $this->socialPublicationsBetween($startOfWeek, $endOfWeek)
            ->orderBy('platform')
            ->get()
            ->groupBy(fn (CorporateAgendaSocialPublication $publication): string => $publication->publication_date->toDateString());

        $days = [];
        $cursor = $startOfWeek->copy();
        while ($cursor->lessThanOrEqualTo($endOfWeek)) {
            $dateKey = $cursor->toDateString();
            $publications = $publicationsByDate->get($dateKey, collect());
            $days[] = [
                'date' => $dateKey,
                'day_label' => Str::upper($cursor->translatedFormat('D')),
                'day_number' => (int) $cursor->format('j'),
                'is_today' => $cursor->isToday(),
                'is_selected' => $dateKey === $this->selectedWeekDate,
                'activity_count' => $activitiesByDate->get($dateKey, collect())->count(),
                'social_platforms' => $this->resolveSocialPlatformsForPublications($publications),
                'social_badges' => $this->resolveSocialBadgesForPublications($publications, includeMedia: false),
            ];

            $cursor->addDay();
        }

        return $days;
    }

    /**
     * @return Collection<int, CorporateAgendaActivity>
     */
    public function getWeekSelectedDayActivitiesProperty(): Collection
    {
        return $this->visibleActivitiesBetween(
            Carbon::parse($this->selectedWeekDate)->startOfDay(),
            Carbon::parse($this->selectedWeekDate)->endOfDay(),
        )
            ->with([
                'creator:id,name,email,phone',
                'participants.colaborador:id,fullName,emailCorporativo,emailPersonal,avatar,user_id',
            ])
            ->orderBy('start_time')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  Collection<int, CorporateAgendaSocialPublication>  $publications
     * @return array<int, string>
     */
    private function resolveSocialPlatformsForPublications(Collection $publications): array
    {
        return $publications
            ->map(fn (CorporateAgendaSocialPublication $publication): string => $publication->platform?->value ?? (string) $publication->getRawOriginal('platform'))
            ->filter(fn (string $platform): bool => $platform !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, CorporateAgendaSocialPublication>  $publications
     * @return array<int, array{
     *   platform:string,
     *   media:array<int, array{url:string,type:string,name:string}>,
     *   media_count:int
     * }>
     */
    private function resolveSocialBadgesForPublications(Collection $publications, bool $includeMedia = true): array
    {
        return $publications
            ->map(function (CorporateAgendaSocialPublication $publication) use ($includeMedia): array {
                $platform = $publication->platform?->value ?? (string) $publication->getRawOriginal('platform');
                $media = $includeMedia ? $this->resolveSocialPublicationMediaGallery($publication) : [];
                $attachments = is_array($publication->attachments) ? $publication->attachments : [];

                return [
                    'platform' => $platform,
                    'media' => $media,
                    'media_count' => count($attachments),
                ];
            })
            ->filter(fn (array $badge): bool => ($badge['platform'] ?? '') !== '')
            ->unique('platform')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{url:string,type:string,name:string}>
     */
    private function resolveSocialPublicationMediaGallery(CorporateAgendaSocialPublication $publication): array
    {
        $attachments = is_array($publication->attachments) ? $publication->attachments : [];
        if ($attachments === []) {
            return [];
        }

        return collect($attachments)
            ->map(function (mixed $attachmentPath): ?array {
                $path = ltrim(trim((string) $attachmentPath), '/');
                if ($path === '') {
                    return null;
                }

                $ext = Str::lower(pathinfo($path, PATHINFO_EXTENSION));
                $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
                $isVideo = in_array($ext, ['mp4', 'webm', 'mov'], true);

                if (! $isImage && ! $isVideo) {
                    return null;
                }

                return [
                    'url' => $this->publicStorageUrl($path) ?? '',
                    'type' => $isVideo ? 'video' : 'image',
                    'name' => basename($path),
                ];
            })
            ->filter(fn (?array $item): bool => $item !== null && ($item['url'] ?? '') !== '')
            ->take(8)
            ->values()
            ->all();
    }

    private function buildDayVisuals(Collection $activities, Collection $publications, bool $isCurrentMonth): array
    {
        $includeHeavyCalendarPayload = ! $this->isActivityModalOpen;
        $socialPlatforms = $this->resolveSocialPlatformsForPublications($publications);
        $socialBadges = $this->resolveSocialBadgesForPublications($publications, includeMedia: $includeHeavyCalendarPayload);

        if (! $isCurrentMonth) {
            return [
                'activity_count' => 0,
                'task_primary' => null,
                'task_secondary' => null,
                'avatars' => [],
                'progress_width' => 0,
                'progress_tone' => 'none',
                'has_indicator' => false,
                'social_platforms' => [],
                'social_badges' => [],
                'has_social_publications' => false,
            ];
        }

        $activityCount = $activities->count();
        $primary = $activities->first();
        $secondary = $activities->skip(1)->first();
        $withMeetCount = $activities->filter(fn (CorporateAgendaActivity $activity): bool => $activity->has_google_meet)->count();

        $avatars = $includeHeavyCalendarPayload
            ? $activities
                ->flatMap(function (CorporateAgendaActivity $activity): Collection {
                    $activityTitle = $activity->short_description ?: 'Actividad';

                    return $activity->participants->map(
                        fn (CorporateAgendaActivityParticipant $participant): array => $this->buildAvatarData(
                            $participant->colaborador,
                            $activityTitle,
                            $participant->invitation_status?->value,
                        )
                    );
                })
                ->filter(fn (array $avatar): bool => filled($avatar['name']))
                ->groupBy('name')
                ->map(function (Collection $items): array {
                    /** @var array{name:string|null,email:string|null,avatar_url:string|null,initials:string,activity_titles:array<int,string>,status:string} $first */
                    $first = $items->first();

                    $activityTitles = $items
                        ->flatMap(fn (array $item): array => $item['activity_titles'] ?? [])
                        ->filter(fn (mixed $title): bool => is_string($title) && $title !== '')
                        ->unique()
                        ->take(3)
                        ->values()
                        ->all();

                    $first['activity_titles'] = $activityTitles;
                    $statuses = $items
                        ->pluck('status')
                        ->filter(fn (mixed $status): bool => is_string($status) && $status !== '')
                        ->values();

                    $first['status'] = match (true) {
                        $statuses->contains(CorporateAgendaInvitationStatus::Rejected->value) => CorporateAgendaInvitationStatus::Rejected->value,
                        $statuses->contains(CorporateAgendaInvitationStatus::Accepted->value) => CorporateAgendaInvitationStatus::Accepted->value,
                        default => CorporateAgendaInvitationStatus::Pending->value,
                    };

                    return $first;
                })
                ->take(4)
                ->values()
                ->all()
            : [];

        $progressWidth = $activityCount === 0
            ? 0
            : min(100, max(16, (int) floor(($activityCount / 5) * 100)));

        $progressTone = match (true) {
            $activityCount === 0 => 'none',
            $withMeetCount > 0 => 'amber',
            $activityCount >= 3 => 'neutral',
            default => 'cyan',
        };

        return [
            'activity_count' => $activityCount,
            'task_primary' => $primary?->short_description,
            'task_secondary' => $secondary?->short_description,
            'avatars' => $avatars,
            'progress_width' => $progressWidth,
            'progress_tone' => $progressTone,
            'has_indicator' => $withMeetCount > 0 || $socialPlatforms !== [],
            'social_platforms' => $socialPlatforms,
            'social_badges' => $socialBadges,
            'has_social_publications' => $socialPlatforms !== [],
        ];
    }

    public function setModalWorkspace(string $workspace): void
    {
        if (! in_array($workspace, ['activities', 'marketing'], true)) {
            return;
        }

        if ($workspace === 'marketing' && ! $this->canManageSocialPublications()) {
            Notification::make()
                ->title('Sin permisos')
                ->body('Solo usuarios del módulo MARKETING o SUPERADMIN pueden acceder al calendario publicitario.')
                ->warning()
                ->send();

            return;
        }

        $this->modalWorkspace = $workspace;

        if ($workspace === 'marketing') {
            $this->selectedActivityId = null;
            $this->isCreatingActivity = false;
            $this->newNote = '';
            $this->invitationRejectionNote = '';
            $this->isCreatingSocialPublication = true;

            if ($this->socialPublicationFormHydratedForDate !== $this->selectedDate) {
                $this->startCreateSocialPublication();
                $this->socialPublicationFormHydratedForDate = $this->selectedDate;
            }
        } else {
            $this->selectedSocialPublicationId = null;
            $this->isCreatingSocialPublication = false;
            if (! $this->isCreatingActivity && $this->selectedActivityId === null) {
                $this->isCreatingActivity = true;
            }
        }
    }

    public function openDayModal(string $date, string $workspace = 'activities'): void
    {
        $targetDate = Carbon::parse($date)->startOfDay();
        if ($targetDate->lt(now()->startOfDay())) {
            Notification::make()
                ->title('Fecha no disponible')
                ->body('No puedes registrar actividades en días pasados.')
                ->warning()
                ->send();

            return;
        }

        if ($workspace === 'marketing' && ! $this->canManageSocialPublications()) {
            $workspace = 'activities';
        }

        $this->selectedDate = $targetDate->toDateString();
        $this->selectedWeekDate = $this->selectedDate;
        $this->selectedActivityId = null;
        $this->selectedSocialPublicationId = null;
        $this->isCreatingActivity = $workspace === 'activities';
        $this->isCreatingSocialPublication = $workspace === 'marketing';
        $this->modalWorkspace = in_array($workspace, ['activities', 'marketing'], true) ? $workspace : 'activities';
        $this->newNote = '';
        $this->invitationRejectionNote = '';
        $this->activityForm = [
            'activity_date' => $this->selectedDate,
            'start_time' => '08:00',
            'end_time' => '09:00',
            'activity_type' => null,
            'has_google_meet' => false,
            'google_meet_url' => null,
            'participant_ids' => [],
            'description' => null,
        ];
        if ($this->modalWorkspace === 'marketing' && $this->canManageSocialPublications()) {
            $this->resetSocialPublicationFormForSelectedDate();
            $this->socialPublicationFormHydratedForDate = $this->selectedDate;
        } else {
            $this->socialPublicationFormHydratedForDate = null;
        }
        $this->isActivityModalOpen = true;
    }

    public function closeActivityModal(): void
    {
        $this->isActivityModalOpen = false;
        $this->isCreatingActivity = false;
        $this->isCreatingSocialPublication = false;
        $this->selectedActivityId = null;
        $this->selectedSocialPublicationId = null;
        $this->modalWorkspace = 'activities';
        $this->socialPublicationFormHydratedForDate = null;
        $this->newNote = '';
        $this->invitationRejectionNote = '';
    }

    public function startCreateActivity(): void
    {
        $this->selectedActivityId = null;
        $this->isCreatingActivity = true;
        $this->newNote = '';
        $this->invitationRejectionNote = '';
        $this->collaboratorSearch = '';
        $this->activityForm = [
            'activity_date' => $this->selectedDate,
            'start_time' => '08:00',
            'end_time' => '09:00',
            'activity_type' => null,
            'has_google_meet' => false,
            'google_meet_url' => null,
            'participant_ids' => [],
            'description' => null,
        ];
    }

    public function selectActivity(int $activityId): void
    {
        if ($this->selectedActivityId === $activityId) {
            $this->selectedActivityId = null;
            $this->isCreatingActivity = false;
            $this->newNote = '';
            $this->invitationRejectionNote = '';

            return;
        }

        $activity = $this->findVisibleActivityOrNull($activityId);
        if ($activity === null) {
            Notification::make()
                ->title('Actividad no disponible')
                ->body('No tienes permisos para ver esta actividad.')
                ->danger()
                ->send();

            return;
        }

        $this->selectedActivityId = $activity->id;
        $this->selectedDate = $activity->activity_date->toDateString();
        $this->isCreatingActivity = false;
        $this->newNote = '';
        $this->invitationRejectionNote = '';
        $this->fillFormFromActivity($activity);
    }

    public function saveActivity(): void
    {
        $validated = $this->validate([
            'activityForm.activity_date' => ['required', 'date', 'after_or_equal:today'],
            'activityForm.start_time' => ['required', 'date_format:H:i'],
            'activityForm.end_time' => ['required', 'date_format:H:i', 'after:activityForm.start_time'],
            'activityForm.activity_type' => ['required', Rule::in(array_keys(CorporateAgendaActivityType::options()))],
            'activityForm.has_google_meet' => ['required', 'boolean'],
            'activityForm.google_meet_url' => ['nullable', 'url', 'required_if:activityForm.has_google_meet,true'],
            'activityForm.participant_ids' => ['nullable', 'array'],
            'activityForm.participant_ids.*' => ['integer', 'exists:rrhh_colaboradors,id'],
            'activityForm.description' => ['required', 'string', 'min:5', 'max:5000'],
        ]);

        $activityDate = (string) $validated['activityForm']['activity_date'];
        $startTime = (string) $validated['activityForm']['start_time'];
        $endTime = (string) $validated['activityForm']['end_time'];
        $type = (string) $validated['activityForm']['activity_type'];
        $hasMeet = (bool) $validated['activityForm']['has_google_meet'];
        $meetUrl = $hasMeet ? (string) ($validated['activityForm']['google_meet_url'] ?? '') : null;
        $participantIds = array_values(array_unique(array_map('intval', $validated['activityForm']['participant_ids'] ?? [])));
        $description = (string) $validated['activityForm']['description'];

        $activity = $this->selectedActivityId !== null ? $this->findVisibleActivityOrNull($this->selectedActivityId) : null;

        if ($activity !== null && ! $this->canCurrentUserEdit($activity)) {
            Notification::make()
                ->title('Sin permisos')
                ->body('Solo el creador o un usuario SUPERADMIN puede actualizar la actividad.')
                ->warning()
                ->send();

            return;
        }

        $this->ensureSelectedCollaboratorsAreAvailable(
            activityDate: $activityDate,
            startTime: $startTime,
            endTime: $endTime,
            participantIds: $participantIds,
            ignoreActivityId: $activity?->id,
        );

        if ($activity === null) {
            $activity = CorporateAgendaActivity::query()->create([
                'creator_user_id' => (int) Auth::id(),
                'activity_date' => $activityDate,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'activity_type' => $type,
                'has_google_meet' => $hasMeet,
                'google_meet_url' => $meetUrl !== '' ? $meetUrl : null,
                'description' => $description,
            ]);
        } else {
            $activity->update([
                'activity_date' => $activityDate,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'activity_type' => $type,
                'has_google_meet' => $hasMeet,
                'google_meet_url' => $meetUrl !== '' ? $meetUrl : null,
                'description' => $description,
            ]);
        }

        /** @var Collection<int, CorporateAgendaActivityParticipant> $existingParticipants */
        $existingParticipants = $activity->participants()
            ->get(['rrhh_colaborador_id', 'invitation_status', 'response_note'])
            ->keyBy('rrhh_colaborador_id');

        if ($participantIds === []) {
            $activity->participants()->delete();
        } else {
            $activity->participants()
                ->whereNotIn('rrhh_colaborador_id', $participantIds)
                ->delete();
        }

        foreach ($participantIds as $participantId) {
            CorporateAgendaActivityParticipant::query()->updateOrCreate(
                [
                    'activity_id' => $activity->id,
                    'rrhh_colaborador_id' => $participantId,
                ],
                [
                    'invitation_status' => $existingParticipants->has($participantId)
                        ? (string) ($existingParticipants->get($participantId)?->invitation_status?->value ?? $existingParticipants->get($participantId)?->getRawOriginal('invitation_status') ?? CorporateAgendaInvitationStatus::Pending->value)
                        : CorporateAgendaInvitationStatus::Pending->value,
                    'response_note' => $existingParticipants->get($participantId)?->response_note,
                ],
            );
        }

        $activity->load([
            'creator:id,name,email,phone',
            'participants.colaborador:id,fullName,emailCorporativo,emailPersonal,avatar,user_id',
            'notes.user:id,name,email',
        ]);

        $this->dispatchAgendaInvitationsToParticipants($activity);

        $this->selectedActivityId = $activity->id;
        $this->selectedDate = $activity->activity_date->toDateString();
        $this->isCreatingActivity = false;
        $this->fillFormFromActivity($activity);
        $this->collaboratorSearch = '';

        Notification::make()
            ->title('Actividad guardada')
            ->body('La actividad fue registrada correctamente en la agenda.')
            ->success()
            ->send();
    }

    public function updatedActivityFormActivityDate(?string $value): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        try {
            $selectedDate = Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return;
        }

        if ($selectedDate->lt(now()->startOfDay())) {
            $safeDate = now()->toDateString();
            $this->activityForm['activity_date'] = $safeDate;

            Notification::make()
                ->title('Fecha no permitida')
                ->body('No puedes seleccionar fechas pasadas para crear o mover actividades.')
                ->warning()
                ->send();
        }
    }

    public function deleteSelectedActivity(): void
    {
        if ($this->selectedActivityId === null) {
            return;
        }

        $activity = $this->findVisibleActivityOrNull($this->selectedActivityId);
        if ($activity === null || ! $this->canCurrentUserEdit($activity)) {
            Notification::make()
                ->title('Sin permisos')
                ->body('Solo el creador o un usuario SUPERADMIN puede eliminar la actividad.')
                ->warning()
                ->send();

            return;
        }

        $activity->delete();

        $this->selectedActivityId = null;
        $this->isCreatingActivity = true;
        $this->activityForm = [
            'activity_date' => $this->selectedDate,
            'start_time' => '08:00',
            'end_time' => '09:00',
            'activity_type' => null,
            'has_google_meet' => false,
            'google_meet_url' => null,
            'participant_ids' => [],
            'description' => null,
        ];
        $this->newNote = '';
        $this->invitationRejectionNote = '';

        Notification::make()
            ->title('Actividad eliminada')
            ->body('La actividad fue eliminada de la agenda.')
            ->success()
            ->send();
    }

    public function addNote(): void
    {
        if ($this->selectedActivityId === null) {
            return;
        }

        $activity = $this->findVisibleActivityOrNull($this->selectedActivityId);
        if ($activity === null || ! $this->canCurrentUserEdit($activity)) {
            Notification::make()
                ->title('Sin permisos')
                ->body('Solo el creador o un usuario SUPERADMIN puede agregar notas.')
                ->warning()
                ->send();

            return;
        }

        $this->validate([
            'newNote' => ['required', 'string', 'min:3', 'max:1500'],
        ]);

        $activity->notes()->create([
            'user_id' => (int) Auth::id(),
            'note' => trim($this->newNote),
        ]);

        $this->newNote = '';
        $this->selectedActivityId = $activity->id;

        Notification::make()
            ->title('Nota registrada')
            ->body('Se agregó la nota a la actividad.')
            ->success()
            ->send();
    }

    public function acceptMeet(int $activityId): void
    {
        $this->respondToMeetInvitation($activityId, CorporateAgendaInvitationStatus::Accepted);
    }

    public function rejectMeet(int $activityId): void
    {
        $this->respondToMeetInvitation($activityId, CorporateAgendaInvitationStatus::Rejected);
    }

    /**
     * @return array<string, string>
     */
    public function getActivityTypeOptionsProperty(): array
    {
        return CorporateAgendaActivityType::options();
    }

    /**
     * @return array<string, string>
     */
    public function getTimeOptionsProperty(): array
    {
        $options = [];
        $cursor = Carbon::createFromTime(6, 0);
        $end = Carbon::createFromTime(22, 0);

        while ($cursor->lte($end)) {
            $key = $cursor->format('H:i');
            $options[$key] = $cursor->format('g:i A');
            $cursor->addMinutes(30);
        }

        return $options;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCollaboratorOptionsProperty(): array
    {
        if (! $this->isActivityModalOpen || $this->modalWorkspace !== 'activities') {
            return [];
        }

        if ($this->collaboratorOptionsCache !== null) {
            return $this->collaboratorOptionsCache;
        }

        $this->collaboratorOptionsCache = RrhhColaborador::query()
            ->orderBy('fullName')
            ->get(['id', 'fullName', 'emailCorporativo', 'emailPersonal', 'avatar'])
            ->map(function (RrhhColaborador $colaborador): array {
                return [
                    'id' => $colaborador->id,
                    'name' => $colaborador->fullName,
                    'email' => $colaborador->emailCorporativo ?: $colaborador->emailPersonal,
                ];
            })
            ->all();

        return $this->collaboratorOptionsCache;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getFilteredCollaboratorOptionsProperty(): array
    {
        $term = Str::lower(trim($this->collaboratorSearch));

        $selectedIds = collect($this->activityForm['participant_ids'] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        if ($term === '') {
            $selectedCollaborators = collect($this->collaboratorOptions)
                ->whereIn('id', $selectedIds);

            $firstBatch = collect($this->collaboratorOptions)->take(50);

            return $selectedCollaborators
                ->concat($firstBatch)
                ->unique('id')
                ->values()
                ->all();
        }

        return collect($this->collaboratorOptions)
            ->filter(function (array $collaborator) use ($term): bool {
                $name = Str::lower((string) ($collaborator['name'] ?? ''));
                $email = Str::lower((string) ($collaborator['email'] ?? ''));

                return Str::contains($name, $term) || Str::contains($email, $term);
            })
            ->values()
            ->all();
    }

    public function selectAllFilteredCollaborators(): void
    {
        $current = collect($this->activityForm['participant_ids'] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $filtered = collect($this->filteredCollaboratorOptions)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $this->activityForm['participant_ids'] = collect(array_merge($current, $filtered))
            ->unique()
            ->values()
            ->all();
    }

    public function clearCollaboratorsSelection(): void
    {
        $this->activityForm['participant_ids'] = [];
    }

    /**
     * @return Collection<int, CorporateAgendaActivity>
     */
    public function getSelectedDateActivitiesProperty(): Collection
    {
        if (! $this->isActivityModalOpen) {
            return collect();
        }

        return $this->visibleActivitiesBetween(
            Carbon::parse($this->selectedDate)->startOfDay(),
            Carbon::parse($this->selectedDate)->endOfDay(),
        )
            ->select([
                'id',
                'creator_user_id',
                'activity_date',
                'start_time',
                'end_time',
                'activity_type',
                'has_google_meet',
                'google_meet_url',
                'description',
                'created_at',
            ])
            ->orderBy('start_time')
            ->orderBy('created_at')
            ->get();
    }

    public function getSelectedActivityProperty(): ?CorporateAgendaActivity
    {
        if ($this->selectedActivityId === null) {
            return null;
        }

        return $this->visibleActivitiesBetween(
            Carbon::parse($this->selectedDate)->startOfDay(),
            Carbon::parse($this->selectedDate)->endOfDay(),
        )
            ->with([
                'creator:id,name,email,phone',
                'participants.colaborador:id,fullName,emailCorporativo,emailPersonal,avatar,user_id',
                'notes.user:id,name,email',
            ])
            ->whereKey($this->selectedActivityId)
            ->first();
    }

    public function isCurrentUserCreator(?CorporateAgendaActivity $activity): bool
    {
        if ($activity === null) {
            return false;
        }

        return (int) $activity->creator_user_id === (int) Auth::id();
    }

    public function canCurrentUserRespondToMeet(?CorporateAgendaActivity $activity): bool
    {
        if ($activity === null) {
            return false;
        }

        return $this->resolveCurrentParticipantForActivity($activity) !== null;
    }

    public function getCurrentParticipantForSelectedActivityProperty(): ?CorporateAgendaActivityParticipant
    {
        $activity = $this->selectedActivity;
        if ($activity === null) {
            return null;
        }

        return $this->resolveCurrentParticipantForActivity($activity);
    }

    private function respondToMeetInvitation(int $activityId, CorporateAgendaInvitationStatus $status): void
    {
        $activity = $this->findVisibleActivityOrNull($activityId);
        if ($activity === null) {
            return;
        }

        $participant = $this->resolveParticipantForAuthenticatedUser($activity);

        if ($participant === null) {
            Notification::make()
                ->title('Sin permisos')
                ->body('No estás asignado a esta actividad.')
                ->warning()
                ->send();

            return;
        }

        $responseNote = null;
        if ($status === CorporateAgendaInvitationStatus::Rejected) {
            $this->validate([
                'invitationRejectionNote' => ['required', 'string', 'min:5', 'max:1500'],
            ], [
                'invitationRejectionNote.required' => 'Debes indicar el motivo del rechazo.',
                'invitationRejectionNote.min' => 'El motivo del rechazo debe tener al menos 5 caracteres.',
            ]);

            $responseNote = trim($this->invitationRejectionNote);
        }

        $participant->update([
            'invitation_status' => $status->value,
            'response_note' => $responseNote,
        ]);

        $this->notifyCreatorAboutInvitationResponse(
            $activity,
            $status,
            $participant->rrhh_colaborador_id,
            $responseNote,
        );

        if ($status === CorporateAgendaInvitationStatus::Accepted) {
            $this->invitationRejectionNote = '';
        }

        Notification::make()
            ->title($status === CorporateAgendaInvitationStatus::Accepted ? 'Invitación aceptada' : 'Invitación rechazada')
            ->body('Se actualizó tu respuesta para esta actividad.')
            ->success()
            ->send();
    }

    private function notifyCreatorAboutInvitationResponse(
        CorporateAgendaActivity $activity,
        CorporateAgendaInvitationStatus $status,
        int $participantColaboradorId,
        ?string $responseNote = null,
    ): void {
        /** @var CorporateAgendaActivityParticipant|null $participant */
        $participant = $activity->participants
            ->first(fn (CorporateAgendaActivityParticipant $item): bool => (int) $item->rrhh_colaborador_id === $participantColaboradorId);

        if ($participant === null) {
            return;
        }

        CorporateAgendaInvitationWhatsAppService::notifyCreatorAboutInvitationResponse(
            participant: $participant,
            status: $status,
            responseNote: $responseNote,
            requestedByUserId: Auth::id() !== null ? (int) Auth::id() : null,
            panel: 'business',
        );
    }

    private function dispatchAgendaInvitationsToParticipants(CorporateAgendaActivity $activity): void
    {
        $requestedByUserId = Auth::id() !== null ? (int) Auth::id() : (int) $activity->creator_user_id;

        foreach ($activity->participants as $participant) {
            CorporateAgendaInvitationWhatsAppService::dispatchInvitationToParticipant(
                participant: $participant,
                requestedByUserId: $requestedByUserId,
                panel: 'business',
            );
        }
    }

    private function fillFormFromActivity(CorporateAgendaActivity $activity): void
    {
        $this->activityForm = [
            'activity_date' => $activity->activity_date->toDateString(),
            'start_time' => Str::of((string) $activity->start_time)->substr(0, 5)->toString(),
            'end_time' => Str::of((string) $activity->end_time)->substr(0, 5)->toString(),
            'activity_type' => $activity->activity_type?->value,
            'has_google_meet' => (bool) $activity->has_google_meet,
            'google_meet_url' => $activity->google_meet_url,
            'participant_ids' => $activity->participants->pluck('rrhh_colaborador_id')->map(fn (mixed $id): int => (int) $id)->values()->all(),
            'description' => $activity->description,
        ];
    }

    public function canCurrentUserEdit(?CorporateAgendaActivity $activity): bool
    {
        if ($activity === null) {
            return false;
        }

        if ($this->userIsSuperAdmin()) {
            return true;
        }

        return (int) $activity->creator_user_id === (int) Auth::id();
    }

    private function findVisibleActivityOrNull(int $activityId): ?CorporateAgendaActivity
    {
        return $this->visibleActivitiesBetween(
            Carbon::create(2000, 1, 1),
            Carbon::create(2100, 1, 1),
        )
            ->with([
                'creator:id,name,email,phone',
                'participants.colaborador:id,fullName,emailCorporativo,emailPersonal,avatar,user_id',
                'notes.user:id,name,email',
            ])
            ->whereKey($activityId)
            ->first();
    }

    private function visibleActivitiesBetween(Carbon $start, Carbon $end): Builder
    {
        $query = CorporateAgendaActivity::query()
            ->whereDate('activity_date', '>=', $start->toDateString())
            ->whereDate('activity_date', '<=', $end->toDateString());

        if ($this->userIsSuperAdmin()) {
            return $query;
        }

        $currentUserId = Auth::id() !== null ? (int) Auth::id() : 0;
        $participantIds = $this->currentCollaboratorIds();

        return $query->where(function (Builder $visibilityQuery) use ($currentUserId, $participantIds): void {
            $visibilityQuery->where('creator_user_id', $currentUserId);

            if ($participantIds !== []) {
                $visibilityQuery->orWhereHas('participants', function (Builder $participantQuery) use ($participantIds): void {
                    $participantQuery->whereIn('rrhh_colaborador_id', $participantIds);
                });
            }
        });
    }

    /**
     * @return array<int>
     */
    private function currentCollaboratorIds(): array
    {
        if ($this->currentCollaboratorIdsCache !== null) {
            return $this->currentCollaboratorIdsCache;
        }

        $user = Auth::user();
        if ($user === null) {
            $this->currentCollaboratorIdsCache = [];

            return [];
        }

        $query = RrhhColaborador::query();
        $query->where(function (Builder $builder) use ($user): void {
            $builder->where('user_id', (int) $user->id);

            $email = is_string($user->email) ? trim($user->email) : '';
            if ($email !== '') {
                $builder->orWhere('emailCorporativo', $email)
                    ->orWhere('emailPersonal', $email);
            }

            $name = is_string($user->name) ? trim($user->name) : '';
            if ($name !== '') {
                $builder->orWhereRaw('LOWER(fullName) = ?', [Str::lower($name)]);
            }
        });

        $this->currentCollaboratorIdsCache = $query->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        return $this->currentCollaboratorIdsCache;
    }

    private function currentCollaboratorId(): ?int
    {
        $user = Auth::user();
        if ($user === null) {
            return null;
        }

        $byUserId = RrhhColaborador::query()
            ->where('user_id', $user->id)
            ->value('id');
        if ($byUserId !== null) {
            return (int) $byUserId;
        }

        $email = is_string($user->email) ? trim($user->email) : '';
        if ($email !== '') {
            $byEmail = RrhhColaborador::query()
                ->where(function (Builder $query) use ($email): void {
                    $query->where('emailCorporativo', $email)
                        ->orWhere('emailPersonal', $email);
                })
                ->value('id');
            if ($byEmail !== null) {
                return (int) $byEmail;
            }
        }

        $name = is_string($user->name) ? trim($user->name) : '';
        if ($name !== '') {
            $byName = RrhhColaborador::query()
                ->whereRaw('LOWER(fullName) = ?', [Str::lower($name)])
                ->value('id');
            if ($byName !== null) {
                return (int) $byName;
            }
        }

        return null;
    }

    private function resolveCurrentParticipantForActivity(CorporateAgendaActivity $activity): ?CorporateAgendaActivityParticipant
    {
        return $this->resolveParticipantForAuthenticatedUser($activity);
    }

    private function resolveParticipantForAuthenticatedUser(CorporateAgendaActivity $activity): ?CorporateAgendaActivityParticipant
    {
        $user = Auth::user();
        if ($user === null) {
            return null;
        }

        $currentCollaboratorId = $this->currentCollaboratorId();
        if ($currentCollaboratorId !== null) {
            /** @var CorporateAgendaActivityParticipant|null $participant */
            $participant = $activity->participants
                ->first(fn (CorporateAgendaActivityParticipant $item): bool => (int) $item->rrhh_colaborador_id === $currentCollaboratorId);

            if ($participant !== null) {
                return $participant;
            }
        }

        $normalizedEmail = is_string($user->email) ? Str::lower(trim($user->email)) : '';
        $normalizedName = is_string($user->name) ? Str::lower(trim($user->name)) : '';

        /** @var CorporateAgendaActivityParticipant|null $participant */
        $participant = $activity->participants->first(function (CorporateAgendaActivityParticipant $item) use ($user, $normalizedEmail, $normalizedName): bool {
            $colaborador = $item->colaborador;
            if ($colaborador === null) {
                return false;
            }

            if ((int) ($colaborador->user_id ?? 0) === (int) $user->id) {
                return true;
            }

            $corpEmail = Str::lower(trim((string) ($colaborador->emailCorporativo ?? '')));
            $personalEmail = Str::lower(trim((string) ($colaborador->emailPersonal ?? '')));
            if ($normalizedEmail !== '' && ($corpEmail === $normalizedEmail || $personalEmail === $normalizedEmail)) {
                return true;
            }

            $fullName = Str::lower(trim((string) ($colaborador->fullName ?? '')));

            return $normalizedName !== '' && $fullName === $normalizedName;
        });

        return $participant;
    }

    /**
     * @param  array<int, int>  $participantIds
     *
     * @throws ValidationException
     */
    private function ensureSelectedCollaboratorsAreAvailable(
        string $activityDate,
        string $startTime,
        string $endTime,
        array $participantIds,
        ?int $ignoreActivityId = null,
    ): void {
        if ($participantIds === []) {
            return;
        }

        $participantIds = array_values(array_unique(array_map('intval', $participantIds)));

        $collaborators = RrhhColaborador::query()
            ->whereIn('id', $participantIds)
            ->get(['id', 'fullName', 'user_id']);

        if ($collaborators->isEmpty()) {
            return;
        }

        $collaboratorNamesById = $collaborators
            ->mapWithKeys(fn (RrhhColaborador $collaborator): array => [
                (int) $collaborator->id => (string) ($collaborator->fullName ?: ('Colaborador #'.$collaborator->id)),
            ])
            ->all();

        $collaboratorByUserId = $collaborators
            ->filter(fn (RrhhColaborador $collaborator): bool => filled($collaborator->user_id))
            ->mapWithKeys(fn (RrhhColaborador $collaborator): array => [(int) $collaborator->user_id => (int) $collaborator->id])
            ->all();

        $creatorUserIds = array_values(array_unique(array_keys($collaboratorByUserId)));

        $conflictingActivities = CorporateAgendaActivity::query()
            ->when($ignoreActivityId !== null, fn (Builder $query): Builder => $query->whereKeyNot($ignoreActivityId))
            ->whereDate('activity_date', $activityDate)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->where(function (Builder $query) use ($participantIds, $creatorUserIds): void {
                $query->whereHas('participants', function (Builder $participantQuery) use ($participantIds): void {
                    $participantQuery->whereIn('rrhh_colaborador_id', $participantIds);
                });

                if ($creatorUserIds !== []) {
                    $query->orWhereIn('creator_user_id', $creatorUserIds);
                }
            })
            ->with([
                'participants' => function ($participantQuery) use ($participantIds): void {
                    $participantQuery
                        ->whereIn('rrhh_colaborador_id', $participantIds)
                        ->select(['id', 'activity_id', 'rrhh_colaborador_id']);
                },
            ])
            ->get(['id', 'creator_user_id']);

        if ($conflictingActivities->isEmpty()) {
            return;
        }

        $conflictingCollaboratorIds = [];

        foreach ($conflictingActivities as $conflictingActivity) {
            foreach ($conflictingActivity->participants as $participant) {
                $participantId = (int) $participant->rrhh_colaborador_id;
                if (in_array($participantId, $participantIds, true)) {
                    $conflictingCollaboratorIds[] = $participantId;
                }
            }

            $creatorUserId = (int) $conflictingActivity->creator_user_id;
            if ($creatorUserId > 0 && isset($collaboratorByUserId[$creatorUserId])) {
                $conflictingCollaboratorIds[] = (int) $collaboratorByUserId[$creatorUserId];
            }
        }

        $conflictingCollaboratorIds = array_values(array_unique($conflictingCollaboratorIds));

        if ($conflictingCollaboratorIds === []) {
            return;
        }

        $conflictingNames = collect($conflictingCollaboratorIds)
            ->map(fn (int $collaboratorId): string => $collaboratorNamesById[$collaboratorId] ?? ('Colaborador #'.$collaboratorId))
            ->unique()
            ->values()
            ->all();

        throw ValidationException::withMessages([
            'activityForm.participant_ids' => 'No se pudo guardar: estos colaboradores ya tienen actividad en ese rango horario ('
                .$startTime.' - '.$endTime.'): '.implode(', ', $conflictingNames).'.',
        ]);
    }

    /**
     * @return array{name:string|null,email:string|null,avatar_url:string|null,initials:string,activity_titles:array<int,string>,status:string}
     */
    private function buildAvatarData(?RrhhColaborador $colaborador, string $activityTitle, ?string $invitationStatus): array
    {
        $name = $colaborador?->fullName;
        $email = $colaborador?->emailCorporativo ?: $colaborador?->emailPersonal;
        $avatar = is_string($colaborador?->avatar) ? trim((string) $colaborador->avatar) : null;
        $avatarUrl = null;

        if (is_string($avatar) && $avatar !== '') {
            $avatarUrl = $this->publicStorageUrl($avatar);
        }

        return [
            'name' => $name,
            'email' => $email,
            'avatar_url' => $avatarUrl,
            'initials' => $this->resolveInitials($name ?? ''),
            'activity_titles' => [$activityTitle],
            'status' => in_array($invitationStatus, [
                CorporateAgendaInvitationStatus::Accepted->value,
                CorporateAgendaInvitationStatus::Rejected->value,
                CorporateAgendaInvitationStatus::Pending->value,
            ], true) ? $invitationStatus : CorporateAgendaInvitationStatus::Pending->value,
        ];
    }

    private function resolveInitials(string $name): string
    {
        $parts = collect(preg_split('/\s+/', trim($name)) ?: [])
            ->filter(fn (?string $part): bool => is_string($part) && $part !== '')
            ->values();

        if ($parts->isEmpty()) {
            return 'NA';
        }

        if ($parts->count() === 1) {
            return Str::upper(Str::substr((string) $parts->first(), 0, 2));
        }

        return Str::upper(
            Str::substr((string) $parts->first(), 0, 1)
            .Str::substr((string) $parts->last(), 0, 1)
        );
    }

    private function resolveCursor(): Carbon
    {
        return Carbon::parse($this->cursorMonth)->startOfMonth();
    }

    private function userIsSuperAdmin(): bool
    {
        $rawDepartments = Auth::user()?->departament;
        $departments = is_array($rawDepartments) ? $rawDepartments : [(string) $rawDepartments];

        // Extra safety: supports JSON/string payloads with mixed formatting.
        $serialized = Str::upper((string) json_encode($rawDepartments, JSON_UNESCAPED_UNICODE));
        $normalizedSerialized = str_replace([' ', '-', '_'], '', $serialized);
        if (Str::contains($normalizedSerialized, 'SUPERADMIN')) {
            return true;
        }

        foreach ($departments as $department) {
            $departmentText = Str::upper((string) $department);
            $normalizedDepartment = str_replace([' ', '-', '_'], '', $departmentText);
            if ($normalizedDepartment === 'SUPERADMIN' || Str::contains($normalizedDepartment, 'SUPERADMIN')) {
                return true;
            }
        }

        return false;
    }

    private function userIsMarketingDepartment(): bool
    {
        $rawDepartments = Auth::user()?->departament;
        $departments = is_array($rawDepartments) ? $rawDepartments : [(string) $rawDepartments];

        $serialized = Str::upper((string) json_encode($rawDepartments, JSON_UNESCAPED_UNICODE));
        $normalizedSerialized = str_replace([' ', '-', '_'], '', $serialized);
        if (Str::contains($normalizedSerialized, 'MARKETING')) {
            return true;
        }

        foreach ($departments as $department) {
            $departmentText = Str::upper((string) $department);
            $normalizedDepartment = str_replace([' ', '-', '_'], '', $departmentText);
            if ($normalizedDepartment === 'MARKETING' || Str::contains($normalizedDepartment, 'MARKETING')) {
                return true;
            }
        }

        return false;
    }

    public function canCreateActivity(): bool
    {
        return Auth::check();
    }

    public function canManageSocialPublications(): bool
    {
        return Auth::check() && ($this->userIsSuperAdmin() || $this->userIsMarketingDepartment());
    }

    /**
     * @return array<string, string>
     */
    public function getSocialPlatformOptionsProperty(): array
    {
        if (! $this->canManageSocialPublications()) {
            return [];
        }

        return CorporateAgendaSocialPlatform::options();
    }

    /**
     * @return Collection<int, CorporateAgendaSocialPublication>
     */
    public function getSelectedDateSocialPublicationsProperty(): Collection
    {
        if (! $this->isActivityModalOpen || ! $this->canManageSocialPublications() || $this->modalWorkspace !== 'marketing') {
            return collect();
        }

        return $this->socialPublicationsBetween(
            Carbon::parse($this->selectedDate)->startOfDay(),
            Carbon::parse($this->selectedDate)->endOfDay(),
        )
            ->with('creator:id,name')
            ->orderBy('platform')
            ->get();
    }

    public function getSelectedSocialPublicationProperty(): ?CorporateAgendaSocialPublication
    {
        if ($this->selectedSocialPublicationId === null) {
            return null;
        }

        return $this->selectedDateSocialPublications
            ->firstWhere('id', $this->selectedSocialPublicationId);
    }

    public function startCreateSocialPublication(): void
    {
        if (! $this->canManageSocialPublications()) {
            return;
        }

        $this->selectedSocialPublicationId = null;
        $this->isCreatingSocialPublication = true;
        $this->resetSocialPublicationFormForSelectedDate();
    }

    public function selectSocialPublication(int $publicationId): void
    {
        if (! $this->canManageSocialPublications()) {
            return;
        }

        if ($this->selectedSocialPublicationId === $publicationId) {
            $this->selectedSocialPublicationId = null;
            $this->isCreatingSocialPublication = false;
            $this->resetSocialPublicationFormForSelectedDate();

            return;
        }

        $publication = $this->findSocialPublicationOrNull($publicationId);
        if ($publication === null) {
            Notification::make()
                ->title('Publicación no disponible')
                ->body('No se encontró la publicación seleccionada.')
                ->warning()
                ->send();

            return;
        }

        $this->selectedSocialPublicationId = $publication->id;
        $this->selectedDate = $publication->publication_date->toDateString();
        $this->isCreatingSocialPublication = false;
        $this->fillSocialPublicationFormFromDate();
        $this->socialPublicationFormHydratedForDate = $this->selectedDate;
    }

    public function saveSocialPublications(): void
    {
        if (! $this->canManageSocialPublications()) {
            Notification::make()
                ->title('Sin permisos')
                ->body('Solo usuarios del módulo MARKETING o SUPERADMIN pueden gestionar publicaciones.')
                ->warning()
                ->send();

            return;
        }

        $validated = $this->validate([
            'socialPublicationForm.publication_date' => ['required', 'date', 'after_or_equal:today'],
            'socialPublicationForm.platforms' => ['required', 'array', 'min:1'],
            'socialPublicationForm.platforms.*' => ['required', Rule::in(CorporateAgendaSocialPlatform::values())],
            'socialPublicationBriefByPlatform' => ['nullable', 'array'],
            'socialPublicationBriefByPlatform.*' => ['nullable', 'string', 'max:2000'],
            'socialPublicationUploadsByPlatform' => ['nullable', 'array'],
            'socialPublicationUploadsByPlatform.*' => ['nullable', 'array'],
            'socialPublicationUploadsByPlatform.*.*' => ['file', 'mimes:jpg,jpeg,png,webp,gif,mp4,webm,mov,pdf,doc,docx,xls,xlsx,ppt,pptx', 'max:10240'],
        ], [
            'socialPublicationForm.platforms.required' => 'Selecciona al menos una red social para programar la publicación.',
            'socialPublicationForm.platforms.min' => 'Selecciona al menos una red social para programar la publicación.',
            'socialPublicationUploadsByPlatform.*.*.mimes' => 'Solo se permiten imágenes, videos y documentos de oficina (JPG, PNG, WEBP, GIF, MP4, WEBM, MOV, PDF, DOCX, XLSX, PPTX).',
            'socialPublicationUploadsByPlatform.*.*.max' => 'Cada archivo debe pesar máximo 10 MB.',
        ]);

        $publicationDate = (string) $validated['socialPublicationForm']['publication_date'];
        $platforms = array_values(array_unique(array_map('strval', $validated['socialPublicationForm']['platforms'] ?? [])));

        $existingPublications = $this->socialPublicationsBetween(
            Carbon::parse($publicationDate)->startOfDay(),
            Carbon::parse($publicationDate)->endOfDay(),
        )->get()->keyBy(fn (CorporateAgendaSocialPublication $publication): string => $publication->platform?->value ?? (string) $publication->getRawOriginal('platform'));

        foreach ($platforms as $platform) {
            $publication = $existingPublications->get($platform);

            if ($publication !== null && ! $this->canCurrentUserEditSocialPublication($publication)) {
                Notification::make()
                    ->title('Sin permisos')
                    ->body('Solo el creador o un usuario SUPERADMIN puede modificar publicaciones ya registradas.')
                    ->warning()
                    ->send();

                return;
            }
        }

        foreach (CorporateAgendaSocialPlatform::values() as $platform) {
            $publication = $existingPublications->get($platform);
            $shouldExist = in_array($platform, $platforms, true);

            if ($shouldExist) {
                $briefByPlatform = collect($this->socialPublicationBriefByPlatform)->get($platform);
                $brief = filled($briefByPlatform)
                    ? trim((string) $briefByPlatform)
                    : null;

                $existingAttachmentPaths = collect($this->socialPublicationExistingAttachmentsByPlatform[$platform] ?? [])
                    ->map(fn (mixed $path): string => trim((string) $path))
                    ->filter(fn (string $path): bool => $path !== '')
                    ->unique()
                    ->values()
                    ->all();

                $uploadedAttachmentPaths = collect($this->socialPublicationUploadsByPlatform[$platform] ?? [])
                    ->map(function ($upload): ?string {
                        if (! is_object($upload) || ! method_exists($upload, 'store')) {
                            return null;
                        }

                        $storedPath = $upload->store('corporate-agenda/social-publications', 'public');

                        return is_string($storedPath) && $storedPath !== '' ? $storedPath : null;
                    })
                    ->filter(fn (?string $path): bool => $path !== null)
                    ->values()
                    ->all();

                $attachments = collect([...$existingAttachmentPaths, ...$uploadedAttachmentPaths])
                    ->unique()
                    ->values()
                    ->all();

                if ($publication === null) {
                    CorporateAgendaSocialPublication::query()->create([
                        'creator_user_id' => (int) Auth::id(),
                        'publication_date' => $publicationDate,
                        'platform' => $platform,
                        'brief' => $brief,
                        'attachments' => $attachments,
                    ]);

                    continue;
                }

                if ($this->canCurrentUserEditSocialPublication($publication)) {
                    $publication->update([
                        'brief' => $brief,
                        'attachments' => $attachments,
                    ]);
                }
            } elseif ($publication !== null && $this->canCurrentUserEditSocialPublication($publication)) {
                $publication->delete();
            }
        }

        $this->selectedSocialPublicationId = null;
        $this->isCreatingSocialPublication = true;
        $this->selectedDate = $publicationDate;
        $this->socialPublicationUploadsByPlatform = [];
        $this->fillSocialPublicationFormFromDate();
        $this->socialPublicationFormHydratedForDate = $this->selectedDate;

        Notification::make()
            ->title('Calendario publicitario actualizado')
            ->body('Las publicaciones del día fueron sincronizadas correctamente.')
            ->success()
            ->send();
    }

    public function deleteSelectedSocialPublication(): void
    {
        if (! $this->canManageSocialPublications()) {
            return;
        }

        if ($this->selectedSocialPublicationId === null) {
            return;
        }

        $publication = $this->findSocialPublicationOrNull($this->selectedSocialPublicationId);
        if ($publication === null || ! $this->canCurrentUserEditSocialPublication($publication)) {
            Notification::make()
                ->title('Sin permisos')
                ->body('Solo el creador o un usuario SUPERADMIN puede eliminar esta publicación.')
                ->warning()
                ->send();

            return;
        }

        $publication->delete();
        $this->selectedSocialPublicationId = null;
        $this->isCreatingSocialPublication = true;
        $this->fillSocialPublicationFormFromDate();
        $this->socialPublicationFormHydratedForDate = $this->selectedDate;

        Notification::make()
            ->title('Publicación eliminada')
            ->body('Se quitó la red social del calendario publicitario de este día.')
            ->success()
            ->send();
    }

    public function updatedSocialPublicationFormPlatforms(): void
    {
        if (! $this->canManageSocialPublications()) {
            $this->socialPublicationForm['platforms'] = [];

            return;
        }

        $platforms = collect($this->socialPublicationForm['platforms'] ?? [])
            ->map(fn (mixed $platform): string => (string) $platform)
            ->filter(fn (string $platform): bool => $platform !== '')
            ->unique()
            ->values()
            ->all();

        $this->socialPublicationForm['platforms'] = $platforms;
        $this->syncSocialPublicationPlatformPayloads($platforms);
    }

    public function removeSocialPublicationAttachment(string $platform, int $index): void
    {
        if (! $this->canManageSocialPublications()) {
            return;
        }

        if (! isset($this->socialPublicationExistingAttachmentsByPlatform[$platform][$index])) {
            return;
        }

        unset($this->socialPublicationExistingAttachmentsByPlatform[$platform][$index]);
        $this->socialPublicationExistingAttachmentsByPlatform[$platform] = array_values($this->socialPublicationExistingAttachmentsByPlatform[$platform]);
    }

    /**
     * @return array<string, array<int, array{url:string,name:string,is_image:bool,is_video:bool,ext:string}>>
     */
    public function getSocialPublicationAttachmentPreviewsByPlatformProperty(): array
    {
        return collect($this->socialPublicationForm['platforms'] ?? [])
            ->mapWithKeys(function (mixed $platform): array {
                $platformKey = (string) $platform;
                $paths = $this->socialPublicationExistingAttachmentsByPlatform[$platformKey] ?? [];

                return [$platformKey => $this->buildStoredAttachmentPreviews($paths)];
            })
            ->all();
    }

    /**
     * @return array<string, array<int, array{url:string,name:string,is_image:bool,is_video:bool,ext:string}>>
     */
    public function getSocialPublicationUploadPreviewsByPlatformProperty(): array
    {
        return collect($this->socialPublicationForm['platforms'] ?? [])
            ->mapWithKeys(function (mixed $platform): array {
                $platformKey = (string) $platform;
                $uploads = $this->socialPublicationUploadsByPlatform[$platformKey] ?? [];
                $previews = collect($uploads)
                    ->map(function ($upload): ?array {
                        if (! is_object($upload)) {
                            return null;
                        }

                        $name = method_exists($upload, 'getClientOriginalName')
                            ? (string) $upload->getClientOriginalName()
                            : 'archivo';
                        $mime = method_exists($upload, 'getMimeType')
                            ? (string) $upload->getMimeType()
                            : '';
                        $isImage = str_starts_with($mime, 'image/') && method_exists($upload, 'temporaryUrl');
                        $isVideo = str_starts_with($mime, 'video/') && method_exists($upload, 'temporaryUrl');
                        $url = ($isImage || $isVideo) ? (string) $upload->temporaryUrl() : null;
                        $ext = Str::lower(pathinfo($name, PATHINFO_EXTENSION));

                        return [
                            'url' => $url ?? '',
                            'name' => $name,
                            'is_image' => $isImage,
                            'is_video' => $isVideo,
                            'ext' => $ext !== '' ? $ext : 'archivo',
                        ];
                    })
                    ->filter(fn (?array $preview): bool => $preview !== null)
                    ->values()
                    ->all();

                return [$platformKey => $previews];
            })
            ->all();
    }

    /**
     * @return array<int, array{url:string,name:string,is_image:bool,is_video:bool,ext:string}>
     */
    public function getSelectedDateSocialPublicationReferencePreviewsProperty(): array
    {
        if (! $this->isActivityModalOpen || $this->modalWorkspace !== 'marketing') {
            return [];
        }

        $paths = $this->selectedDateSocialPublications
            ->flatMap(function (CorporateAgendaSocialPublication $publication): array {
                $attachments = $publication->attachments;

                return is_array($attachments) ? $attachments : [];
            })
            ->map(fn (mixed $path): string => trim((string) $path))
            ->filter(fn (string $path): bool => $path !== '')
            ->unique()
            ->values();

        return $paths
            ->map(function (string $path): ?array {
                $normalizedPath = ltrim($path, '/');
                if ($normalizedPath === '') {
                    return null;
                }

                $extension = Str::lower(pathinfo($normalizedPath, PATHINFO_EXTENSION));
                $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
                $isVideo = in_array($extension, ['mp4', 'webm', 'mov'], true);

                return [
                    'url' => $this->publicStorageUrlIfExists($normalizedPath) ?? '',
                    'name' => basename($normalizedPath),
                    'is_image' => $isImage,
                    'is_video' => $isVideo,
                    'ext' => $extension !== '' ? $extension : 'archivo',
                ];
            })
            ->filter(fn (?array $item): bool => $item !== null && ($item['url'] ?? '') !== '')
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $paths
     * @return array<int, array{url:string,name:string,is_image:bool,is_video:bool,ext:string}>
     */
    private function buildStoredAttachmentPreviews(array $paths): array
    {
        return collect($paths)
            ->map(function (mixed $path): ?array {
                $normalizedPath = ltrim(trim((string) $path), '/');
                if ($normalizedPath === '') {
                    return null;
                }

                $extension = Str::lower(pathinfo($normalizedPath, PATHINFO_EXTENSION));
                $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
                $isVideo = in_array($extension, ['mp4', 'webm', 'mov'], true);

                return [
                    'url' => $this->publicStorageUrlIfExists($normalizedPath) ?? '',
                    'name' => basename($normalizedPath),
                    'is_image' => $isImage,
                    'is_video' => $isVideo,
                    'ext' => $extension !== '' ? $extension : 'archivo',
                ];
            })
            ->filter(fn (?array $preview): bool => $preview !== null && ($preview['url'] ?? '') !== '')
            ->values()
            ->all();
    }

    private function publicStorageUrl(?string $path): ?string
    {
        $normalizedPath = ltrim(trim((string) $path), '/');
        if ($normalizedPath === '') {
            return null;
        }

        return url('storage/'.$normalizedPath);
    }

    private function publicStorageUrlIfExists(?string $path): ?string
    {
        $normalizedPath = ltrim(trim((string) $path), '/');
        if ($normalizedPath === '') {
            return null;
        }

        $exists = Cache::remember(
            'agenda-corporativa:storage-exists:'.md5($normalizedPath),
            now()->addMinutes(5),
            fn (): bool => Storage::disk('public')->exists($normalizedPath),
        );

        if (! $exists) {
            return null;
        }

        return $this->publicStorageUrl($normalizedPath);
    }

    /**
     * @param  array<int, string>  $platforms
     */
    private function syncSocialPublicationPlatformPayloads(array $platforms): void
    {
        $platformSet = collect($platforms)
            ->map(fn (string $platform): string => trim($platform))
            ->filter(fn (string $platform): bool => $platform !== '')
            ->values()
            ->all();

        $briefs = [];
        $existingAttachments = [];
        $uploads = [];

        foreach ($platformSet as $platform) {
            $briefs[$platform] = $this->socialPublicationBriefByPlatform[$platform] ?? null;
            $existingAttachments[$platform] = array_values($this->socialPublicationExistingAttachmentsByPlatform[$platform] ?? []);
            $uploads[$platform] = array_values($this->socialPublicationUploadsByPlatform[$platform] ?? []);
        }

        $this->socialPublicationBriefByPlatform = $briefs;
        $this->socialPublicationExistingAttachmentsByPlatform = $existingAttachments;
        $this->socialPublicationUploadsByPlatform = $uploads;
    }

    public function canCurrentUserEditSocialPublication(?CorporateAgendaSocialPublication $publication): bool
    {
        if ($publication === null) {
            return false;
        }

        if ($this->userIsSuperAdmin()) {
            return true;
        }

        return (int) $publication->creator_user_id === (int) Auth::id();
    }

    private function socialPublicationsBetween(Carbon $start, Carbon $end): Builder
    {
        return CorporateAgendaSocialPublication::query()
            ->whereDate('publication_date', '>=', $start->toDateString())
            ->whereDate('publication_date', '<=', $end->toDateString());
    }

    private function findSocialPublicationOrNull(int $publicationId): ?CorporateAgendaSocialPublication
    {
        return $this->socialPublicationsBetween(
            Carbon::create(2000, 1, 1),
            Carbon::create(2100, 1, 1),
        )
            ->with('creator:id,name')
            ->whereKey($publicationId)
            ->first();
    }

    private function resetSocialPublicationFormForSelectedDate(): void
    {
        $this->socialPublicationForm = [
            'publication_date' => $this->selectedDate,
            'platforms' => [],
            'brief' => null,
            'attachments' => [],
        ];
        $this->socialPublicationUploadsByPlatform = [];
        $this->socialPublicationExistingAttachmentsByPlatform = [];
        $this->socialPublicationBriefByPlatform = [];

        $this->fillSocialPublicationFormFromDate();
    }

    private function fillSocialPublicationFormFromDate(): void
    {
        $publications = $this->selectedDateSocialPublications;
        $firstPublication = $publications->first();

        $this->socialPublicationForm = [
            'publication_date' => $this->selectedDate,
            'platforms' => $this->resolveSocialPlatformsForPublications($publications),
            'brief' => $firstPublication?->brief,
            'attachments' => is_array($firstPublication?->attachments) ? array_values(array_map('strval', $firstPublication->attachments)) : [],
        ];

        $this->socialPublicationBriefByPlatform = $publications
            ->mapWithKeys(function (CorporateAgendaSocialPublication $publication): array {
                $platform = $publication->platform?->value ?? (string) $publication->getRawOriginal('platform');

                return [$platform => $publication->brief];
            })
            ->all();

        $this->socialPublicationExistingAttachmentsByPlatform = $publications
            ->mapWithKeys(function (CorporateAgendaSocialPublication $publication): array {
                $platform = $publication->platform?->value ?? (string) $publication->getRawOriginal('platform');
                $attachments = is_array($publication->attachments) ? array_values(array_map('strval', $publication->attachments)) : [];

                return [$platform => $attachments];
            })
            ->all();

        $this->socialPublicationUploadsByPlatform = [];
        $this->syncSocialPublicationPlatformPayloads($this->socialPublicationForm['platforms']);
    }
}
