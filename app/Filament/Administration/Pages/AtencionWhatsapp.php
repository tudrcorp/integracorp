<?php

declare(strict_types=1);

namespace App\Filament\Administration\Pages;

use App\Filament\Business\Pages\AtencionWhatsapp as BusinessAtencionWhatsapp;

class AtencionWhatsapp extends BusinessAtencionWhatsapp
{
    protected function panelId(): string
    {
        return 'administration';
    }
}
