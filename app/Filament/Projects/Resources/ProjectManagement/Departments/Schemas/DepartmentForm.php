<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Departments\Schemas;

use App\Support\Filament\ProjectManagement\ProjectManagementFilamentSchemas;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class DepartmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return ProjectManagementFilamentSchemas::tabbed($schema, 'departmentFormTabs', [
            Tab::make('General')
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->schema([
                    ProjectManagementFilamentSchemas::section(
                        'Departamento',
                        'Unidad organizacional asignable a proyectos.',
                        'heroicon-o-building-office-2',
                    )->schema([
                        ProjectManagementFilamentSchemas::innerGrid([
                            TextInput::make('name')
                                ->label('Nombre')
                                ->prefixIcon('heroicon-m-building-office-2')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),
                            Textarea::make('description')
                                ->label('Descripción')
                                ->rows(4)
                                ->columnSpanFull(),
                        ]),
                    ]),
                ]),
        ]);
    }
}
