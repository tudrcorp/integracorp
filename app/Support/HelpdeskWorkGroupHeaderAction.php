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
            ->modalDescription('Departamento SISTEMAS: defina equipos, cuota de tickets e integrantes. Ampliar la cuota permite que el grupo vuelva a registrar tickets.')
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

                $validation = HelpdeskWorkGroupValidator::validate($data);

                if (! $validation->valid) {
                    Notification::make()
                        ->warning()
                        ->title($validation->errorTitle ?? 'Datos incompletos')
                        ->body($validation->errorBody ?? 'Revise el formulario del grupo.')
                        ->send();

                    return;
                }

                $userName = Auth::user()?->name;

                HelpdeskGroup::query()->create([
                    'name' => $validation->name,
                    'status' => $validation->status,
                    'total_tickets_assigned' => $validation->ticketQuota,
                    'team_members' => $validation->members,
                    'created_by' => is_string($userName) ? $userName : null,
                    'updated_by' => is_string($userName) ? $userName : null,
                ]);

                Notification::make()
                    ->success()
                    ->title('Grupo creado')
                    ->body('El grupo «'.$validation->name.'» quedó registrado con cuota de '.$validation->ticketQuota.' ticket(s) y '.count($validation->members).' integrante(s).')
                    ->send();
            });
    }
}
