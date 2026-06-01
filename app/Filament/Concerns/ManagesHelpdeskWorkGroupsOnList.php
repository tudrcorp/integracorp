<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Models\HelpdeskGroup;
use App\Support\HelpdeskBusinessTicketCreationGate;
use App\Support\HelpdeskUserAccess;
use App\Support\HelpdeskWorkGroupFormSchema;
use App\Support\HelpdeskWorkGroupValidator;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

trait ManagesHelpdeskWorkGroupsOnList
{
    public function mountDeleteHelpdeskWorkGroup(int $groupId): void
    {
        $this->replaceMountedAction('deleteHelpdeskWorkGroup', ['groupId' => $groupId]);
    }

    public function mountUpdateHelpdeskWorkGroupQuota(int $groupId): void
    {
        $this->replaceMountedAction('updateHelpdeskWorkGroupQuota', ['groupId' => $groupId]);
    }

    public function mountEditHelpdeskWorkGroup(int $groupId): void
    {
        $this->replaceMountedAction('editHelpdeskWorkGroup', ['groupId' => $groupId]);
    }

    protected function editHelpdeskWorkGroupAction(): Action
    {
        return Action::make('editHelpdeskWorkGroup')
            ->label('Editar grupo')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->modalHeading('Editar grupo de trabajo')
            ->modalDescription('Actualice el nombre, el estado y los integrantes del grupo. La cuota se modifica con «Actualizar cuota».')
            ->modalSubmitActionLabel('Guardar cambios')
            ->modalCancelActionLabel('Cancelar')
            ->fillForm(function (Action $action): array {
                $group = $this->findHelpdeskWorkGroupForAction($action);

                if ($group === null) {
                    return [
                        'name' => '',
                        'status' => 'ACTIVO',
                        'team_colaborador_ids' => [],
                    ];
                }

                return [
                    'name' => $group->name,
                    'status' => $group->status,
                    'team_colaborador_ids' => $group->memberColaboradorIds(),
                ];
            })
            ->schema([
                Grid::make(['default' => 1, 'sm' => 2])
                    ->schema(HelpdeskWorkGroupFormSchema::editFormComponents()),
            ])
            ->action(function (Action $action, array $data): void {
                if (! $this->ensureHelpdeskWorkGroupSystemsAccess()) {
                    return;
                }

                $group = $this->findHelpdeskWorkGroupForAction($action);

                if ($group === null) {
                    $this->notifyHelpdeskWorkGroupNotFound();

                    return;
                }

                $validation = HelpdeskWorkGroupValidator::validateForUpdate($data);

                if (! $validation->valid) {
                    Notification::make()
                        ->warning()
                        ->title($validation->errorTitle ?? 'Datos incompletos')
                        ->body($validation->errorBody ?? 'Revise los datos del grupo.')
                        ->send();

                    return;
                }

                $previousCount = count($group->memberColaboradorIds());
                $userName = Auth::user()?->name;

                $group->update([
                    'name' => $validation->name,
                    'status' => $validation->status,
                    'team_members' => $validation->members,
                    'updated_by' => is_string($userName) ? $userName : null,
                ]);

                $memberDelta = count($validation->members) - $previousCount;
                $deltaLabel = match (true) {
                    $memberDelta > 0 => 'Se agregaron '.$memberDelta.' integrante(s).',
                    $memberDelta < 0 => 'Se retiraron '.abs($memberDelta).' integrante(s).',
                    default => 'Sin cambios en el número de integrantes.',
                };

                Notification::make()
                    ->title('Grupo actualizado')
                    ->body('«'.$validation->name.'» tiene '.count($validation->members).' integrante(s). '.$deltaLabel)
                    ->success()
                    ->send();

                $this->replaceMountedAction('manageHelpdeskWorkGroups');
            });
    }

    protected function updateHelpdeskWorkGroupQuotaAction(): Action
    {
        return Action::make('updateHelpdeskWorkGroupQuota')
            ->label('Actualizar cuota')
            ->icon(Heroicon::OutlinedTicket)
            ->modalHeading('Actualizar cuota de tickets')
            ->modalDescription('Defina cuántos tickets puede registrar el grupo. Los tickets ya creados no se eliminan.')
            ->modalSubmitActionLabel('Guardar cuota')
            ->modalCancelActionLabel('Cancelar')
            ->fillForm(function (Action $action): array {
                $groupId = (int) ($action->getArguments()['groupId'] ?? 0);
                $group = HelpdeskGroup::query()->find($groupId);

                return [
                    'total_tickets_assigned' => $group?->total_tickets_assigned ?? HelpdeskBusinessTicketCreationGate::DEFAULT_GROUP_QUOTA,
                ];
            })
            ->schema([
                TextInput::make('total_tickets_assigned')
                    ->label('Cuota de tickets')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->suffix('tickets')
                    ->helperText(fn (Action $action): string => $this->helpdeskWorkGroupQuotaHelperText($action)),
            ])
            ->action(function (Action $action, array $data): void {
                if (! $this->ensureHelpdeskWorkGroupSystemsAccess()) {
                    return;
                }

                $group = $this->findHelpdeskWorkGroupForAction($action);

                if ($group === null) {
                    $this->notifyHelpdeskWorkGroupNotFound();

                    return;
                }

                $quota = max(0, (int) ($data['total_tickets_assigned'] ?? 0));
                $userName = Auth::user()?->name;

                $group->update([
                    'total_tickets_assigned' => $quota,
                    'updated_by' => is_string($userName) ? $userName : null,
                ]);

                Notification::make()
                    ->title('Cuota actualizada')
                    ->body('El grupo «'.$group->name.'» puede registrar hasta '.$quota.' ticket(s). Actualmente tiene '.$group->ticketsCreatedCount().' registrado(s).')
                    ->success()
                    ->send();

                $this->replaceMountedAction('manageHelpdeskWorkGroups');
            });
    }

    protected function helpdeskWorkGroupQuotaHelperText(Action $action): string
    {
        $groupId = (int) ($action->getArguments()['groupId'] ?? 0);
        $group = HelpdeskGroup::query()->find($groupId);

        if ($group === null) {
            return 'Indique la cuota máxima permitida para el grupo.';
        }

        $used = $group->ticketsCreatedCount();

        return 'Tickets registrados por integrantes del grupo: '.$used.'.';
    }

    protected function deleteHelpdeskWorkGroupAction(): Action
    {
        return Action::make('deleteHelpdeskWorkGroup')
            ->label('Eliminar')
            ->color('danger')
            ->icon(Heroicon::OutlinedTrash)
            ->modalIcon(Heroicon::OutlinedTrash)
            ->requiresConfirmation()
            ->modalHeading('Eliminar grupo de trabajo')
            ->modalDescription('¿Está segura/o de eliminar este grupo? Los tickets existentes no se borran.')
            ->modalSubmitActionLabel('Eliminar')
            ->modalCancelActionLabel('Cancelar')
            ->modalSubmitAction(
                fn (Action $action): Action => $action->color('danger')
            )
            ->action(function (Action $action): void {
                if (! $this->ensureHelpdeskWorkGroupSystemsAccess()) {
                    return;
                }

                $group = $this->findHelpdeskWorkGroupForAction($action);

                if ($group === null) {
                    $this->notifyHelpdeskWorkGroupNotFound();

                    return;
                }

                $group->delete();

                Notification::make()
                    ->title('Grupo eliminado')
                    ->success()
                    ->send();

                $this->replaceMountedAction('manageHelpdeskWorkGroups');
            });
    }

    protected function ensureHelpdeskWorkGroupSystemsAccess(): bool
    {
        if (HelpdeskUserAccess::hasSystemsDepartment()) {
            return true;
        }

        Notification::make()
            ->danger()
            ->title('Acceso denegado')
            ->body('Solo usuarios del departamento SISTEMAS pueden gestionar grupos de trabajo.')
            ->send();

        $this->replaceMountedAction('manageHelpdeskWorkGroups');

        return false;
    }

    protected function findHelpdeskWorkGroupForAction(Action $action): ?HelpdeskGroup
    {
        $groupId = (int) ($action->getArguments()['groupId'] ?? 0);

        if ($groupId < 1) {
            $this->replaceMountedAction('manageHelpdeskWorkGroups');

            return null;
        }

        return HelpdeskGroup::query()->find($groupId);
    }

    protected function notifyHelpdeskWorkGroupNotFound(): void
    {
        Notification::make()
            ->title('Grupo no encontrado')
            ->warning()
            ->send();

        $this->replaceMountedAction('manageHelpdeskWorkGroups');
    }
}
