<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Affiliations\Schemas;

use Filament\Schemas\Schema;

class AffiliationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return \App\Filament\Business\Resources\Affiliations\Schemas\AffiliationInfolist::configure($schema);
    }
}
