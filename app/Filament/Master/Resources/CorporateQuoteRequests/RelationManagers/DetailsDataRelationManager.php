<?php

namespace App\Filament\Master\Resources\CorporateQuoteRequests\RelationManagers;

use App\Filament\Master\Resources\CorporateQuoteRequests\CorporateQuoteRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class DetailsDataRelationManager extends RelationManager
{
    protected static string $relationship = 'detailsData';

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}