<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Groups\Schemas;

use App\Models\RrhhColaborador;
use App\Support\Filament\ProjectManagement\ProjectManagementFilamentSchemas;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class GroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return ProjectManagementFilamentSchemas::tabbed($schema, 'groupFormTabs', [
            Tab::make('General')
                ->icon(Heroicon::OutlinedUserGroup)
                ->schema([
                    ProjectManagementFilamentSchemas::section(
                        'Grupo',
                        'Equipo de trabajo asignable a actividades.',
                        'heroicon-o-user-group',
                    )->schema([
                        ProjectManagementFilamentSchemas::innerGrid([
                            TextInput::make('name')
                                ->label('Nombre')
                                ->prefixIcon('heroicon-m-user-group')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),
                            Textarea::make('description')
                                ->label('Descripción')
                                ->rows(4)
                                ->columnSpanFull(),
                            Select::make('collaborator_ids')
                                ->label('Colaboradores del equipo')
                                ->prefixIcon('heroicon-m-users')
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->default([])
                                ->options(fn (): array => RrhhColaborador::query()
                                    ->where('fullName', '!=', 'CAYETANO BATRES')
                                    ->orderBy('fullName', 'asc')
                                    ->pluck('fullName', 'id')
                                    ->all())
                                ->getSearchResultsUsing(fn (string $search): array => RrhhColaborador::query()
                                    ->where('fullName', '!=', 'CAYETANO BATRES')
                                    ->where(fn (Builder $query): Builder => $query->where('fullName', 'like', "%{$search}%"))
                                    ->orderBy('fullName', 'asc')
                                    ->limit(50)
                                    ->pluck('fullName', 'id')
                                    ->all())
                                ->helperText('Selecciona uno o varios colaboradores de RRHH (se excluye CAYETANO BATRES).')
                                ->columnSpanFull(),
                        ]),
                    ]),
                ]),
        ]);
    }
}
