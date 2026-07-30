<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\RenovationCorporates\Schemas;

use App\Filament\Shared\RenovationCorporates\RenovationCorporateInfolist as SharedRenovationCorporateInfolist;
use Filament\Schemas\Schema;

class RenovationCorporateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return SharedRenovationCorporateInfolist::configure($schema);
    }
}
