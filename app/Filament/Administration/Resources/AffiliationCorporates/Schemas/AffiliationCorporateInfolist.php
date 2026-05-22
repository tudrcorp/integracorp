<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\AffiliationCorporates\Schemas;

use Filament\Schemas\Schema;

class AffiliationCorporateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return \App\Filament\Business\Resources\AffiliationCorporates\Schemas\AffiliationCorporateInfolist::configure($schema);
    }
}
