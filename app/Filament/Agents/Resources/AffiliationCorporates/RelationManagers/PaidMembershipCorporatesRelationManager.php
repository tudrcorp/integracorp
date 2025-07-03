<?php

namespace App\Filament\Agents\Resources\AffiliationCorporates\RelationManagers;

use App\Filament\Agents\Resources\AffiliationCorporates\AffiliationCorporateResource;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class PaidMembershipCorporatesRelationManager extends RelationManager
{
    protected static string $relationship = 'paid_membership_corporates';

    protected static ?string $title = 'Pagos asociados';

    protected static string|BackedEnum|null $icon = 'heroicon-o-credit-card';

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}