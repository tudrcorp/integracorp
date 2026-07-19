<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Epics\Schemas;

use App\Enums\ProjectManagement\EpicStatus;
use App\Support\Filament\ProjectManagement\ProjectManagementFilamentSchemas;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EpicForm
{
    public static function configure(Schema $schema): Schema
    {
        return ProjectManagementFilamentSchemas::tabbed($schema, 'epicFormTabs', [
            Tab::make('General')
                ->icon(Heroicon::OutlinedBookmarkSquare)
                ->schema([
                    ProjectManagementFilamentSchemas::section(
                        'Épica',
                        'Bloque de valor que agrupa historias del backlog.',
                        'heroicon-o-bookmark-square',
                    )->schema([
                        ProjectManagementFilamentSchemas::innerGrid([
                            TextInput::make('name')
                                ->label('Nombre')
                                ->prefixIcon('heroicon-m-bookmark-square')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),
                            Select::make('status')
                                ->label('Estatus')
                                ->prefixIcon('heroicon-m-signal')
                                ->options(EpicStatus::options())
                                ->default(EpicStatus::Open->value)
                                ->required(),
                            TextInput::make('order')
                                ->label('Orden')
                                ->prefixIcon('heroicon-m-bars-3')
                                ->numeric()
                                ->minValue(0)
                                ->default(0)
                                ->required(),
                        ], ['default' => 1, 'lg' => 2]),
                    ]),
                ]),
            Tab::make('Proyecto')
                ->icon(Heroicon::OutlinedFolder)
                ->schema([
                    ProjectManagementFilamentSchemas::section(
                        'Vinculación',
                        'Proyecto al que pertenece la épica.',
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
                        'Alcance y notas de la épica.',
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
