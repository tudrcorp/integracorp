<?php

declare(strict_types=1);

namespace App\Filament\Projects\Pages;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Projects\Resources\ProjectManagement\Activities\ActivityResource;
use App\Models\ProjectManagement\Activity;
use App\Models\ProjectManagement\Epic;
use App\Models\ProjectManagement\Project;
use App\Support\Filament\FilamentIosButton;
use App\Support\Filament\ProjectManagement\ProjectManagementActivityAppearance;
use App\Support\ProjectManagement\BacklogOrdering;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class Backlog extends Page implements HasTable
{
    use AuthorizesDepartmentNavigation;
    use Tables\Concerns\InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static string|UnitEnum|null $navigationGroup = 'GESTION DE PROYECTOS';

    protected static ?string $navigationLabel = 'Backlog';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Product Backlog';

    protected string $view = 'filament.projects.pages.backlog';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Activity::query()
                    ->whereNull('sprint_id')
                    ->with(['project:id,name', 'epic:id,name'])
                    ->orderByRaw('CASE WHEN backlog_order IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('backlog_order')
                    ->orderByDesc('priority'),
            )
            ->heading('Product Backlog')
            ->description('Prioriza historias sin sprint. El orden alimenta el planning del siguiente sprint.')
            ->emptyStateHeading('Backlog vacío')
            ->emptyStateDescription('Crea una historia o deja historias sin sprint para priorizarlas aquí.')
            ->emptyStateIcon('heroicon-o-queue-list')
            ->defaultPaginationPageOption(25)
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('backlog_order')
                    ->label('#')
                    ->alignCenter()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Historia')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->url(fn (Activity $record): string => ActivityResource::getUrl('view', ['record' => $record], panel: 'projects')),
                TextColumn::make('project.name')
                    ->label('Proyecto')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                TextColumn::make('epic.name')
                    ->label('Épica')
                    ->badge()
                    ->color('primary')
                    ->placeholder('—'),
                TextColumn::make('story_points')
                    ->label('Pts')
                    ->badge()
                    ->color('warning')
                    ->alignCenter()
                    ->placeholder('—'),
                TextColumn::make('priority')
                    ->label('Prioridad')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'high' => 'Alta',
                        'medium' => 'Media',
                        'low' => 'Baja',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'high' => 'danger',
                        'medium' => 'warning',
                        'low' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'todo' => 'Por hacer',
                        'in_progress' => 'En progreso',
                        'review' => 'En revisión',
                        'done' => 'Finalizada',
                        default => $state,
                    }),
            ])
            ->filters([
                SelectFilter::make('project_id')
                    ->label('Proyecto')
                    ->options(fn (): array => Project::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload(),
                SelectFilter::make('epic_id')
                    ->label('Épica')
                    ->options(fn (): array => Epic::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('moveUp')
                    ->label('Subir')
                    ->icon('heroicon-m-arrow-up')
                    ->color('gray')
                    ->action(function (Activity $record): void {
                        (new BacklogOrdering)->moveUp($record);
                        Notification::make()->title('Orden actualizado')->success()->send();
                    }),
                Action::make('moveDown')
                    ->label('Bajar')
                    ->icon('heroicon-m-arrow-down')
                    ->color('gray')
                    ->action(function (Activity $record): void {
                        (new BacklogOrdering)->moveDown($record);
                        Notification::make()->title('Orden actualizado')->success()->send();
                    }),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createBacklogItem')
                ->label('Nueva historia')
                ->icon('heroicon-s-plus')
                ->color('primary')
                ->modalHeading('Nueva historia en backlog')
                ->modalSubmitActionLabel('Crear')
                ->form([
                    Select::make('project_id')
                        ->label('Proyecto')
                        ->options(fn (): array => Project::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live(),
                    Select::make('epic_id')
                        ->label('Épica')
                        ->options(function (callable $get): array {
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
                    TextInput::make('title')
                        ->label('Título')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('story_points')
                        ->label('Story points')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100),
                    Select::make('priority')
                        ->label('Prioridad')
                        ->options([
                            'low' => 'Baja',
                            'medium' => 'Media',
                            'high' => 'Alta',
                        ])
                        ->default('medium')
                        ->required(),
                    Textarea::make('acceptance_criteria')
                        ->label('Criterios de aceptación')
                        ->rows(4),
                ])
                ->action(function (array $data): void {
                    Activity::query()->create([
                        'project_id' => $data['project_id'],
                        'epic_id' => $data['epic_id'] ?? null,
                        'title' => $data['title'],
                        'story_points' => $data['story_points'] ?? null,
                        'priority' => $data['priority'] ?? 'medium',
                        'acceptance_criteria' => $data['acceptance_criteria'] ?? null,
                        'status' => 'todo',
                        'color' => ProjectManagementActivityAppearance::DEFAULT_COLOR,
                        'assignment_type' => 'collaborator',
                        'assigned_collaborator_ids' => [],
                        'executor_type' => null,
                        'executor_id' => null,
                    ]);

                    Notification::make()
                        ->title('Historia creada en el backlog')
                        ->success()
                        ->send();
                })
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('primary'),
                ]),
        ];
    }
}
