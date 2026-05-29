<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Projects\Schemas;

use App\Support\Filament\ProjectManagement\ProjectManagementFilamentSchemas;
use App\Support\Filament\ProjectManagement\ProjectManagementProjectAppearance;
use Filament\Infolists\Components\ColorEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

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
                ]),
            Tab::make('Fechas')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->schema([
                    ProjectManagementFilamentSchemas::section(
                        'Planificación',
                        'Ventana temporal del proyecto.',
                        'heroicon-o-calendar-days',
                    )->schema([
                        ProjectManagementFilamentSchemas::innerGrid([
                            TextEntry::make('start_date')
                                ->label('Inicio')
                                ->date()
                                ->placeholder('—'),
                            TextEntry::make('end_date')
                                ->label('Fin')
                                ->date()
                                ->placeholder('—'),
                        ], ['default' => 1, 'lg' => 2]),
                    ]),
                ]),
            Tab::make('Descripción')
                ->icon(Heroicon::OutlinedDocumentText)
                ->schema([
                    ProjectManagementFilamentSchemas::section(
                        'Detalle',
                        'Alcance y notas del proyecto.',
                        'heroicon-o-document-text',
                    )->schema([
                        ProjectManagementFilamentSchemas::innerGrid([
                            TextEntry::make('description')
                                ->label('Descripción')
                                ->placeholder('—')
                                ->columnSpanFull(),
                        ]),
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
