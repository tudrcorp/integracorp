<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Activities\Schemas;

use App\Models\ProjectManagement\Department;
use App\Models\ProjectManagement\Epic;
use App\Models\ProjectManagement\Group;
use App\Models\ProjectManagement\Sprint;
use App\Support\Filament\ProjectManagement\ProjectManagementActivityAppearance;
use App\Support\Filament\ProjectManagement\ProjectManagementCollaboratorSelect;
use App\Support\Filament\ProjectManagement\ProjectManagementFilamentSchemas;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ActivityForm
{
    public static function configure(Schema $schema): Schema
    {
        return ProjectManagementFilamentSchemas::tabbed($schema, 'activityFormTabs', [
            Tab::make('Actividad')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->schema([
                    ProjectManagementFilamentSchemas::section(
                        'Tarea',
                        'Título, estatus y prioridad de la actividad.',
                        'heroicon-o-clipboard-document-check',
                    )->schema([
                        ProjectManagementFilamentSchemas::innerGrid([
                            TextInput::make('title')
                                ->label('Título')
                                ->prefixIcon('heroicon-m-clipboard-document-check')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),
                            Select::make('status')
                                ->label('Estatus')
                                ->prefixIcon('heroicon-m-signal')
                                ->options([
                                    'todo' => 'Por hacer',
                                    'in_progress' => 'En progreso',
                                    'review' => 'En revisión',
                                    'done' => 'Finalizada',
                                ])
                                ->default('todo')
                                ->required(),
                            Select::make('priority')
                                ->label('Prioridad')
                                ->prefixIcon('heroicon-m-flag')
                                ->options([
                                    'low' => 'Baja',
                                    'medium' => 'Media',
                                    'high' => 'Alta',
                                ])
                                ->default('medium')
                                ->required(),
                        ], ['default' => 1, 'lg' => 2]),
                    ]),
                    ProjectManagementFilamentSchemas::section(
                        'Identidad visual',
                        'Color para distinguir la actividad en tablas y en el tablero Kanban.',
                        'heroicon-o-swatch',
                    )->schema([
                        ProjectManagementFilamentSchemas::innerGrid([
                            ToggleButtons::make('color')
                                ->label('Color rápido')
                                ->options(ProjectManagementActivityAppearance::colorPresets())
                                ->inline()
                                ->live()
                                ->default(ProjectManagementActivityAppearance::DEFAULT_COLOR)
                                ->columnSpanFull(),
                            ColorPicker::make('color')
                                ->label('Color de la actividad')
                                ->helperText('Este color se muestra en el Kanban y en el listado de actividades.')
                                ->hex()
                                ->default(ProjectManagementActivityAppearance::DEFAULT_COLOR)
                                ->required(),
                        ], ['default' => 1, 'lg' => 2]),
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
                            Select::make('project_id')
                                ->label('Proyecto')
                                ->prefixIcon('heroicon-m-folder')
                                ->relationship('project', 'name')
                                ->searchable()
                                ->preload()
                                ->live()
                                ->required(),
                            Select::make('subproject_id')
                                ->label('Subproyecto')
                                ->prefixIcon('heroicon-m-rectangle-stack')
                                ->relationship('subproject', 'name')
                                ->searchable()
                                ->preload(),
                        ], ['default' => 1, 'lg' => 2]),
                    ]),
                    ProjectManagementFilamentSchemas::section(
                        'Scrum',
                        'Épica, sprint, estimación y criterios de aceptación.',
                        'heroicon-o-rocket-launch',
                    )->schema([
                        ProjectManagementFilamentSchemas::innerGrid([
                            Select::make('epic_id')
                                ->label('Épica')
                                ->prefixIcon('heroicon-m-bookmark-square')
                                ->options(function (Get $get): array {
                                    $projectId = $get('project_id');

                                    if (! filled($projectId)) {
                                        return [];
                                    }

                                    return Epic::query()
                                        ->where('project_id', $projectId)
                                        ->orderBy('order')
                                        ->pluck('name', 'id')
                                        ->all();
                                })
                                ->searchable()
                                ->preload(),
                            Select::make('sprint_id')
                                ->label('Sprint')
                                ->prefixIcon('heroicon-m-rocket-launch')
                                ->helperText('Vacío = Product Backlog.')
                                ->options(function (Get $get): array {
                                    $projectId = $get('project_id');

                                    if (! filled($projectId)) {
                                        return [];
                                    }

                                    return Sprint::query()
                                        ->where('project_id', $projectId)
                                        ->orderByDesc('starts_at')
                                        ->pluck('name', 'id')
                                        ->all();
                                })
                                ->searchable()
                                ->preload(),
                            TextInput::make('story_points')
                                ->label('Story points')
                                ->prefixIcon('heroicon-m-hashtag')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100),
                            TextInput::make('backlog_order')
                                ->label('Orden backlog')
                                ->prefixIcon('heroicon-m-bars-3')
                                ->numeric()
                                ->minValue(0)
                                ->helperText('También puedes reordenar desde Backlog.'),
                            Textarea::make('acceptance_criteria')
                                ->label('Criterios de aceptación')
                                ->rows(4)
                                ->columnSpanFull(),
                        ], ['default' => 1, 'lg' => 2]),
                    ]),
                    ProjectManagementFilamentSchemas::section(
                        'Responsable de ejecución',
                        'Asigna la actividad a colaboradores, a un equipo o a un departamento.',
                        'heroicon-o-user-circle',
                    )->schema([
                        ProjectManagementFilamentSchemas::innerGrid([
                            ToggleButtons::make('assignment_type')
                                ->label('¿Quién ejecutará la actividad?')
                                ->options([
                                    'collaborator' => 'Colaborador(es)',
                                    'team' => 'Equipo',
                                    'department' => 'Departamento',
                                ])
                                ->icons([
                                    'collaborator' => Heroicon::OutlinedUser,
                                    'team' => Heroicon::OutlinedUserGroup,
                                    'department' => Heroicon::OutlinedBuildingOffice2,
                                ])
                                ->inline()
                                ->live()
                                ->default('collaborator')
                                ->required()
                                ->columnSpanFull(),
                            ProjectManagementCollaboratorSelect::make('assigned_collaborator_ids', 'Colaboradores asignados')
                                ->visible(fn (Get $get): bool => $get('assignment_type') === 'collaborator')
                                ->required(fn (Get $get): bool => $get('assignment_type') === 'collaborator')
                                ->columnSpanFull(),
                            Select::make('executor_department_id')
                                ->label('Departamento')
                                ->prefixIcon('heroicon-m-building-office-2')
                                ->options(fn (): array => Department::query()
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all())
                                ->searchable()
                                ->preload()
                                ->visible(fn (Get $get): bool => $get('assignment_type') === 'department')
                                ->required(fn (Get $get): bool => $get('assignment_type') === 'department')
                                ->columnSpanFull(),
                            Select::make('executor_group_id')
                                ->label('Equipo')
                                ->prefixIcon('heroicon-m-user-group')
                                ->options(fn (): array => Group::query()
                                    ->orderBy('name', 'asc')
                                    ->pluck('name', 'id')
                                    ->all())
                                ->searchable()
                                ->preload()
                                ->visible(fn (Get $get): bool => $get('assignment_type') === 'team')
                                ->required(fn (Get $get): bool => $get('assignment_type') === 'team')
                                ->belowContent(
                                    Action::make('create_team_from_activity')
                                        ->label('Crear equipo')
                                        ->icon('heroicon-o-plus')
                                        ->color('success')
                                        ->modalHeading('Nuevo equipo')
                                        ->modalDescription('Registra un equipo y asígnalo de inmediato a esta actividad.')
                                        ->modalSubmitActionLabel('Crear y asignar')
                                        ->form([
                                            TextInput::make('name')
                                                ->label('Nombre del equipo')
                                                ->required()
                                                ->maxLength(255),
                                            Textarea::make('description')
                                                ->label('Descripción')
                                                ->rows(3),
                                            ProjectManagementCollaboratorSelect::make('collaborator_ids', 'Integrantes del equipo'),
                                        ])
                                        ->action(function (array $data, Set $set): void {
                                            $group = Group::query()->create([
                                                'name' => $data['name'],
                                                'description' => $data['description'] ?? null,
                                                'collaborator_ids' => collect($data['collaborator_ids'] ?? [])
                                                    ->map(fn (mixed $id): int => (int) $id)
                                                    ->filter(fn (int $id): bool => $id > 0)
                                                    ->unique()
                                                    ->values()
                                                    ->all(),
                                            ]);

                                            $set('assignment_type', 'team');
                                            $set('executor_group_id', $group->id);
                                        }),
                                )
                                ->columnSpanFull(),
                        ]),
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
                            DatePicker::make('due_date')
                                ->label('Fecha límite')
                                ->native(false),
                        ]),
                    ]),
                ]),
            Tab::make('Descripción')
                ->icon(Heroicon::OutlinedDocumentText)
                ->schema([
                    ProjectManagementFilamentSchemas::section(
                        'Detalle',
                        'Notas y alcance de la actividad.',
                        'heroicon-o-document-text',
                    )->schema([
                        ProjectManagementFilamentSchemas::innerGrid([
                            Textarea::make('description')
                                ->label('Descripción')
                                ->rows(6)
                                ->columnSpanFull(),
                        ]),
                    ]),
                ]),
        ]);
    }
}
