<?php

declare(strict_types=1);

namespace App\Filament\Shared\Renovations\Widgets;

class CorporateRenovationKpisWidget extends RenovationKpisWidget
{
    protected static ?int $sort = 1;

    protected function isCorporate(): bool
    {
        return true;
    }
}
