<?php

namespace App\Filament\Resources\AffiliationCorporates\RelationManagers;

use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Resources\AffiliationCorporates\AffiliationCorporateResource;

class StatusLogCorporateAffiliationsRelationManager extends RelationManager
{
    protected static string $relationship = 'status_log_corporate_affiliations';

    protected static ?string $title = 'Bitacora';

    protected static string|BackedEnum|null $icon = 'heroicon-s-pencil';

    public function table(Table $table): Table
    {
        return $table
            
            ->headerActions([
                // CreateAction::make(),
            ]);
    }
}