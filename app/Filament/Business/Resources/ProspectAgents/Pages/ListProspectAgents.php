<?php

namespace App\Filament\Business\Resources\ProspectAgents\Pages;

use App\Filament\Business\Resources\ProspectAgents\ProspectAgentResource;
use App\Models\ProspectAgent;
use App\Models\ProspectAgentTask;
use App\Models\RrhhColaborador;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;

class ListProspectAgents extends ListRecords
{
    protected static string $resource = ProspectAgentResource::class;

    protected static ?string $title = 'Prospectos TuDrGroup';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
            ->label('Nuevo prospecto')
            ->icon('heroicon-o-plus'),
            Action::make('tascks')
                ->label('Nueva Tarea')
                ->icon('heroicon-o-puzzle-piece')
                ->color('success')
                ->modal()
                ->modalHeading('Formulario de Asigancion de Tarea')
                ->modalSubmitActionLabel('Guardar')
                ->modalCancelActionLabel('Cancelar')
                ->form([
                    Fieldset::make('Formulario de Notas')
                        ->schema([
                            Grid::make(2)->schema([                            
                                Select::make('prospect_agent_id')
                                    ->label('Selecciona el Prospecto')
                                    ->preload()
                                    ->searchable()
                                    ->options(ProspectAgent::all()->pluck('name', 'id'))
                                    ->required(),
                                Select::make('rrhh_colaborador_id')
                                    ->label('Selecciona el Colaborador para la Tarea')
                                    ->options(RrhhColaborador::all()->pluck('fullName', 'id'))
                                    ->preload()
                                    ->searchable()
                                    ->required(),
                            ])->columnSpanFull(),
                            Textarea::make('task')
                                ->label('Descripción de la Tarea')
                                ->helperText('Describe la tarea que se debe realizar, por ejemplo: Llamar al prospecto para agendar una reunión o enviar un correo electrónico. Debes ser lo mas especifico posible para que el colaborador entienda que debe hacer')
                                ->autosize()
                                ->required(),
                            Hidden::make('created_by')->default(auth()->user()->name),
                        ])->columns(1),
                ])
                ->action(function ($data, $record) {

                    try {

                        ProspectAgentTask::create([
                            'prospect_agent_id' => $data['prospect_agent_id'],
                            'rrhh_colaborador_id' => $data['rrhh_colaborador_id'],
                            'task'              => $data['task'],
                            'created_by'        => $data['created_by'],
                        ]);

                        Notification::make()
                            ->title('Notas agregadas correctamente')
                            ->success()
                            ->send();

                    } catch (\Exception $e) {
                        dd($e);
                        Notification::make()
                            ->title('Error al agregar notas')
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
