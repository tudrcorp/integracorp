<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Projects\Schemas;

use App\Support\Filament\ProjectManagement\ProjectManagementFilamentSchemas;
use App\Support\Filament\ProjectManagement\ProjectManagementProjectAppearance;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return ProjectManagementFilamentSchemas::tabbed($schema, 'projectFormTabs', [
            Tab::make('General')
                ->icon(Heroicon::OutlinedFolder)
                ->schema([
                    ProjectManagementFilamentSchemas::section(
                        'Proyecto',
                        'Identificación y estatus del proyecto.',
                        'heroicon-o-folder',
                    )->schema([
                        ProjectManagementFilamentSchemas::innerGrid([
                            TextInput::make('name')
                                ->label('Nombre')
                                ->prefixIcon('heroicon-m-folder')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),
                            Select::make('status')
                                ->label('Estatus')
                                ->prefixIcon('heroicon-m-signal')
                                ->options([
                                    'active' => 'Activo',
                                    'on_hold' => 'En espera',
                                    'completed' => 'Completado',
                                ])
                                ->default('active')
                                ->required(),
                        ], ['default' => 1, 'lg' => 2]),
                    ]),
                    ProjectManagementFilamentSchemas::section(
                        'Identidad visual',
                        'Color e icono para distinguir el proyecto rápidamente en listas y navegación.',
                        'heroicon-o-swatch',
                    )->schema([
                        ProjectManagementFilamentSchemas::innerGrid([
                            ToggleButtons::make('color')
                                ->label('Color rápido')
                                ->options(ProjectManagementProjectAppearance::colorPresets())
                                ->inline()
                                ->live()
                                ->default(ProjectManagementProjectAppearance::DEFAULT_COLOR)
                                ->columnSpanFull(),
                            ColorPicker::make('color')
                                ->label('Color del proyecto')
                                ->helperText('Personaliza el tono exacto del proyecto.')
                                ->hex()
                                ->default(ProjectManagementProjectAppearance::DEFAULT_COLOR)
                                ->required(),
                            Select::make('icon')
                                ->label('Icono del proyecto')
                                ->prefixIcon('heroicon-m-squares-2x2')
                                ->options(ProjectManagementProjectAppearance::iconOptions())
                                ->searchable()
                                ->preload()
                                ->default(ProjectManagementProjectAppearance::DEFAULT_ICON)
                                ->required()
                                ->native(false),
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
                            DatePicker::make('start_date')
                                ->label('Fecha de inicio')
                                ->native(false),
                            DatePicker::make('end_date')
                                ->label('Fecha de fin')
                                ->native(false),
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
