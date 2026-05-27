<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Models\HelpdeskGroup;
use App\Support\HelpdeskUserAccess;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

trait ManagesHelpdeskWorkGroupsOnList
{
    public function mountDeleteHelpdeskWorkGroup(int $groupId): void
    {
        $this->replaceMountedAction('deleteHelpdeskWorkGroup', ['groupId' => $groupId]);
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
                if (! HelpdeskUserAccess::hasSystemsDepartment()) {
                    Notification::make()
                        ->danger()
                        ->title('Acceso denegado')
                        ->body('Solo usuarios del departamento SISTEMAS pueden eliminar grupos de trabajo.')
                        ->send();

                    $this->replaceMountedAction('manageHelpdeskWorkGroups');

                    return;
                }

                $groupId = (int) ($action->getArguments()['groupId'] ?? 0);

                if ($groupId < 1) {
                    $this->replaceMountedAction('manageHelpdeskWorkGroups');

                    return;
                }

                $group = HelpdeskGroup::query()->find($groupId);

                if ($group === null) {
                    Notification::make()
                        ->title('Grupo no encontrado')
                        ->warning()
                        ->send();

                    $this->replaceMountedAction('manageHelpdeskWorkGroups');

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
}
