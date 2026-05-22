<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Agencies\Schemas;

use Filament\Schemas\Schema;

class AgencyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return \App\Filament\Business\Resources\Agencies\Schemas\AgencyInfolist::configure($schema);
    }
}
