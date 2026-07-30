<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\AffiliationCorporateRenovationHistories\Schemas;

use App\Filament\Shared\RenovationCorporateHistories\RenovationCorporateHistoryInfolist as SharedRenovationCorporateHistoryInfolist;
use Filament\Schemas\Schema;

class AffiliationCorporateRenovationHistoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return SharedRenovationCorporateHistoryInfolist::configure($schema);
    }
}
