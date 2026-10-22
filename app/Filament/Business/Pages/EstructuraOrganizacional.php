<?php

declare(strict_types=1);

namespace App\Filament\Business\Pages;

use App\Enums\OrgStructureVersionStatus;
use App\Models\OrgStructureEvent;
use App\Models\OrgStructureVersion;
use App\Models\User;
use App\Support\Filament\SharedNavigationGroups;
use App\Support\OrgStructure\OrgChartPayload;
use App\Support\OrgStructure\OrgStructureAccess;
use App\Support\OrgStructure\OrgStructureCache;
use App\Support\OrgStructure\OrgStructureComparator;
use App\Support\OrgStructure\OrgStructureConflictException;
use App\Support\OrgStructure\OrgStructureDemo;
use App\Support\OrgStructure\OrgStructureDrafts;
use App\Support\OrgStructure\OrgStructureEditLock;
use App\Support\OrgStructure\OrgStructureEditor;
use App\Support\OrgStructure\OrgStructureException;
use App\Support\OrgStructure\OrgStructurePublisher;
use App\Support\OrgStructure\OrgStructureRrhh;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Url;
use Throwable;
use UnitEnum;

/**
 * Estudio de la estructura organizacional (Negocios).
 *
 * Sin borrador abierto muestra la lista de borradores y el historial reciente; con
 * `?borrador=ID` abre el editor: esquema y lienzo sincronizados, guardado
 * automático, candado de edición y comparación contra la vigente.
 *
 * Las ediciones son llamadas `Renderless` que devuelven el árbol actualizado; las
 * decisiones (enviar, publicar, aprobar, devolver, descartar) son acciones de
 * Filament con su modal de confirmación.
 */
class EstructuraOrganizacional extends Page
{
    protected static ?string $navigationLabel = 'Estructura organizacional';

    protected static ?string $title = 'Estructura organizacional';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = SharedNavigationGroups::USER;

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'estructura-organizacional';

    protected string $view = 'filament.administration.pages.estructura-organizacional';

    #[Url(as: 'borrador')]
    public ?int $draftId = null;

    /** Organigrama de ejemplo que no se guarda (`?ejemplo=1`). */
    #[Url(as: 'ejemplo')]
    public bool $demo = false;

    private ?OrgStructureVersion $loadedDraft = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $publishPreviewCache = null;

    public static function canAccess(): bool
    {
        return OrgStructureAccess::canOpenStudio(self::currentUser());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        if ($this->draftId === null) {
            return;
        }

        $this->demo = false;

        $draft = $this->draft();

        if ($draft === null || in_array($draft->status, [OrgStructureVersionStatus::Discarded], true)) {
            $this->draftId = null;

            Notification::make()->title('Ese borrador ya no está disponible.')->warning()->send();

            return;
        }

        if ($draft->status->isHistorical()) {
            $this->redirect(Organigrama::getUrl(), navigate: true);

            return;
        }

        if ($draft->isEditable() && OrgStructureAccess::canEdit(self::currentUser())) {
            OrgStructureEditLock::acquire($draft->id, self::currentUser());
        }
    }

    public function getTitle(): string|Htmlable
    {
        if ($this->demo) {
            return 'Organigrama de ejemplo';
        }

        $draft = $this->draft();

        return $draft !== null ? $draft->name : static::$title;
    }

    public function getSubheading(): string|Htmlable|null
    {
        if ($this->demo) {
            return 'Prueba el estudio con libertad: nada de lo que hagas aquí se guarda.';
        }

        $draft = $this->draft();

        if ($draft === null) {
            return 'Planea cambios del organigrama en borradores sin tocar el vigente. Un SUPERADMIN publica directo; los demás envían a aprobación.';
        }

        $base = $draft->basedOn?->name;

        return $draft->status->label().($base !== null ? ' · sobre «'.$base.'»' : ' · carga inicial desde RRHH');
    }

    /**
     * @return array<string, mixed>
     */
    public function listData(): array
    {
        $current = OrgStructureCache::currentVersion();

        $open = OrgStructureVersion::query()
            ->whereIn('status', OrgStructureVersionStatus::openValues())
            ->with(['creator:id,name', 'submitter:id,name', 'basedOn:id,name'])
            ->orderByDesc('updated_at')
            ->limit(OrgStructureDrafts::MAX_OPEN_DRAFTS + 5)
            ->get();

        $closed = OrgStructureVersion::query()
            ->whereIn('status', [OrgStructureVersionStatus::Archived->value, OrgStructureVersionStatus::Discarded->value])
            ->with('creator:id,name')
            ->orderByDesc('updated_at')
            ->limit(15)
            ->get();

        $events = $current === null ? collect() : OrgStructureEvent::query()
            ->where('version_id', $current->id)
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'summary', 'user_name', 'created_at']);

        return [
            'current' => $current,
            'currentStats' => $current !== null ? OrgChartPayload::build($current)['stats'] : null,
            'open' => $open,
            'closed' => $closed,
            'events' => $events,
            'canEdit' => OrgStructureAccess::canEdit(self::currentUser()),
            'canReview' => OrgStructureAccess::canReview(self::currentUser()),
            'userId' => (int) self::currentUser()?->getKey(),
        ];
    }

    /**
     * Configuración inicial del editor: árbol completo, catálogos de RRHH y estado
     * del candado.
     *
     * @return array<string, mixed>
     */
    public function editorConfig(): array
    {
        $draft = $this->draft();
        $user = self::currentUser();

        if ($draft === null) {
            return [];
        }

        $holder = OrgStructureEditLock::holder($draft->id);
        $lockedByOther = $holder !== null && (int) $holder['user_id'] !== (int) $user?->getKey();
        $canEdit = $draft->isEditable() && OrgStructureAccess::canEdit($user) && ! $lockedByOther;

        return [
            'mode' => 'edit',
            'payload' => OrgChartPayload::compute($draft),
            'readOnly' => ! $canEdit,
            'readOnlyReason' => match (true) {
                ! $draft->isEditable() => 'Este borrador está «'.$draft->status->label().'» y no se puede editar.',
                ! OrgStructureAccess::canEdit($user) => 'Puedes revisar este borrador, pero no editarlo.',
                $lockedByOther => 'Lo está editando '.$holder['name'].'. Se abrió en modo lectura.',
                default => null,
            },
            'reviewNotes' => $draft->status === OrgStructureVersionStatus::Returned ? $draft->review_notes : null,
            'hasBase' => $draft->based_on_version_id !== null,
            'cargoOptions' => OrgStructureRrhh::cargoOptions(),
            'deptOptions' => collect(OrgStructureRrhh::departamentoNames())->map(fn (string $name, int $id): array => ['id' => $id, 'name' => $name])->values()->all(),
            'colors' => OrgStructureEditor::COLORS,
            'heartbeatMs' => OrgStructureEditLock::HEARTBEAT_SECONDS * 1000,
            'palette' => OrgStructureRrhh::paletteCatalog(),
            'publishAction' => match (true) {
                ! $canEdit => null,
                OrgStructureAccess::canPublishDirectly($user) => 'publish',
                default => 'submit',
            },
            'demo' => false,
            'canExport' => false,
            'canManageViews' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function demoConfig(): array
    {
        return OrgStructureDemo::config();
    }

    /**
     * @return array<string, mixed>
     */
    #[Renderless]
    public function reloadDraft(): array
    {
        $draft = $this->draft(fresh: true);

        if ($draft === null) {
            return ['ok' => false, 'message' => 'El borrador ya no existe.'];
        }

        return ['ok' => true, 'payload' => OrgChartPayload::compute($draft)];
    }

    /**
     * @return array<string, mixed>
     */
    #[Renderless]
    public function heartbeat(): array
    {
        $draft = $this->draft(fresh: true);
        $user = self::currentUser();

        if ($draft === null || $user === null || ! $draft->isEditable() || ! OrgStructureAccess::canEdit($user)) {
            return ['ok' => false, 'locked' => false];
        }

        $holder = OrgStructureEditLock::acquire($draft->id, $user);

        return $holder === null
            ? ['ok' => true, 'lock_version' => $draft->lock_version]
            : ['ok' => false, 'locked' => true, 'message' => 'Ahora lo está editando '.$holder['name'].'. El borrador quedó en modo lectura.'];
    }

    #[Renderless]
    public function releaseLock(): void
    {
        $user = self::currentUser();

        if ($this->draftId !== null && $user !== null) {
            OrgStructureEditLock::release($this->draftId, $user);
        }
    }

    /**
     * @return array<string, mixed>
     */
    #[Renderless]
    public function addPosition(int $lock, ?int $parentId, string $title, string $kind = 'LINEA', ?int $index = null, ?int $departamentoId = null): array
    {
        return $this->mutate(fn (User $user): array => OrgStructureEditor::addPosition($this->requireDraftId(), $user, $lock, $parentId, $title, $kind, null, $index, $departamentoId));
    }

    /**
     * @return array<string, mixed>
     */
    #[Renderless]
    public function addFromCargo(int $lock, ?int $parentId, int $cargoId, ?int $index = null): array
    {
        return $this->mutate(fn (User $user): array => OrgStructureEditor::addFromCargo($this->requireDraftId(), $user, $lock, $parentId, $cargoId, $index));
    }

    /**
     * Últimos movimientos de un puesto en este borrador, para «Más detalles».
     *
     * @return list<array{summary: string, user: string, at: string}>
     */
    #[Renderless]
    public function positionHistory(string $positionKey): array
    {
        if ($this->draftId === null || ! OrgStructureAccess::canOpenStudio(self::currentUser()) || preg_match('/^[0-9a-f-]{36}$/i', $positionKey) !== 1) {
            return [];
        }

        return OrgStructureEvent::query()
            ->where('version_id', $this->draftId)
            ->where('position_key', $positionKey)
            ->orderByDesc('id')
            ->limit(15)
            ->get(['summary', 'user_name', 'created_at'])
            ->map(fn (OrgStructureEvent $event): array => [
                'summary' => $event->summary,
                'user' => (string) $event->user_name,
                'at' => $event->created_at?->format('d/m/Y H:i') ?? '',
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    #[Renderless]
    public function updatePosition(int $lock, int $positionId, array $attributes): array
    {
        $allowed = array_intersect_key($attributes, array_flip(['title', 'kind', 'seats', 'color', 'rrhh_cargo_id', 'rrhh_departamento_id']));

        return $this->mutate(fn (User $user): array => ['lock_version' => OrgStructureEditor::updatePosition($this->requireDraftId(), $user, $lock, $positionId, $allowed)]);
    }

    /**
     * @return array<string, mixed>
     */
    #[Renderless]
    public function movePosition(int $lock, int $positionId, int $newParentId, int $index): array
    {
        return $this->mutate(fn (User $user): array => ['lock_version' => OrgStructureEditor::movePosition($this->requireDraftId(), $user, $lock, $positionId, $newParentId, $index)]);
    }

    /**
     * @return array<string, mixed>
     */
    #[Renderless]
    public function removePosition(int $lock, int $positionId, bool $releaseOccupants = false): array
    {
        return $this->mutate(fn (User $user): array => ['lock_version' => OrgStructureEditor::removePosition($this->requireDraftId(), $user, $lock, $positionId, $releaseOccupants)]);
    }

    /**
     * @return array<string, mixed>
     */
    #[Renderless]
    public function assignOccupant(int $lock, int $positionId, int $colaboradorId, string $role): array
    {
        return $this->mutate(fn (User $user): array => OrgStructureEditor::assign($this->requireDraftId(), $user, $lock, $positionId, $colaboradorId, $role));
    }

    /**
     * @return array<string, mixed>
     */
    #[Renderless]
    public function removeOccupant(int $lock, int $assignmentId): array
    {
        return $this->mutate(fn (User $user): array => ['lock_version' => OrgStructureEditor::unassign($this->requireDraftId(), $user, $lock, $assignmentId)]);
    }

    /**
     * @return array<string, mixed>
     */
    #[Renderless]
    public function removeInactiveOccupants(int $lock): array
    {
        return $this->mutate(fn (User $user): array => OrgStructureEditor::removeInactiveOccupants($this->requireDraftId(), $user, $lock));
    }

    /**
     * @return list<array{id: int, name: string, cargo: string|null, initials: string, avatar: string|null}>
     */
    #[Renderless]
    public function searchColaboradores(string $term): array
    {
        if (! OrgStructureAccess::canEdit(self::currentUser())) {
            return [];
        }

        return OrgStructureRrhh::searchActive(mb_substr($term, 0, 80));
    }

    /**
     * @return array<string, mixed>
     */
    #[Renderless]
    public function compareWithBase(): array
    {
        $draft = $this->draft(fresh: true);

        if ($draft === null || $draft->based_on_version_id === null) {
            return ['ok' => false, 'message' => 'Este borrador no tiene una versión de base para comparar.'];
        }

        $base = OrgStructureVersion::query()->find($draft->based_on_version_id);

        if ($base === null) {
            return ['ok' => false, 'message' => 'La versión de base ya no existe.'];
        }

        return [
            'ok' => true,
            'base_name' => $base->name,
            'diff' => OrgStructureComparator::compare(OrgChartPayload::build($base)['nodes'], OrgChartPayload::compute($draft)['nodes']),
        ];
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToList')
                ->label('Todos los borradores')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->visible(fn (): bool => $this->draftId !== null || $this->demo)
                ->url(fn (): string => static::getUrl()),
            Action::make('viewChart')
                ->label('Ver organigrama vigente')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->visible(fn (): bool => $this->draftId === null && ! $this->demo)
                ->url(fn (): string => Organigrama::getUrl()),
            Action::make('demo')
                ->label('Ver un ejemplo')
                ->icon(Heroicon::OutlinedAcademicCap)
                ->color('gray')
                ->visible(fn (): bool => $this->draftId === null && ! $this->demo)
                ->url(fn (): string => static::getUrl(['ejemplo' => 1])),
            $this->copyCurrentAction(),
            $this->newOrgChartAction(),
            $this->renameDraftAction(),
            $this->takeOverAction(),
            $this->discardDraftAction(),
            $this->rejectAction(),
            $this->submitAction(),
            $this->publishAction(),
            $this->approveAction(),
        ];
    }

    /**
     * Organigrama nuevo: un borrador vacío que se abre con un solo cuadro en el
     * lienzo para escribir el puesto principal.
     */
    public function newOrgChartAction(): Action
    {
        return Action::make('newOrgChart')
            ->label('Nuevo organigrama')
            ->icon(Heroicon::OutlinedPlus)
            ->color('primary')
            ->visible(fn (): bool => $this->draftId === null && ! $this->demo && OrgStructureAccess::canEdit(self::currentUser()))
            ->action(function (): void {
                $this->runAction(function (User $user): void {
                    $draft = OrgStructureDrafts::createEmpty($user, 'Organigrama '.now()->format('Y'));
                    $this->redirect(static::getUrl(['borrador' => $draft->id]), navigate: true);
                });
            });
    }

    public function copyCurrentAction(): Action
    {
        return Action::make('copyCurrent')
            ->label('Partir del vigente')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->visible(fn (): bool => $this->draftId === null && ! $this->demo && OrgStructureAccess::canEdit(self::currentUser()) && OrgStructureCache::currentVersionRef() !== null)
            ->modalHeading('Nuevo borrador desde el vigente')
            ->modalDescription('Se crea una copia exacta del organigrama vigente para reorganizarlo. Lo que cambies no se ve hasta publicarlo.')
            ->modalSubmitActionLabel('Crear borrador')
            ->schema([
                TextInput::make('name')
                    ->label('Nombre del borrador')
                    ->placeholder('Ej.: Reestructuración Q1 2027')
                    ->default('Borrador '.now()->format('d/m/Y'))
                    ->required()
                    ->minLength(3)
                    ->maxLength(120),
            ])
            ->action(function (array $data): void {
                $this->runAction(function (User $user) use ($data): void {
                    $draft = OrgStructureDrafts::createFromCurrent($user, (string) $data['name']);
                    $this->redirect(static::getUrl(['borrador' => $draft->id]), navigate: true);
                });
            });
    }

    public function renameDraftAction(): Action
    {
        return Action::make('renameDraft')
            ->label('Renombrar')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->visible(fn (): bool => $this->isEditableByMe())
            ->fillForm(fn (): array => ['name' => $this->draft()?->name, 'notes' => $this->draft()?->notes])
            ->modalHeading('Nombre y notas del borrador')
            ->modalSubmitActionLabel('Guardar')
            ->schema([
                TextInput::make('name')->label('Nombre')->required()->minLength(3)->maxLength(120),
                Textarea::make('notes')->label('Notas para quien lo revise')->rows(3)->maxLength(2000),
            ])
            ->action(function (array $data): void {
                $this->runAction(function (User $user) use ($data): void {
                    $draft = $this->draft(fresh: true);
                    OrgStructureEditor::rename($this->requireDraftId(), $user, (int) $draft?->lock_version, (string) $data['name'], $data['notes'] ?? null);
                    $this->reloadPage('Borrador actualizado.');
                });
            });
    }

    public function takeOverAction(): Action
    {
        return Action::make('takeOver')
            ->label('Tomar el control')
            ->icon(Heroicon::OutlinedLockOpen)
            ->color('warning')
            ->visible(function (): bool {
                $draft = $this->draft();
                $holder = $draft !== null ? OrgStructureEditLock::holder($draft->id) : null;

                return $draft !== null
                    && $draft->isEditable()
                    && OrgStructureAccess::canReview(self::currentUser())
                    && $holder !== null
                    && (int) $holder['user_id'] !== (int) self::currentUser()?->getKey();
            })
            ->requiresConfirmation()
            ->modalHeading('Tomar el control del borrador')
            ->modalDescription(fn (): string => 'Quien lo está editando ('.(OrgStructureEditLock::holder((int) $this->draftId)['name'] ?? 'otro usuario').') pasará a modo lectura y sus cambios sin guardar se perderán.')
            ->modalSubmitActionLabel('Tomar el control')
            ->action(function (): void {
                $user = self::currentUser();

                if ($user === null || ! OrgStructureAccess::canReview($user)) {
                    return;
                }

                OrgStructureEditLock::forceTakeover($this->requireDraftId(), $user);
                $this->reloadPage('Ahora editas tú este borrador.');
            });
    }

    public function discardDraftAction(): Action
    {
        return Action::make('discardDraft')
            ->label(fn (array $arguments): string => isset($arguments['draft']) ? 'Descartar' : 'Descartar borrador')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(function (array $arguments): bool {
                $draft = isset($arguments['draft']) ? OrgStructureVersion::query()->find((int) $arguments['draft']) : $this->draft();
                $user = self::currentUser();

                return $draft !== null
                    && $draft->isEditable()
                    && ((int) $draft->created_by === (int) $user?->getKey() || OrgStructureAccess::canReview($user));
            })
            ->requiresConfirmation()
            ->modalHeading('Descartar borrador')
            ->modalDescription('El borrador deja de estar en la lista de trabajo. No afecta al organigrama vigente y queda registrado en la bitácora.')
            ->modalSubmitActionLabel('Descartar')
            ->action(function (array $arguments): void {
                $versionId = isset($arguments['draft']) ? (int) $arguments['draft'] : $this->requireDraftId();

                $this->runAction(function (User $user) use ($versionId): void {
                    OrgStructureDrafts::discard($versionId, $user);
                    Notification::make()->title('Borrador descartado.')->success()->send();
                    $this->redirect(static::getUrl(), navigate: true);
                });
            });
    }

    public function submitAction(): Action
    {
        return Action::make('submit')
            ->label('Enviar a aprobación')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('warning')
            ->visible(fn (): bool => $this->isEditableByMe() && ! OrgStructureAccess::canPublishDirectly(self::currentUser()))
            ->modalHeading('Enviar a aprobación')
            ->modalDescription('Un SUPERADMIN lo revisará y lo aprobará o te lo devolverá con comentarios. Mientras tanto no se puede editar.')
            ->schema(fn (): array => [$this->previewPlaceholder(includeRrhh: false)])
            ->modalSubmitAction(fn (Action $action): Action => $action->label('Enviar')->disabled($this->publishPreview()['errors'] !== [] || $this->publishPreview()['base_changed']))
            ->action(function (): void {
                $this->runAction(function (User $user): void {
                    OrgStructurePublisher::submitForReview($this->requireDraftId(), $user);
                    Notification::make()->title('Borrador enviado a aprobación')->body('Avisamos a los SUPERADMIN. Te notificaremos cuando lo revisen.')->success()->send();
                    $this->redirect(static::getUrl(), navigate: true);
                });
            });
    }

    public function publishAction(): Action
    {
        return Action::make('publish')
            ->label('Publicar')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn (): bool => $this->isEditableByMe() && OrgStructureAccess::canPublishDirectly(self::currentUser()))
            ->modalHeading('Publicar organigrama')
            ->modalDescription('Como SUPERADMIN, este borrador pasa a ser el organigrama vigente sin aprobación. La versión actual queda en el historial.')
            ->schema(fn (): array => $this->publishSchema())
            ->modalSubmitAction(fn (Action $action): Action => $action->label('Publicar ahora')->disabled($this->publishPreview()['errors'] !== [] || $this->publishPreview()['base_changed']))
            ->action(function (array $data): void {
                $this->runAction(function (User $user) use ($data): void {
                    $result = OrgStructurePublisher::publishDirectly($this->requireDraftId(), $user, (bool) ($data['sync_rrhh'] ?? false));
                    $this->notifyPublished($result['rrhh_updated']);
                });
            });
    }

    public function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Aprobar y publicar')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn (): bool => $this->draft()?->status === OrgStructureVersionStatus::InReview
                && OrgStructureAccess::canReview(self::currentUser())
                && (int) $this->draft()?->submitted_by !== (int) self::currentUser()?->getKey())
            ->modalHeading('Aprobar y publicar')
            ->modalDescription(fn (): string => 'Enviado por '.($this->draft()?->submitter?->name ?? 'otro usuario').'. Al aprobarlo pasa a ser el organigrama vigente.')
            ->schema(fn (): array => $this->publishSchema())
            ->modalSubmitAction(fn (Action $action): Action => $action->label('Aprobar y publicar')->disabled($this->publishPreview()['errors'] !== [] || $this->publishPreview()['base_changed']))
            ->action(function (array $data): void {
                $this->runAction(function (User $user) use ($data): void {
                    $result = OrgStructurePublisher::approve($this->requireDraftId(), $user, (bool) ($data['sync_rrhh'] ?? false));
                    $this->notifyPublished($result['rrhh_updated']);
                });
            });
    }

    public function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Devolver')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->visible(fn (): bool => $this->draft()?->status === OrgStructureVersionStatus::InReview && OrgStructureAccess::canReview(self::currentUser()))
            ->modalHeading('Devolver para corregir')
            ->modalDescription('Quien lo envió recibirá tu comentario y podrá corregir y reenviar.')
            ->modalSubmitActionLabel('Devolver')
            ->schema([
                Textarea::make('reason')
                    ->label('¿Qué hay que corregir?')
                    ->required()
                    ->minLength(10)
                    ->maxLength(2000)
                    ->rows(4),
            ])
            ->action(function (array $data): void {
                $this->runAction(function (User $user) use ($data): void {
                    OrgStructurePublisher::reject($this->requireDraftId(), $user, (string) $data['reason']);
                    Notification::make()->title('Borrador devuelto')->body('Avisamos a quien lo envió.')->success()->send();
                    $this->redirect(static::getUrl(), navigate: true);
                });
            });
    }

    /**
     * @return array<\Filament\Schemas\Components\Component|\Filament\Forms\Components\Field>
     */
    private function publishSchema(): array
    {
        $preview = $this->publishPreview();
        $changes = $preview['rrhh']['changes'];

        return [
            $this->previewPlaceholder(includeRrhh: true),
            Toggle::make('sync_rrhh')
                ->label('Actualizar también el cargo en RRHH de '.count($changes).' persona(s)')
                ->helperText('Solo cambia el cargo y departamento de quienes aparecen en la lista de arriba. Si lo dejas apagado, RRHH queda como está.')
                ->default(false)
                ->visible($changes !== []),
        ];
    }

    private function previewPlaceholder(bool $includeRrhh): Placeholder
    {
        return Placeholder::make('preview')
            ->hiddenLabel()
            ->content(fn (): HtmlString => new HtmlString(view('filament.administration.pages.partials.org-structure-publish-preview', [
                'preview' => $this->publishPreview(),
                'includeRrhh' => $includeRrhh,
            ])->render()));
    }

    /**
     * @return array{errors: list<string>, warnings: list<string>, rrhh: array{changes: list<array<string, mixed>>, skipped: list<string>}, base_changed: bool}
     */
    private function publishPreview(): array
    {
        if ($this->publishPreviewCache !== null) {
            return $this->publishPreviewCache;
        }

        $draft = $this->draft(fresh: true);

        return $this->publishPreviewCache = $draft === null
            ? ['errors' => ['El borrador ya no existe.'], 'warnings' => [], 'rrhh' => ['changes' => [], 'skipped' => []], 'base_changed' => false]
            : OrgStructurePublisher::preview($draft);
    }

    private function notifyPublished(int $rrhhUpdated): void
    {
        Notification::make()
            ->title('Organigrama publicado')
            ->body('Ya es el organigrama vigente.'.($rrhhUpdated > 0 ? ' Se actualizó el cargo de '.$rrhhUpdated.' persona(s) en RRHH.' : ''))
            ->success()
            ->send();

        $this->redirect(Organigrama::getUrl(), navigate: true);
    }

    /**
     * @param  Closure(User): array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function mutate(Closure $operation): array
    {
        $user = self::currentUser();

        if ($user === null) {
            return ['ok' => false, 'message' => 'Tu sesión expiró. Vuelve a iniciar sesión.'];
        }

        try {
            $result = $operation($user);

            return ['ok' => true, ...$result, 'payload' => $this->freshPayload()];
        } catch (OrgStructureConflictException $exception) {
            return ['ok' => false, 'conflict' => true, 'message' => $exception->getMessage(), 'payload' => $this->freshPayload()];
        } catch (OrgStructureException $exception) {
            return ['ok' => false, 'message' => $exception->getMessage()];
        } catch (Throwable $exception) {
            report($exception);

            return ['ok' => false, 'message' => 'No se pudo guardar el cambio. Intenta de nuevo; si persiste, recarga la página.'];
        }
    }

    /**
     * @param  Closure(User): void  $operation
     */
    private function runAction(Closure $operation): void
    {
        $user = self::currentUser();

        if ($user === null) {
            return;
        }

        try {
            $operation($user);
        } catch (OrgStructureException $exception) {
            Notification::make()->title('No se pudo completar')->body($exception->getMessage())->danger()->persistent()->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title('Ocurrió un error inesperado')->body('No se aplicó ningún cambio. Intenta de nuevo.')->danger()->send();
        }
    }

    private function reloadPage(string $message): void
    {
        Notification::make()->title($message)->success()->send();
        $this->redirect(static::getUrl(['borrador' => $this->draftId]), navigate: true);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function freshPayload(): ?array
    {
        $draft = $this->draft(fresh: true);

        return $draft === null ? null : OrgChartPayload::compute($draft);
    }

    private function isEditableByMe(): bool
    {
        $draft = $this->draft();
        $user = self::currentUser();

        if ($draft === null || ! $draft->isEditable() || ! OrgStructureAccess::canEdit($user)) {
            return false;
        }

        $holder = OrgStructureEditLock::holder($draft->id);

        return $holder === null || (int) $holder['user_id'] === (int) $user?->getKey();
    }

    private function draft(bool $fresh = false): ?OrgStructureVersion
    {
        if ($this->draftId === null) {
            return null;
        }

        if ($fresh || $this->loadedDraft === null || $this->loadedDraft->id !== $this->draftId) {
            $this->loadedDraft = OrgStructureVersion::query()
                ->with(['basedOn:id,name', 'submitter:id,name'])
                ->find($this->draftId);
        }

        return $this->loadedDraft;
    }

    private function requireDraftId(): int
    {
        if ($this->draftId === null) {
            throw new OrgStructureException('No hay un borrador abierto.');
        }

        return $this->draftId;
    }

    private static function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
