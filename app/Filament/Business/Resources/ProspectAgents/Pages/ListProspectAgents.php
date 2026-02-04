<?php

namespace App\Filament\Business\Resources\ProspectAgents\Pages;

use App\Filament\Business\Resources\ProspectAgents\ProspectAgentResource;
use App\Models\ProspectAgent;
use App\Models\ProspectAgentTask;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Fieldset;

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
                ->modalHeading('Nueva Tarea')
                ->modalSubmitActionLabel('Guardar')
                ->modalCancelActionLabel('Cancelar')
                ->form([
                    Fieldset::make('Formulario de Notas')
                        ->schema([
                            TextInput::make('created_by')
                                ->label('Creado por')
                                ->disabled()
                                ->dehydrated()
                                ->default(auth()->user()->name),
                            Select::make('prospect_agent_id')
                                ->label('Prospecto')
                                ->options(ProspectAgent::all()->pluck('name', 'id'))
                                ->required(),
                            Textarea::make('task')
                                ->label('Definicion de la Tarea')
                                ->autosize()
                                ->required(),
                        ])->columns(1),
                ])
                ->action(function ($data, $record) {

                    try {

                        ProspectAgentTask::create([
                            'prospect_agent_id' => $data['prospect_agent_id'],
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
