<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Groups\Schemas;

use App\Models\ProjectManagement\Group;
use App\Support\Filament\ProjectManagement\ProjectManagementFilamentSchemas;
use App\Support\Filament\ProjectManagement\ProjectManagementGroupTable;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class GroupInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return ProjectManagementFilamentSchemas::tabbed($schema, 'groupInfolistTabs', [
            Tab::make('General')
                ->icon(Heroicon::OutlinedUserGroup)
                ->schema([
                    ProjectManagementFilamentSchemas::section(
                        'Grupo',
                        'Detalle del equipo de trabajo.',
                        'heroicon-o-user-group',
                    )->schema([
                        ProjectManagementFilamentSchemas::innerGrid([
                            TextEntry::make('name')
                                ->label('Nombre')
                                ->badge()
                                ->color('primary'),
                            TextEntry::make('description')
                                ->label('Descripción')
                                ->placeholder('—')
                                ->columnSpanFull(),
                        ], ['default' => 1, 'lg' => 2]),
                    ]),
                ]),
            Tab::make('Integrantes')
                ->icon(Heroicon::OutlinedUsers)
                ->schema([
                    ProjectManagementFilamentSchemas::section(
                        'Integrantes del equipo',
                        'Listado optimizado de colaboradores asociados al equipo.',
                        'heroicon-o-users',
                    )->schema([
                        ProjectManagementFilamentSchemas::innerGrid([
                            TextEntry::make('team_size')
                                ->label('Total de integrantes')
                                ->state(fn (Group $record): int => ProjectManagementGroupTable::resolveMemberNames($record)->count())
                                ->badge()
                                ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray'),
                            TextEntry::make('team_members')
                                ->label('Integrantes')
                                ->state(fn (Group $record): array => self::resolveMemberNames($record)->all())
                                ->listWithLineBreaks()
                                ->bulleted()
                                ->placeholder('Sin integrantes asignados')
                                ->columnSpanFull(),
                        ], ['default' => 1, 'lg' => 1]),
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
