<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Departments\Schemas;

use App\Support\Filament\ProjectManagement\ProjectManagementFilamentSchemas;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class DepartmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return ProjectManagementFilamentSchemas::tabbed($schema, 'departmentInfolistTabs', [
            Tab::make('General')
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->schema([
                    ProjectManagementFilamentSchemas::section(
                        'Departamento',
                        'Detalle de la unidad organizacional.',
                        'heroicon-o-building-office-2',
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
