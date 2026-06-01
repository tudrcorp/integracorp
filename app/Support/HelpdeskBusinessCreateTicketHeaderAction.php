<?php

declare(strict_types=1);

namespace App\Support;

use App\Filament\Business\Resources\Helpdesks\HelpdeskResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

final class HelpdeskBusinessCreateTicketHeaderAction
{
    public const BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    public static function make(): Action
    {
        return Action::make('create')
            ->label('Crear ticket de soporte')
            ->icon('heroicon-s-plus')
            ->color('primary')
            ->visible(fn (): bool => HelpdeskResource::canSeeCreateTicketButton())
            ->action(function (): void {
                $verdict = HelpdeskBusinessTicketCreationGate::allowsCreation();

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

                redirect()->to(HelpdeskResource::getUrl('create'));
            })
            ->extraAttributes([
                'id' => 'helpdesk-create-ticket-btn',
                'data-tour-shape' => 'pill',
                'class' => self::BUTTON_CLASS,
            ]);
    }
}
