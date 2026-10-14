<?php

declare(strict_types=1);

namespace App\Filament\Administration\Pages;

use App\Filament\Business\Pages\SupervisionWhatsapp as BusinessSupervisionWhatsapp;

class SupervisionWhatsapp extends BusinessSupervisionWhatsapp
{
    public static function panelKey(): string
    {
        return 'administration';
    }

    protected static function inboxUrl(): string
    {
        return AtencionWhatsapp::getUrl(panel: static::panelKey());
    }

    protected static function libraryUrl(): string
    {
        return BibliotecaAtencion::getUrl(panel: static::panelKey());
    }
}
