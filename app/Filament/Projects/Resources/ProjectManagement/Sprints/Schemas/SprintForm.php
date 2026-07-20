<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Sprints\Schemas;

use App\Enums\ProjectManagement\SprintStatus;
use App\Support\Filament\ProjectManagement\ProjectManagementFilamentSchemas;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SprintForm
{
    public static function configure(Schema $schema): Schema
    {
        return ProjectManagementFilamentSchemas::tabbed($schema, 'sprintFormTabs', [
            Tab::make('Sprint')
                ->icon(Heroicon::OutlinedRocketLaunch)
                ->schema([
                    ProjectManagementFilamentSchemas::section(
                        'Iteración',
                        'Nombre, objetivo y ventana temporal del sprint.',
                        'heroicon-o-rocket-launch',
                    )->schema([
                        ProjectManagementFilamentSchemas::innerGrid([
                            TextInput::make('name')
                                ->label('Nombre')
                                ->prefixIcon('heroicon-m-rocket-launch')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),
                            Select::make('status')
                                ->label('Estatus')
                                ->prefixIcon('heroicon-m-signal')
                                ->options(SprintStatus::options())
                                ->default(SprintStatus::Planned->value)
                                ->helperText('Usa Activar/Completar en la vista del sprint para el flujo operativo.')
                                ->required(),
                            DatePicker::make('starts_at')
                                ->label('Inicio')
                                ->native(false)
                                ->required(),
                            DatePicker::make('ends_at')
                                ->label('Fin')
                                ->native(false)
                                ->required()
                                ->afterOrEqual('starts_at'),
                        ], ['default' => 1, 'lg' => 2]),
                    ]),
                ]),
            Tab::make('Proyecto')
                ->icon(Heroicon::OutlinedFolder)
                ->schema([
                    ProjectManagementFilamentSchemas::section(
                        'Vinculación',
                        'Proyecto dueño del sprint.',
                        'heroicon-o-folder',
                    )->schema([
                        ProjectManagementFilamentSchemas::innerGrid([
                            Select::make('project_id')
                                ->label('Proyecto')
                                ->prefixIcon('heroicon-m-folder')
                                ->relationship('project', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->columnSpanFull(),
                        ]),
                    ]),
                ]),
            Tab::make('Objetivo')
                ->icon(Heroicon::OutlinedFlag)
                ->schema([
                    ProjectManagementFilamentSchemas::section(
                        'Sprint Goal',
                        'Resultado de negocio que el equipo se compromete a lograr.',
                        'heroicon-o-flag',
                    )->schema([
                        ProjectManagementFilamentSchemas::innerGrid([
                            Textarea::make('goal')
                                ->label('Objetivo')
                                ->rows(5)
                                ->columnSpanFull(),
                        ]),
                    ]),
                ]),
        ]);
    }
}
