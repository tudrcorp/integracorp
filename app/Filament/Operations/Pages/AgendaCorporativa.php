<?php

declare(strict_types=1);

namespace App\Filament\Operations\Pages;

use App\Filament\Business\Pages\AgendaCorporativa as BusinessAgendaCorporativa;
use UnitEnum;

class AgendaCorporativa extends BusinessAgendaCorporativa
{
    // protected static string|UnitEnum|null $navigationGroup = 'COORDINACIÓN DE SERVICIOS';

    protected static ?int $navigationSort = 20;
}
