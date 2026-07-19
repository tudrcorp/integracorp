<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Epics\Schemas;

use App\Enums\ProjectManagement\EpicStatus;
use App\Support\Filament\ProjectManagement\ProjectManagementFilamentSchemas;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EpicInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return ProjectManagementFilamentSchemas::tabbed($schema, 'epicInfolistTabs', [
            Tab::make('General')
                ->icon(Heroicon::OutlinedBookmarkSquare)
                ->schema([
                    ProjectManagementFilamentSchemas::section(
                        'Épica',
                        'Identificación y estatus.',
                        'heroicon-o-bookmark-square',
                    )->schema([
                        ProjectManagementFilamentSchemas::innerGrid([
                            TextEntry::make('name')
                                ->label('Nombre')
                                ->badge()
                                ->color('primary'),
                            TextEntry::make('status')
                                ->label('Estatus')
                                ->badge()
                                ->formatStateUsing(fn (EpicStatus|string|null $state): string => $state instanceof EpicStatus
                                    ? $state->label()
                                    : (EpicStatus::tryFrom((string) $state)?->label() ?? (string) $state))
                                ->color(fn (EpicStatus|string|null $state): string => match ($state instanceof EpicStatus ? $state : EpicStatus::tryFrom((string) $state)) {
                                    EpicStatus::Open => 'success',
                                    EpicStatus::Done => 'gray',
                                    default => 'gray',
                                }),
                            TextEntry::make('order')
                                ->label('Orden')
                                ->badge()
                                ->color('gray'),
                            TextEntry::make('activities_count')
                                ->label('Historias')
                                ->state(fn ($record): int => (int) ($record->activities_count ?? $record->activities()->count()))
                                ->badge()
                                ->color('info'),
                            TextEntry::make('story_points_sum')
                                ->label('Puntos')
                                ->state(fn ($record): int => (int) ($record->activities_sum_story_points ?? $record->activities()->sum('story_points')))
                                ->badge()
                                ->color('warning'),
                        ], ['default' => 1, 'lg' => 2]),
                    ]),
                ]),
            Tab::make('Proyecto')
                ->icon(Heroicon::OutlinedFolder)
                ->schema([
                    ProjectManagementFilamentSchemas::section(
                        'Vinculación',
                        'Proyecto padre de la épica.',
                        'heroicon-o-folder',
                    )->schema([
                        ProjectManagementFilamentSchemas::innerGrid([
                            TextEntry::make('project.name')
                                ->label('Proyecto')
                                ->badge()
                                ->color('info'),
                        ]),
                    ]),
                ]),
            Tab::make('Descripción')
                ->icon(Heroicon::OutlinedDocumentText)
                ->schema([
                    ProjectManagementFilamentSchemas::section(
                        'Detalle',
                        'Alcance y notas.',
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
        ]);
    }
}
