<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Agencies\Schemas;

use App\Filament\Shared\CommercialStructure\AgencyInfolist as SharedAgencyInfolist;
use Filament\Schemas\Schema;

class AgencyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return SharedAgencyInfolist::configure($schema);
    }
}
