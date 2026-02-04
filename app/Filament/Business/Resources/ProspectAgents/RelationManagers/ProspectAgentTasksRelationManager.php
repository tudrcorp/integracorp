<?php

namespace App\Filament\Business\Resources\ProspectAgents\RelationManagers;

use App\Filament\Business\Resources\ProspectAgents\ProspectAgentResource;
use App\Models\ProspectAgentTask;
use App\Models\RrhhColaborador;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ProspectAgentTasksRelationManager extends RelationManager
{
    protected static string $relationship = 'prospect_agent_tasks';

    public function table(Table $table): Table
    {
        return $table
            ->heading('TAREAS')
            ->description('Lista de tareas asignadas para la captacion del prospecto')
            ->columns([
                TextColumn::make('task')
                    ->label('Difinicion de Tarea')
                    ->searchable(),
                TextColumn::make('created_by')
                    ->label('Creada por')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->icon('heroicon-o-puzzle-piece')
                    ->color(fn(string $state): string => match ($state) {
                        'PENDIENTE' => 'gray',
                        'RESUELTA' => 'success',
                    })
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha de Registro')
                    ->description(fn(ProspectAgentTask $record): string => $record->created_at->diffForHumans())
                    ->dateTime()
                    ->sortable(),
                SelectColumn::make('resolved_by')
                    ->options(RrhhColaborador::all()->pluck('fullName', 'id'))
                    ->searchableOptions()
                    ->afterStateUpdated(function ($record, $state) {
                        $record->update([
                            'resolved_by' => $state ?? null,
                            'updated_by' => Auth::user()->name,
                            'status' => $state ? 'RESUELTA' : 'PENDIENTE',
                        ]);
                        Log::info('NEGOCIOS: Tarea actualizada', [
                            'record' => $record->id,
                            'state' => $state,
                            'user' => Auth::user()->name,
                            'date' => now(),
                        ]);
                    }),
                TextColumn::make('updated_by')
                    ->label('Actualizado por')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Ultima Actualizacion')
                    ->description(fn(ProspectAgentTask $record): string => $record->updated_at->diffForHumans())
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])->striped();
    }
}
