<?php

declare(strict_types=1);

namespace App\Filament\Master\Resources\AffiliationCorporates\RelationManagers;

use App\Support\Filament\CorporateAffiliatesTableDisplay;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class CorporateAffiliatesRelationManager extends RelationManager
{
    protected static string $relationship = 'corporateAffiliates';

    protected static ?string $title = 'Afiliados';

    protected static string|BackedEnum|null $icon = 'heroicon-s-user-group';

    public function table(Table $table): Table
    {
        return CorporateAffiliatesTableDisplay::configureReadOnlyTable($table);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
