<?php

declare(strict_types=1);

namespace App\Support\Filament;

use Filament\Tables\Columns\TextColumn;

final class CorporateQuotePlanAffiliatesDisplay
{
    public static function planColumn(): TextColumn
    {
        return TextColumn::make('plan.description')
            ->label('Plan')
            ->description(fn ($record): string => self::affiliatesDescription((int) $record->total_persons));
    }

    public static function affiliatesDescription(int $totalPersons): string
    {
        return $totalPersons.' '.($totalPersons === 1 ? 'afiliado' : 'afiliados');
    }
}
