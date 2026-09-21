<?php

declare(strict_types=1);

namespace App\Filament\Shared\Renovations\Widgets;

class IndividualRenovationKpisWidget extends RenovationKpisWidget
{
    protected static ?int $sort = 0;

    protected function isCorporate(): bool
    {
        return false;
    }
}
