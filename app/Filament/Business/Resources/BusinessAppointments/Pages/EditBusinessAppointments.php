<?php

namespace App\Filament\Business\Resources\BusinessAppointments\Pages;

use App\Filament\Business\Resources\BusinessAppointments\BusinessAppointmentsResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditBusinessAppointments extends EditRecord
{
    protected static string $resource = BusinessAppointmentsResource::class;

    private const IOS_BUTTON_BASE = ' shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const IOS_PRIMARY_BUTTON_CLASS = 'aviso-btn-ios-primary'.self::IOS_BUTTON_BASE;

    private const IOS_DANGER_BUTTON_CLASS = 'aviso-btn-ios-danger'.self::IOS_BUTTON_BASE;

    private const IOS_GRAY_BUTTON_CLASS = 'ticket-btn-ios-gray'.self::IOS_BUTTON_BASE;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()
                ->label('Ver')
                ->icon('heroicon-o-eye')
                ->extraAttributes([
                    'class' => self::IOS_PRIMARY_BUTTON_CLASS,
                ]),
            DeleteAction::make()
                ->extraAttributes([
                    'class' => self::IOS_DANGER_BUTTON_CLASS,
                ])
                ->modalSubmitAction(fn (Action $action): Action => $action->extraAttributes([
                    'class' => self::IOS_DANGER_BUTTON_CLASS,
                ]))
                ->modalCancelAction(fn (Action $action): Action => $action->extraAttributes([
                    'class' => self::IOS_GRAY_BUTTON_CLASS,
                ])),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {

        $data['updated_by'] = Auth::user()->name;

        return $data;
    }
}
