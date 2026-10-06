<?php

namespace App\Filament\Business\Resources\ProspectAgents\Pages;

use App\Filament\Business\Resources\ProspectAgents\ProspectAgentLabels;
use App\Filament\Business\Resources\ProspectAgents\ProspectAgentResource;
use App\Jobs\NotifyProspectAgentTaskAssigneeJob;
use App\Models\ProspectAgent;
use App\Models\ProspectAgentObservation;
use App\Models\ProspectAgentTask;
use App\Models\RrhhColaborador;
use App\Support\Filament\RecordPageHeader;
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
        return 'Prospecto '.(trim((string) $this->getRecord()->name) ?: '');
    }

    /**
     * Encabezado del sistema ({@see RecordPageHeader}): etapa del pipeline con su
     * color, tipo y origen del prospecto, correo y fecha de captación.
     */
    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        /** @var ProspectAgent $prospect */
        $prospect = $this->getRecord();

        $status = trim((string) $prospect->status);

        return RecordPageHeader::render(
            eyebrow: 'Capacitación · Prospecto',
            title: trim((string) $prospect->name) ?: 'Sin nombre',
            status: $status !== ''
                ? RecordPageHeader::tag(Str::upper(ProspectAgentLabels::statusLabel($status)), self::statusTone($status))
                : null,
            chips: [
                filled($prospect->type) ? RecordPageHeader::tag(ProspectAgentLabels::typeLabel($prospect->type), RecordPageHeader::TONE_VIOLET) : null,
                filled($prospect->reference_by) ? RecordPageHeader::tag('Referido por: '.ProspectAgentLabels::referenceLabel($prospect->reference_by), RecordPageHeader::TONE_NEUTRAL) : null,
            ],
            facts: [
                'Correo' => $prospect->email,
                'Captado el' => $prospect->created_at?->format('d/m/Y'),
            ],
        );
    }

    /**
     * Mismo color que la etapa en la tabla de prospectos ({@see ProspectAgentLabels::statusColor()}).
     */
    private static function statusTone(string $status): string
    {
        return match (ProspectAgentLabels::statusColor($status)) {
            'success' => RecordPageHeader::TONE_SUCCESS,
            'danger' => RecordPageHeader::TONE_DANGER,
            'warning' => RecordPageHeader::TONE_WARNING,
            'info', 'primary' => RecordPageHeader::TONE_INFO,
            default => RecordPageHeader::TONE_NEUTRAL,
        };
    }
}
