<?php

namespace App\Filament\Business\Resources\ProspectAgents\Pages;

use App\Filament\Business\Resources\ProspectAgents\ProspectAgentResource;
use App\Models\ProspectAgent;
use App\Models\ProspectAgentObservation;
use App\Models\ProspectAgentTask;
use App\Models\RrhhColaborador;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;

class ViewProspectAgent extends ViewRecord
{
    protected static string $resource = ProspectAgentResource::class;

    /**
     * Sobrescribimos los Relation Managers para esta página específica.
     * Al retornar un array vacío, no se mostrará ninguna tabla de relación
     * en la vista de "Ver", pero se mantendrán en la de "Editar".
     */
    public function getRelationManagers(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(ProspectAgentResource::getUrl()),
            EditAction::make()
                ->label('Editar')
                ->icon('heroicon-o-pencil')
                ->color('primary'),
            Action::make('notes')
                ->label('Agregar Notas/Observaciones')
                ->icon('heroicon-o-document-text')
                ->color('success')
                ->modal()
                ->modalHeading('Agregar Notas/Observaciones')
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
                                ->default($this->record->id)
                                ->disabled()
                                ->required(),
                            Select::make('prospect_agent_task_id')
                                ->label('Tarea')
                                ->options(ProspectAgentTask::all()->where('status', 'PENDIENTE')->where('prospect_agent_id', $this->record->id)->pluck('id', 'id'))
                                ->preload()
                                ->searchable()
                                ->required(),
                        ])->columnSpanFull(),
                        Textarea::make('observations')
                            ->label('Notas')
                            ->autosize()
                            ->required(),
                    ])->columns(1),
                ])
                ->action(function ($data, $record) {

                    try {

                        ProspectAgentObservation::create([
                            'prospect_agent_id'         => $record->id,
                            'observation'               => $data['observations'],
                            'created_by'                => auth()->user()->name,
                            'prospect_agent_task_id'    => $data['prospect_agent_task_id'],
                        ]);

                        Notification::make()
                            ->title('Notas agregadas correctamente')
                            ->success()
                            ->send();

                        $this->redirectMethod($record->id);    

                    } catch (\Exception $e) {
                        dd($e);
                        Notification::make()
                            ->title('Error al agregar notas')
                            ->danger()
                            ->send();
                    }

                }),
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
                                    ->default($this->record->id)
                                    ->disabled()
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
                            'prospect_agent_id' => $record->id,
                            'rrhh_colaborador_id' => $data['rrhh_colaborador_id'],
                            'task'              => $data['task'],
                            'created_by'        => $data['created_by'],
                        ]);

                        Notification::make()
                            ->title('Notas agregadas correctamente')
                            ->success()
                            ->send();

                        $this->redirectMethod($record->id);

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

    /**
     * Corrección del error de redirección.
     * getUrl() requiere un array ['record' => $id] como segundo parámetro.
     */
    public function redirectMethod($recordId): void
    {
        $this->redirect(ProspectAgentResource::getUrl('view', ['record' => $recordId]));
    }

    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        $prospectAgent = $this->getRecord();

        // Definimos el nombre del afiliado de forma segura
        $fullName = $prospectAgent->name ?? 'Sin Nombre';

        return new \Illuminate\Support\HtmlString(
            '<div style="display: flex; flex-direction: column; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; gap: 2px; padding: 12px 0;">' .
                // Título Principal Resaltado
                '<span class="text-sm font-bold uppercase tracking-tight text-gray-900 dark:text-gray-100 mb-2 dark:text-white">' .
                'Informacion Principal'. 
                '</span>' .

                // Subtítulo (Nombre del Paciente)
                '<span class="text-3xl font-bold tracking-tight text-gray-900 dark:text-gray-100 mb-2 dark:text-white">' .
                'Prospecto: ' . $fullName .
                '</span>' .

                // Estatus Estilo Badge iOS Resaltado
                '<div style="display: flex; align-items: center; margin-top: 8px;">' .
                '<span style="' .
                'background-color: #28cd41; ' . // Verde iOS vibrante
                'color: #ffffff; ' .
                'padding: 6px 16px; ' .
                'border-radius: 50px; ' .
                'font-size: 0.8rem; ' .
                'font-weight: 700; ' .
                'display: inline-flex; ' .
                'align-items: center; ' .
                'gap: 6px; ' .
                'box-shadow: 0 4px 12px rgba(40, 205, 65, 0.35); ' .
                'border: 1px solid rgba(255, 255, 255, 0.2);' .
                '">' .
                '<span style="font-size: 10px;">●</span>' . $prospectAgent->status .
                '</span>' .
                '</div>' .
                '</div>'
        );
    }
}
