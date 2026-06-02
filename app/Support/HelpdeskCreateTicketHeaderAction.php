<?php

declare(strict_types=1);

namespace App\Support;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;

final class HelpdeskCreateTicketHeaderAction
{
    public const BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    /**
     * @param  class-string<resource>  $resourceClass
     */
    public static function make(string $resourceClass): Action
    {
        return Action::make('create')
            ->label('Crear ticket de soporte')
            ->icon('heroicon-s-plus')
            ->color('primary')
            ->visible(fn (): bool => $resourceClass::canSeeCreateTicketButton())
            ->action(function () use ($resourceClass): void {
                $verdict = HelpdeskTicketCreationGate::allowsCreation(
                    enforceGroupQuota: $resourceClass::helpdeskEnforcesCreationQuota(),
                );

                if (! $verdict->allowed) {
                    Notification::make()
                        ->title('No puede crear tickets')
                        ->body($verdict->message)
                        ->icon('heroicon-m-user-group')
                        ->iconColor('warning')
                        ->warning()
                        ->persistent()
                        ->send();

                    return;
                }

                redirect()->to($resourceClass::getUrl('create'));
            })
            ->extraAttributes([
                'id' => 'helpdesk-create-ticket-btn',
                'data-tour-shape' => 'pill',
                'class' => self::BUTTON_CLASS,
            ]);
    }
}
