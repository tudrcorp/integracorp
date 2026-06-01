<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Groups\Schemas;

use App\Models\ProjectManagement\Group;
use App\Support\Filament\ProjectManagement\ProjectManagementFilamentSchemas;
use App\Support\Filament\ProjectManagement\ProjectManagementGroupInfolistDisplay;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\View as ViewFactory;
use Illuminate\Support\HtmlString;

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
                    ProjectManagementFilamentSchemas::section(
                        'Integrantes',
                        'Colaboradores asociados al equipo de trabajo.',
                        'heroicon-o-users',
                    )->schema([
                        TextEntry::make('members_highlight')
                            ->hiddenLabel()
                            ->html()
                            ->state(function (Group $record): HtmlString {
                                $payload = ProjectManagementGroupInfolistDisplay::membersPayload($record);

                                return new HtmlString(
                                    ViewFactory::make('filament.projects.infolists.group-members-highlight', $payload)->render(),
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
