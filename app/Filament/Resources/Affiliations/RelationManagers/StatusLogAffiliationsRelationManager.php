<?php

namespace App\Filament\Resources\Affiliations\RelationManagers;

use App\Filament\Resources\Affiliations\AffiliationResource;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class StatusLogAffiliationsRelationManager extends RelationManager
{
    protected static string $relationship = 'status_log_affiliations';

    protected static ?string $title = 'BITACORA';

    protected static string|BackedEnum|null $icon = 'heroicon-m-pencil';

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}