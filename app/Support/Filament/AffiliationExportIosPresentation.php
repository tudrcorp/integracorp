<?php

declare(strict_types=1);

namespace App\Support\Filament;

use Filament\Actions\Action;

final class AffiliationExportIosPresentation
{
    public const EXPORT_LABEL = 'Exportar Afiliados';

    public const IOS_MODAL_CLASS = 'fi-helpdesk-ios-section';

    public const IOS_SUCCESS_BTN = 'aviso-btn-ios-success shrink-0 inline-flex min-w-[7.5rem] items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    public const IOS_GRAY_BTN = 'ticket-btn-ios-gray shrink-0 inline-flex min-w-[7.5rem] items-center justify-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    public static function apply(Action $action): Action
    {
        return $action
            ->label(self::EXPORT_LABEL)
            ->button()
            ->extraAttributes([
                'class' => self::IOS_SUCCESS_BTN,
            ])
            ->extraModalWindowAttributes([
                'class' => self::IOS_MODAL_CLASS,
            ])
            ->modalSubmitAction(fn (Action $submitAction): Action => $submitAction
                ->label('Descargar')
                ->extraAttributes([
                    'class' => self::IOS_SUCCESS_BTN,
                ]))
            ->modalCancelAction(fn (Action $cancelAction): Action => $cancelAction
                ->extraAttributes([
                    'class' => self::IOS_GRAY_BTN,
                ]));
    }
}
