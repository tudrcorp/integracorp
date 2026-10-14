<?php

declare(strict_types=1);

namespace App\Filament\Administration\Pages;

use App\Filament\Business\Pages\BibliotecaAtencion as BusinessBibliotecaAtencion;

class BibliotecaAtencion extends BusinessBibliotecaAtencion
{
    public static function panelKey(): string
    {
        return 'administration';
    }
}
