<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\HelpdeskGroup;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

final class HelpdeskWorkGroupHeaderAction
{
    public const SUBMIT_BUTTON_CLASS = 'aviso-btn-ios-success shrink-0 inline-flex min-w-[7.5rem] items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const IOS_GRAY_BTN = 'ticket-btn-ios-gray shrink-0 inline-flex min-w-[7.5rem] items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    public static function make(): Action
    {
        return Action::make('manageHelpdeskWorkGroups')
            ->label('Grupos de trabajo')
            ->icon('heroicon-o-user-group')
            ->color('gray')
            ->visible(fn (): bool => HelpdeskUserAccess::hasSystemsDepartment())
            ->extraAttributes([
                'id' => 'helpdesk-work-groups-btn',
                'data-helpdesk-work-groups-trigger' => 'true',
                'class' => 'shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]',
            ])
            ->slideOver()
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalHeading('Grupos de trabajo')
            ->modalDescription('Defina equipos, la cuota de tickets del grupo e integrantes del directorio RRHH.')
            ->modalSubmitAction(false)
            ->modalCancelAction(
                fn (Action $action): Action => $action
                    ->label('Cerrar')
                    ->extraAttributes([
                        'class' => self::IOS_GRAY_BTN,
                    ])
            )
            ->form(HelpdeskWorkGroupFormSchema::components())
            ->modalContent(fn (): View => view('filament.helpdesks.work-groups-modal', [
                'groups' => HelpdeskGroup::query()->latest()->get(),
            ]))
            ->action(function (array $data): void {
                if (! HelpdeskUserAccess::hasSystemsDepartment()) {
                    Notification::make()
                        ->danger()
                        ->title('Acceso denegado')
                        ->body('Solo usuarios del departamento SISTEMAS pueden gestionar grupos de trabajo.')
                        ->send();

                    return;
                }

                if (! HelpdeskWorkGroupFormSchema::shouldCreateGroup($data)) {
                    return;
                }

                $name = trim((string) ($data['name'] ?? ''));
                $status = trim((string) ($data['status'] ?? 'ACTIVO'));
                $ticketQuota = max(0, (int) ($data['total_tickets_assigned'] ?? 0));
                $teamColaboradorIds = $data['team_colaborador_ids'] ?? [];

                if ($name === '') {
                    Notification::make()
                        ->warning()
                        ->title('Nombre requerido')
                        ->body('Indique un nombre para el grupo de trabajo.')
                        ->send();

                    return;
                }

                if (! is_array($teamColaboradorIds)) {
                    $teamColaboradorIds = [];
                }

                $teamColaboradorIds = array_values(array_unique(array_map(
                    static fn (mixed $value): int => (int) $value,
                    array_filter($teamColaboradorIds, static fn (mixed $value): bool => filled($value))
                )));

                if (count($teamColaboradorIds) < 2) {
                    Notification::make()
                        ->warning()
                        ->title('Integrantes insuficientes')
                        ->body('Seleccione al menos dos colaboradores para el grupo.')
                        ->send();

                    return;
                }

                $members = HelpdeskTeamMembersPayload::fromColaboradorIds($teamColaboradorIds);

                if (count($members) < 2) {
                    Notification::make()
                        ->warning()
                        ->title('Colaboradores no válidos')
                        ->body('Debe seleccionar al menos dos colaboradores activos en el directorio.')
                        ->send();

                    return;
                }

                $userName = Auth::user()?->name;

                HelpdeskGroup::query()->create([
                    'name' => $name,
                    'status' => in_array($status, ['ACTIVO', 'INACTIVO'], true) ? $status : 'ACTIVO',
                    'total_tickets_assigned' => $ticketQuota,
                    'team_members' => $members,
                    'created_by' => is_string($userName) ? $userName : null,
                    'updated_by' => is_string($userName) ? $userName : null,
                ]);

                Notification::make()
                    ->success()
                    ->title('Grupo creado')
                    ->body('El grupo «'.$name.'» quedó registrado con cuota de '.$ticketQuota.' ticket(s) y '.count($members).' integrante(s).')
                    ->send();
            });
    }
}
