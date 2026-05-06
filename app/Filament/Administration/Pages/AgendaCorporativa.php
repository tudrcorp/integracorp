<?php

declare(strict_types=1);

namespace App\Filament\Administration\Pages;

use App\Filament\Business\Pages\AgendaCorporativa as BusinessAgendaCorporativa;
use UnitEnum;

class AgendaCorporativa extends BusinessAgendaCorporativa
{
    // protected static string|UnitEnum|null $navigationGroup = 'ADMINISTRACIÓN';

    protected static ?int $navigationSort = 2;
}
