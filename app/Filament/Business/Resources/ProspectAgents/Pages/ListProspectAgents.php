<?php

namespace App\Filament\Business\Resources\ProspectAgents\Pages;

use App\Filament\Business\Resources\ProspectAgents\ProspectAgentResource;
use App\Filament\Business\Resources\ProspectAgents\Widgets\ClassificationProspect;
use App\Filament\Business\Resources\ProspectAgents\Widgets\ProspectAgentTasksByUserChart;
use App\Filament\Business\Resources\ProspectAgents\Widgets\ReferenceProspect;
use App\Filament\Business\Resources\ProspectAgents\Widgets\StatsOverviewCapacitacion;
use App\Filament\Business\Resources\ProspectAgents\Widgets\StatusChangesByMonth;
use App\Filament\Business\Resources\ProspectAgents\Widgets\TopRegisterProspect;
use App\Filament\Business\Resources\ProspectAgents\Widgets\TopRegisterProspectForState;
use App\Filament\Business\Resources\ProspectAgents\Widgets\TypeProspect;
use App\Jobs\NotifyProspectAgentTaskAssigneeJob;
use App\Models\ProspectAgent;
use App\Models\ProspectAgentTask;
use App\Models\RrhhColaborador;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Throwable;

class ListProspectAgents extends ListRecords
{
    protected static string $resource = ProspectAgentResource::class;

    protected static ?string $title = 'Captación de Tu Doctor Group';

    public function getSubheading(): string|Htmlable|null
    {
        return new HtmlString(
            '<p class="max-w-3xl text-sm leading-6 text-gray-600 dark:text-gray-300">Prospectos de la red comercial. Registre altas, asigne tareas y dé seguimiento desde esta vista.</p>'
        );
    }

    /**
     * @return int|array<string, int|null>
     */
    public function getHeaderWidgetsColumns(): int|array
    {
        return [
            'default' => 1,
            'lg' => 2,
        ];
    }

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
                            Hidden::make('created_by')->default(Auth::user()->name),
                        ])->columns(1),
                ])
                ->action(function (array $data): void {
                    try {
                        $task = ProspectAgentTask::create([
                            'prospect_agent_id' => $data['prospect_agent_id'],
                            'rrhh_colaborador_id' => $data['rrhh_colaborador_id'],
                            'task' => $data['task'],
                            'created_by' => $data['created_by'] ?? Auth::user()?->name,
                        ]);
                    } catch (Throwable $exception) {
                        Log::error('ListProspectAgents: no se pudo crear la tarea', [
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

                        Log::error('ListProspectAgents: la tarea se guardó pero no se encoló el aviso', [
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
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverviewCapacitacion::class,
            TopRegisterProspect::class,
            ProspectAgentTasksByUserChart::class,
            StatusChangesByMonth::class,
            TopRegisterProspectForState::class,
            TypeProspect::class,
            ReferenceProspect::class,
            ClassificationProspect::class,
        ];
    }
}
