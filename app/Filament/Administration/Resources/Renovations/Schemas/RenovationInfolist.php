<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Renovations\Schemas;

use App\Filament\Shared\Renovations\RenovationInfolist as SharedRenovationInfolist;
use Filament\Schemas\Schema;

class RenovationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return SharedRenovationInfolist::configure($schema);
    }
}
