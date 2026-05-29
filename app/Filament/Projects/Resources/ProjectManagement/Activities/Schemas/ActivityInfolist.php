<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Activities\Schemas;

use App\Models\ProjectManagement\Activity;
use App\Models\ProjectManagement\Group;
use App\Models\RrhhColaborador;
use App\Support\Filament\ProjectManagement\ProjectManagementActivityInfolistDisplay;
use App\Support\Filament\ProjectManagement\ProjectManagementActivityTable;
use App\Support\Filament\ProjectManagement\ProjectManagementCollaboratorSelect;
use App\Support\Filament\ProjectManagement\ProjectManagementFilamentSchemas;
use App\Support\Filament\ProjectManagement\ProjectManagementGroupMembers;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View as ViewFactory;
use Illuminate\Support\HtmlString;

class ActivityInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return ProjectManagementFilamentSchemas::tabbed($schema, 'activityInfolistTabs', [
            Tab::make('Actividad')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->schema([
                    ProjectManagementFilamentSchemas::section(
                        'Tarea',
                        'Título, estatus y prioridad.',
                        'heroicon-o-clipboard-document-check',
                    )->schema([
                        ProjectManagementFilamentSchemas::innerGrid([
                            TextEntry::make('title')
                                ->label('Título')
                                ->badge()
                                ->color('primary')
                                ->columnSpanFull(),
                            TextEntry::make('status')
                                ->label('Estatus')
                                ->badge()
                                ->formatStateUsing(fn (string $state): string => match ($state) {
                                    'todo' => 'Por hacer',
                                    'in_progress' => 'En progreso',
                                    'review' => 'En revisión',
                                    'done' => 'Finalizada',
                                    default => $state,
                                })
                                ->color(fn (string $state): string => match ($state) {
                                    'todo' => 'gray',
                                    'in_progress' => 'info',
                                    'review' => 'warning',
                                    'done' => 'success',
                                    default => 'gray',
                                }),
                            TextEntry::make('priority')
                                ->label('Prioridad')
                                ->badge()
                                ->formatStateUsing(fn (string $state): string => match ($state) {
                                    'low' => 'Baja',
                                    'medium' => 'Media',
                                    'high' => 'Alta',
                                    default => $state,
                                })
                                ->color(fn (string $state): string => match ($state) {
                                    'low' => 'gray',
                                    'medium' => 'warning',
                                    'high' => 'danger',
                                    default => 'gray',
                                }),
                            TextEntry::make('color')
                                ->label('Color de actividad')
                                ->state(fn (Activity $record): string => ProjectManagementActivityTable::resolveColor($record))
                                ->badge()
                                ->copyable()
                                ->columnSpanFull(),
                        ], ['default' => 1, 'lg' => 3]),
                    ]),
                    ProjectManagementFilamentSchemas::section(
                        'Descripción',
                        'Alcance y detalle de la tarea.',
                        'heroicon-o-document-text',
                    )->schema([
                        TextEntry::make('description_highlight')
                            ->hiddenLabel()
                            ->html()
                            ->state(function (Activity $record): HtmlString {
                                $payload = ProjectManagementActivityInfolistDisplay::descriptionPayload($record);

                                return new HtmlString(
                                    ViewFactory::make('filament.projects.infolists.activity-description-highlight', $payload)->render(),
                                );
                            })
                            ->columnSpanFull(),
                    ]),
                ]),
            Tab::make('Asignación')
                ->icon(Heroicon::OutlinedLink)
                ->schema([
                    ProjectManagementFilamentSchemas::section(
                        'Contexto',
                        'Proyecto y subproyecto asociados.',
                        'heroicon-o-link',
                    )->schema([
                        ProjectManagementFilamentSchemas::innerGrid([
                            TextEntry::make('project.name')
                                ->label('Proyecto')
                                ->badge()
                                ->color('info'),
                            TextEntry::make('subproject.name')
                                ->label('Subproyecto')
                                ->badge()
                                ->color('info')
                                ->placeholder('—'),
                        ], ['default' => 1, 'lg' => 2]),
                    ]),
                    ProjectManagementFilamentSchemas::section(
                        'Ejecutor',
                        'Equipo o colaboradores responsables de la ejecución.',
                        'heroicon-o-user-circle',
                    )->schema([
                        ProjectManagementFilamentSchemas::innerGrid([
                            TextEntry::make('assignment_type')
                                ->label('Tipo de asignación')
                                ->badge()
                                ->formatStateUsing(fn (string $state): string => match ($state) {
                                    'team' => 'Equipo',
                                    default => 'Colaborador(es)',
                                })
                                ->color(fn (string $state): string => $state === 'team' ? 'info' : 'success'),
                            TextEntry::make('assigned_team_name')
                                ->label('Equipo asignado')
                                ->state(fn (Activity $record): ?string => self::resolveTeam($record)?->name)
                                ->visible(fn (Activity $record): bool => ($record->assignment_type ?? 'collaborator') === 'team')
                                ->badge()
                                ->color('info')
                                ->placeholder('—'),
                            TextEntry::make('assigned_team_size')
                                ->label('Total de integrantes')
                                ->state(fn (Activity $record): int => self::resolveTeamMemberNames($record)->count())
                                ->visible(fn (Activity $record): bool => ($record->assignment_type ?? 'collaborator') === 'team')
                                ->badge()
                                ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray'),
                            TextEntry::make('assigned_team_members')
                                ->label('Integrantes del equipo')
                                ->state(fn (Activity $record): array => self::resolveTeamMemberNames($record)->all())
                                ->visible(fn (Activity $record): bool => ($record->assignment_type ?? 'collaborator') === 'team')
                                ->listWithLineBreaks()
                                ->bulleted()
                                ->placeholder('El equipo no tiene integrantes asignados')
                                ->columnSpanFull(),
                            TextEntry::make('assigned_collaborators')
                                ->label('Colaboradores asignados')
                                ->state(fn (Activity $record): array => self::resolveCollaboratorNames($record)->all())
                                ->visible(fn (Activity $record): bool => ($record->assignment_type ?? 'collaborator') !== 'team')
                                ->listWithLineBreaks()
                                ->bulleted()
                                ->placeholder('Sin colaboradores asignados')
                                ->columnSpanFull(),
                        ], ['default' => 1, 'lg' => 2]),
                    ]),
                ]),
            Tab::make('Fechas')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->schema([
                    ProjectManagementFilamentSchemas::section(
                        'Planificación',
                        'Fecha límite de la actividad.',
                        'heroicon-o-calendar-days',
                    )->schema([
                        ProjectManagementFilamentSchemas::innerGrid([
                            TextEntry::make('due_date')
                                ->label('Fecha límite')
                                ->date()
                                ->placeholder('—'),
                        ]),
                    ]),
                ]),
            Tab::make('Descripción')
                ->icon(Heroicon::OutlinedDocumentText)
                ->schema([
                    ProjectManagementFilamentSchemas::section(
                        'Detalle',
                        'Alcance descriptivo de la actividad.',
                        'heroicon-o-document-text',
                    )->schema([
                        TextEntry::make('description_detail')
                            ->hiddenLabel()
                            ->html()
                            ->state(function (Activity $record): HtmlString {
                                $payload = ProjectManagementActivityInfolistDisplay::descriptionPayload($record);

                                return new HtmlString(
                                    ViewFactory::make('filament.projects.infolists.activity-description-highlight', $payload)->render(),
                                );
                            })
                            ->columnSpanFull(),
                    ]),
                ]),
            Tab::make('Bitácora')
                ->icon(Heroicon::OutlinedBookOpen)
                ->schema([
                    ProjectManagementFilamentSchemas::section(
                        'Bitácora de notas',
                        'Historial detallado de seguimiento interno. De la más reciente a la más antigua.',
                        'heroicon-o-chat-bubble-left-right',
                    )->schema([
                        TextEntry::make('notes_journal')
                            ->hiddenLabel()
                            ->html()
                            ->state(function (Activity $record): HtmlString {
                                $payload = ProjectManagementActivityInfolistDisplay::notesJournalPayload($record);

                                return new HtmlString(
                                    ViewFactory::make('filament.projects.infolists.activity-notes-bitacora', $payload)->render(),
                                );
                            })
                            ->columnSpanFull(),
                    ]),
                ]),
            Tab::make('Documentos')
                ->icon(Heroicon::OutlinedFolderOpen)
                ->schema([
                    ProjectManagementFilamentSchemas::section(
                        'Expediente documental',
                        'Listado de archivos cargados con metadatos, responsable y descarga directa.',
                        'heroicon-o-folder-open',
                    )->schema([
                        TextEntry::make('documents_list')
                            ->hiddenLabel()
                            ->html()
                            ->state(function (Activity $record): HtmlString {
                                $payload = ProjectManagementActivityInfolistDisplay::documentsPayload($record);

                                return new HtmlString(
                                    ViewFactory::make('filament.projects.infolists.activity-documents-list', $payload)->render(),
                                );
                            })
                            ->columnSpanFull(),
                    ]),
                ]),
            Tab::make('Auditoría')
                ->icon(Heroicon::OutlinedClock)
                ->schema([
                    ProjectManagementFilamentSchemas::section(
                        'Registro',
                        'Fechas de creación y última actualización.',
                        'heroicon-o-clock',
                    )->schema([
                        ProjectManagementFilamentSchemas::innerGrid([
                            TextEntry::make('created_at')
                                ->label('Creado')
                                ->dateTime(),
                            TextEntry::make('updated_at')
                                ->label('Actualizado')
                                ->dateTime(),
                        ], ['default' => 1, 'lg' => 2]),
                    ]),
                ]),
        ]);
    }

    private static function resolveTeam(Activity $record): ?Group
    {
        if (! ProjectManagementGroupMembers::isTeamActivity($record)) {
            return null;
        }

        return ProjectManagementGroupMembers::resolveGroupForActivity($record);
    }

    /**
     * @return Collection<int, string>
     */
    private static function resolveTeamMemberNames(Activity $record): Collection
    {
        $group = self::resolveTeam($record);

        if ($group === null) {
            return collect();
        }

        $memberIds = ProjectManagementGroupMembers::memberIdsForActivity($record, $group);

        if ($memberIds === []) {
            return collect();
        }

        return RrhhColaborador::query()
            ->find($memberIds)
            ->filter(fn (RrhhColaborador $collaborator): bool => $collaborator->fullName !== ProjectManagementCollaboratorSelect::EXCLUDED_COLLABORATOR_NAME)
            ->sortBy(fn (RrhhColaborador $collaborator): string => (string) $collaborator->fullName)
            ->pluck('fullName')
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    private static function resolveCollaboratorNames(Activity $record): Collection
    {
        $memberIds = collect($record->assigned_collaborator_ids ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($memberIds === [] && $record->executor_type === RrhhColaborador::class && filled($record->executor_id)) {
            $memberIds = [(int) $record->executor_id];
        }

        if ($memberIds === []) {
            return collect();
        }

        return RrhhColaborador::query()
            ->find($memberIds)
            ->filter(fn (RrhhColaborador $collaborator): bool => $collaborator->fullName !== ProjectManagementCollaboratorSelect::EXCLUDED_COLLABORATOR_NAME)
            ->sortBy(fn (RrhhColaborador $collaborator): string => (string) $collaborator->fullName)
            ->pluck('fullName')
            ->values();
    }
}
