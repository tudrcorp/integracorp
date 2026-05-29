<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Subprojects\Schemas;

use App\Support\Filament\ProjectManagement\ProjectManagementFilamentSchemas;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SubprojectForm
{
    public static function configure(Schema $schema): Schema
    {
        return ProjectManagementFilamentSchemas::tabbed($schema, 'subprojectFormTabs', [
            Tab::make('General')
                ->icon(Heroicon::OutlinedRectangleStack)
                ->schema([
                    ProjectManagementFilamentSchemas::section(
                        'Subproyecto',
                        'Identificación y estatus dentro del proyecto.',
                        'heroicon-o-rectangle-stack',
                    )->schema([
                        ProjectManagementFilamentSchemas::innerGrid([
                            TextInput::make('name')
                                ->label('Nombre')
                                ->prefixIcon('heroicon-m-rectangle-stack')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),
                            Select::make('status')
                                ->label('Estatus')
                                ->prefixIcon('heroicon-m-signal')
                                ->options([
                                    'pending' => 'Pendiente',
                                    'active' => 'Activo',
                                    'completed' => 'Completado',
                                ])
                                ->default('pending')
                                ->required(),
                        ], ['default' => 1, 'lg' => 2]),
                    ]),
                ]),
            Tab::make('Proyecto')
                ->icon(Heroicon::OutlinedFolder)
                ->schema([
                    ProjectManagementFilamentSchemas::section(
                        'Vinculación',
                        'Proyecto padre del subproyecto.',
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
            Tab::make('Descripción')
                ->icon(Heroicon::OutlinedDocumentText)
                ->schema([
                    ProjectManagementFilamentSchemas::section(
                        'Detalle',
                        'Alcance y notas del subproyecto.',
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
