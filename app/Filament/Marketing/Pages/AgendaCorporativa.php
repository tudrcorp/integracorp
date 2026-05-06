<?php

declare(strict_types=1);

namespace App\Filament\Marketing\Pages;

use App\Filament\Business\Pages\AgendaCorporativa as BusinessAgendaCorporativa;
use UnitEnum;

class AgendaCorporativa extends BusinessAgendaCorporativa
{
    // protected static string|UnitEnum|null $navigationGroup = 'MARKETING';

    protected static ?int $navigationSort = 2;
}
