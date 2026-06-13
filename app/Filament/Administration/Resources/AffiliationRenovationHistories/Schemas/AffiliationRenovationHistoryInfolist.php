<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\AffiliationRenovationHistories\Schemas;

use App\Filament\Shared\RenovationHistories\RenovationHistoryInfolist as SharedRenovationHistoryInfolist;
use Filament\Schemas\Schema;

class AffiliationRenovationHistoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return SharedRenovationHistoryInfolist::configure($schema);
    }
}
