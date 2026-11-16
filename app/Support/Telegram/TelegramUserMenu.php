<?php

declare(strict_types=1);

namespace App\Support\Telegram;

use App\Filament\Shared\Telegram\Pages\ConnectTelegram;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;

/**
 * Ítem «Conectar Telegram» del menú de usuario de los paneles internos. Solo lo
 * ve el personal activo con correo del dominio interno.
 */
final class TelegramUserMenu
{
    public static function action(): Action
    {
        return Action::make('connectTelegram')
            ->label('CONECTAR TELEGRAM')
            ->icon('heroicon-o-paper-airplane')
            ->color('info')
            ->visible(fn (): bool => ConnectTelegram::userCanConnect(Auth::user()))
            ->url(fn (): string => ConnectTelegram::getUrl());
    }
}
