<?php

namespace App\Filament\Business\Resources\ProspectAgents\Pages;

use App\Filament\Business\Resources\ProspectAgents\ProspectAgentResource;
use App\Jobs\NotifyProspectAgentTaskAssigneeJob;
use App\Models\ProspectAgent;
use App\Models\ProspectAgentObservation;
use App\Models\ProspectAgentTask;
use App\Models\RrhhColaborador;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ViewProspectAgent extends ViewRecord
{
    protected static string $resource = ProspectAgentResource::class;

    private const IOS_BUTTON_BASE = ' shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const IOS_GRAY_BUTTON_CLASS = 'ticket-btn-ios-gray'.self::IOS_BUTTON_BASE;

    private const IOS_PRIMARY_BUTTON_CLASS = 'aviso-btn-ios-primary'.self::IOS_BUTTON_BASE;

    private const IOS_SUCCESS_BUTTON_CLASS = 'aviso-btn-ios-success'.self::IOS_BUTTON_BASE;

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
                ->url(ProspectAgentResource::getUrl())
                ->extraAttributes([
                    'class' => self::IOS_GRAY_BUTTON_CLASS,
                ]),
            EditAction::make()
                ->label('Editar')
                ->icon('heroicon-o-pencil')
                ->color('primary')
                ->extraAttributes([
                    'class' => self::IOS_PRIMARY_BUTTON_CLASS,
                ]),
            Action::make('notes')
                ->label('Agregar Notas/Observaciones')
                ->icon('heroicon-o-document-text')
                ->color('success')
                ->extraAttributes([
                    'class' => self::IOS_SUCCESS_BUTTON_CLASS,
                ])
                ->modal()
                ->modalHeading('Agregar Notas/Observaciones')
                ->modalSubmitActionLabel('Guardar')
                ->modalCancelActionLabel('Cancelar')
                ->modalSubmitAction(fn (Action $action): Action => $action->extraAttributes([
                    'class' => self::IOS_SUCCESS_BUTTON_CLASS,
                ]))
                ->modalCancelAction(fn (Action $action): Action => $action->extraAttributes([
                    'class' => self::IOS_GRAY_BUTTON_CLASS,
                ]))
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
                                    ->placeholder('Sin tarea vinculada')
                                    ->helperText('Opcional. Puedes vincular cualquier tarea de este prospecto o dejarla en blanco.')
                                    ->options(fn (): array => $this->prospectTaskOptions())
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),
                            ])->columnSpanFull(),
                            Textarea::make('observations')
                                ->label('Notas')
                                ->rows(8)
                                ->autosize()
                                ->columnSpanFull()
                                ->required(),
                        ])->columns(1),
                ])
                ->action(function ($data, $record) {

                    try {

                        ProspectAgentObservation::create([
                            'prospect_agent_id' => $record->id,
                            'observation' => $data['observations'],
                            'created_by' => auth()->user()->name,
                            'prospect_agent_task_id' => filled($data['prospect_agent_task_id'] ?? null)
                                ? $data['prospect_agent_task_id']
                                : null,
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
                ->extraAttributes([
                    'class' => self::IOS_SUCCESS_BUTTON_CLASS,
                ])
                ->modal()
                ->modalHeading('Formulario de Asigancion de Tarea')
                ->modalSubmitActionLabel('Guardar')
                ->modalCancelActionLabel('Cancelar')
                ->modalSubmitAction(fn (Action $action): Action => $action->extraAttributes([
                    'class' => self::IOS_SUCCESS_BUTTON_CLASS,
                ]))
                ->modalCancelAction(fn (Action $action): Action => $action->extraAttributes([
                    'class' => self::IOS_GRAY_BUTTON_CLASS,
                ]))
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
                ->action(function (array $data, ProspectAgent $record): void {
                    try {
                        $task = ProspectAgentTask::create([
                            'prospect_agent_id' => $record->getKey(),
                            'rrhh_colaborador_id' => $data['rrhh_colaborador_id'],
                            'task' => $data['task'],
                            'created_by' => $data['created_by'] ?? auth()->user()?->name,
                        ]);
                    } catch (Throwable $exception) {
                        Log::error('ViewProspectAgent: no se pudo crear la tarea', [
                            'prospect_agent_id' => $record->getKey(),
                            'error' => $exception->getMessage(),
                        ]);

                        Notification::make()
                            ->title('No se pudo asignar la tarea')
                            ->body('Inténtelo de nuevo. Si el problema continúa, avise a sistemas.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $queued = true;

                    try {
                        NotifyProspectAgentTaskAssigneeJob::dispatch((int) $task->getKey());
                    } catch (Throwable $exception) {
                        $queued = false;

                        Log::error('ViewProspectAgent: la tarea se guardó pero no se encoló el aviso', [
                            'task_id' => $task->getKey(),
                            'error' => $exception->getMessage(),
                        ]);
                    }

                    Notification::make()
                        ->title('Tarea asignada')
                        ->body($queued
                            ? 'El colaborador recibirá el aviso por WhatsApp, correo y la campana del panel.'
                            : 'La tarea quedó guardada, pero no se pudo encolar el aviso. Avise a sistemas.')
                        ->success()
                        ->send();

                    $this->redirectMethod($record->getKey());
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

    /**
     * Todas las tareas del prospecto, sin filtrar por estado.
     *
     * @return array<int|string, string>
     */
    private function prospectTaskOptions(): array
    {
        return ProspectAgentTask::query()
            ->where('prospect_agent_id', $this->record->getKey())
            ->orderByDesc('id')
            ->get(['id', 'task', 'status'])
            ->mapWithKeys(function (ProspectAgentTask $task): array {
                $description = trim((string) $task->task);
                $label = '#'.$task->id;

                if ($description !== '') {
                    $label .= ' · '.Str::limit($description, 90);
                }

                if (filled($task->status)) {
                    $label .= ' ('.$task->status.')';
                }

                return [$task->id => $label];
            })
            ->all();
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        $prospectAgent = $this->getRecord();

        // Definimos el nombre del afiliado de forma segura
        $fullName = $prospectAgent->name ?? 'Sin Nombre';

        return new \Illuminate\Support\HtmlString(
            '<div style="display: flex; flex-direction: column; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; gap: 2px; padding: 12px 0;">'.
                // Título Principal Resaltado
                '<span class="text-sm font-bold uppercase tracking-tight text-gray-900 dark:text-gray-100 mb-2 dark:text-white">'.
                'Informacion Principal'.
                '</span>'.

                // Subtítulo (Nombre del Paciente)
                '<span class="text-3xl font-bold tracking-tight text-gray-900 dark:text-gray-100 mb-2 dark:text-white">'.
                'Prospecto: '.$fullName.
                '</span>'.

                // Estatus Estilo Badge iOS Resaltado
                '<div style="display: flex; align-items: center; margin-top: 8px;">'.
                '<span style="'.
                'background-color: #28cd41; '. // Verde iOS vibrante
                'color: #ffffff; '.
                'padding: 6px 16px; '.
                'border-radius: 50px; '.
                'font-size: 0.8rem; '.
                'font-weight: 700; '.
                'display: inline-flex; '.
                'align-items: center; '.
                'gap: 6px; '.
                'box-shadow: 0 4px 12px rgba(40, 205, 65, 0.35); '.
                'border: 1px solid rgba(255, 255, 255, 0.2);'.
                '">'.
                '<span style="font-size: 10px;">●</span>'.$prospectAgent->status.
                '</span>'.
                '</div>'.
                '</div>'
        );
    }
}
