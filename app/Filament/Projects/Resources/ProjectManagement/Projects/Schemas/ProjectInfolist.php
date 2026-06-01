<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Projects\Schemas;

use App\Models\ProjectManagement\Project;
use App\Support\Filament\ProjectManagement\ProjectManagementFilamentSchemas;
use App\Support\Filament\ProjectManagement\ProjectManagementProjectAppearance;
use App\Support\Filament\ProjectManagement\ProjectManagementProjectInfolistDisplay;
use Filament\Infolists\Components\ColorEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\View as ViewFactory;
use Illuminate\Support\HtmlString;

class ProjectInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return ProjectManagementFilamentSchemas::tabbed($schema, 'projectInfolistTabs', [
            Tab::make('General')
                ->icon(Heroicon::OutlinedFolder)
                ->schema([
                    ProjectManagementFilamentSchemas::section(
                        'Proyecto',
                        'Identificación y estatus.',
                        'heroicon-o-folder',
                    )->schema([
                        ProjectManagementFilamentSchemas::innerGrid([
                            TextEntry::make('name')
                                ->label('Nombre')
                                ->badge()
                                ->color('primary'),
                            TextEntry::make('status')
                                ->label('Estatus')
                                ->badge()
                                ->formatStateUsing(fn (string $state): string => match ($state) {
                                    'active' => 'Activo',
                                    'on_hold' => 'En espera',
                                    'completed' => 'Completado',
                                    default => $state,
                                })
                                ->color(fn (string $state): string => match ($state) {
                                    'active' => 'success',
                                    'on_hold' => 'warning',
                                    'completed' => 'gray',
                                    default => 'gray',
                                }),
                            ColorEntry::make('color')
                                ->label('Color')
                                ->default(ProjectManagementProjectAppearance::DEFAULT_COLOR),
                            TextEntry::make('icon')
                                ->label('Icono')
                                ->icon(fn (?string $state): string => $state ?? ProjectManagementProjectAppearance::DEFAULT_ICON)
                                ->formatStateUsing(fn (?string $state): string => ProjectManagementProjectAppearance::iconOptions()[$state ?? ''] ?? 'Carpeta'),
                        ], ['default' => 1, 'lg' => 2]),
                    ]),
                    ProjectManagementFilamentSchemas::section(
                        'Planificación',
                        'Fechas de inicio y fin del proyecto.',
                        'heroicon-o-calendar-days',
                    )->schema([
                        TextEntry::make('dates_highlight')
                            ->hiddenLabel()
                            ->html()
                            ->state(function (Project $record): HtmlString {
                                $payload = ProjectManagementProjectInfolistDisplay::datesPayload($record);

                                return new HtmlString(
                                    ViewFactory::make('filament.projects.infolists.project-dates-highlight', $payload)->render(),
                                );
                            })
                            ->columnSpanFull(),
                    ]),
                    ProjectManagementFilamentSchemas::section(
                        'Descripción',
                        'Alcance y detalle del proyecto.',
                        'heroicon-o-document-text',
                    )->schema([
                        TextEntry::make('description_highlight')
                            ->hiddenLabel()
                            ->html()
                            ->state(function (Project $record): HtmlString {
                                $payload = ProjectManagementProjectInfolistDisplay::descriptionPayload($record);

                                return new HtmlString(
                                    ViewFactory::make('filament.projects.infolists.project-description-highlight', $payload)->render(),
                                );
                            })
                            ->columnSpanFull(),
                    ]),
                ]),
            Tab::make('Diagrama de Proyecto')
                ->icon(Heroicon::OutlinedShare)
                ->schema([
                    ProjectManagementFilamentSchemas::section(
                        'Diagrama de flujo',
                        'Relación visual entre el proyecto maestro y sus subproyectos.',
                        'heroicon-o-share',
                    )->schema([
                        TextEntry::make('flow_diagram')
                            ->hiddenLabel()
                            ->html()
                            ->state(function (Project $record): HtmlString {
                                $payload = ProjectManagementProjectInfolistDisplay::flowDiagramPayload($record);

                                return new HtmlString(
                                    ViewFactory::make('filament.projects.infolists.project-flow-diagram', $payload)->render(),
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
}
