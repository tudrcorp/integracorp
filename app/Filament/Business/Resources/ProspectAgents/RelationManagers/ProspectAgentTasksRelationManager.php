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
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

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
                    ->wrap()  
                    ->width('40%')
                    ->label('Difinicion de Tarea')
                // ->description(fn(ProspectAgentTask $record): string => 'Asignada por: ' . $record->created_by)
                    ->description(fn (ProspectAgentTask $record): HtmlString => new HtmlString(
                        Blade::render(<<<'BLADE'
                            <div class="flex flex-col space-y-1">
                                <span class="text-xs text-gray-500 italic">
                                    Creada por: {{ $created_by }}
                                </span>
                                <span class="text-xs text-gray-500 italic">
                                    Responsable: {{ $assigned_to }}
                                </span>
                                <span class="text-xs text-gray-500 italic">
                                    Registo: {{ $created_at }}    
                                </span>
                                <span class="text-xs text-gray-500 italic">
                                    {{ $date }}    
                                </span>
                            </div>
                        BLADE, [
                            'created_by'    => $record->created_by,
                            'created_at'    => $record->created_at,
                            'date'          => $record->created_at->diffForHumans(),
                            'assigned_to'   => $record->rrhh_colaborador?->fullName,
                        ])
                    ))
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->icon('heroicon-o-puzzle-piece')
                    ->color(fn(string $state): string => match ($state) {
                        'PENDIENTE' => 'gray',
                        'RESUELTA' => 'success',
                    })
                    ->searchable()
                    ->description(fn(ProspectAgentTask $record): HtmlString => new HtmlString(
                        Blade::render(<<<'BLADE'
                                <div class="flex flex-col space-y-1">
                                    <span class="text-xs text-gray-500 italic">
                                        {{ $updated_by ? 'Actualizado por: ' : 'Sin actualizar' }}
                                    </span>
                                    <span class="text-xs text-gray-500 italic">
                                        {{ $updated_by ? $updated_by : '' }}
                                    </span>
                                </div>
                            BLADE, [
                            'updated_by' => $record->updated_by,
                        ])
                    )),
                TextColumn::make('updated_at')
                    ->label('Ultima Actualizacion')
                    ->description(fn(ProspectAgentTask $record): string => $record->updated_at->diffForHumans())
                    ->dateTime()
                    ->sortable(),
                SelectColumn::make('resolved_by')
                    ->options(RrhhColaborador::all()->pluck('fullName', 'fullName'))
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
                
            ])->striped();
    }
}
