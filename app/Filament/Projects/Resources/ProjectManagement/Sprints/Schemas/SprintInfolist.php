<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Sprints\Schemas;

use App\Enums\ProjectManagement\SprintStatus;
use App\Support\Filament\ProjectManagement\ProjectManagementFilamentSchemas;
use App\Support\ProjectManagement\BurndownChartData;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SprintInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return ProjectManagementFilamentSchemas::tabbed($schema, 'sprintInfolistTabs', [
            Tab::make('Sprint')
                ->icon(Heroicon::OutlinedRocketLaunch)
                ->schema([
                    ProjectManagementFilamentSchemas::section(
                        'Iteración',
                        'Estado y ventana del sprint.',
                        'heroicon-o-rocket-launch',
                    )->schema([
                        ProjectManagementFilamentSchemas::innerGrid([
                            TextEntry::make('name')
                                ->label('Nombre')
                                ->badge()
                                ->color('primary'),
                            TextEntry::make('status')
                                ->label('Estatus')
                                ->badge()
                                ->formatStateUsing(fn (SprintStatus|string|null $state): string => $state instanceof SprintStatus
                                    ? $state->label()
                                    : (SprintStatus::tryFrom((string) $state)?->label() ?? (string) $state))
                                ->color(fn (SprintStatus|string|null $state): string => match ($state instanceof SprintStatus ? $state : SprintStatus::tryFrom((string) $state)) {
                                    SprintStatus::Planned => 'gray',
                                    SprintStatus::Active => 'success',
                                    SprintStatus::Completed => 'info',
                                    default => 'gray',
                                }),
                            TextEntry::make('starts_at')
                                ->label('Inicio')
                                ->date('d/m/Y'),
                            TextEntry::make('ends_at')
                                ->label('Fin')
                                ->date('d/m/Y'),
                            TextEntry::make('project.name')
                                ->label('Proyecto')
                                ->badge()
                                ->color('info'),
                            TextEntry::make('goal')
                                ->label('Objetivo')
                                ->placeholder('—')
                                ->columnSpanFull(),
                        ], ['default' => 1, 'lg' => 2]),
                    ]),
                    ProjectManagementFilamentSchemas::section(
                        'Burndown',
                        'Compromiso vs trabajo restante del sprint.',
                        'heroicon-o-chart-bar',
                    )->schema([
                        ViewEntry::make('burndown')
                            ->label('')
                            ->view('filament.projects.infolists.sprint-burndown')
                            ->state(fn ($record): array => (new BurndownChartData)->forSprint($record))
                            ->columnSpanFull(),
                    ]),
                ]),
        ]);
    }
}
