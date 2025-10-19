<?php

namespace App\Filament\Business\Resources\BenefitCoverages\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class BenefitCoverageInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('benefit_id')
                    ->numeric(),
                TextEntry::make('coverage_id')
                    ->numeric(),
                TextEntry::make('limit')
                    ->numeric(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('plan_id')
                    ->numeric()
                    ->placeholder('-'),
            ]);
    }
}
