<?php

declare(strict_types=1);

namespace App\Filament\Operations\Pages;

use App\Filament\Business\Pages\AtencionWhatsapp as BusinessAtencionWhatsapp;

class AtencionWhatsapp extends BusinessAtencionWhatsapp
{
    protected function panelId(): string
    {
        return 'operations';
    }
}
