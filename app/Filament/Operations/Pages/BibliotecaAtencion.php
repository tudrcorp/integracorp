<?php

declare(strict_types=1);

namespace App\Filament\Operations\Pages;

use App\Filament\Business\Pages\BibliotecaAtencion as BusinessBibliotecaAtencion;

class BibliotecaAtencion extends BusinessBibliotecaAtencion
{
    public static function panelKey(): string
    {
        return 'operations';
    }
}
