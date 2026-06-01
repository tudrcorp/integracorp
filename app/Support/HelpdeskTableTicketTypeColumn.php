<?php

declare(strict_types=1);

namespace App\Support;

use Filament\Tables\Columns\TextColumn;

final class HelpdeskTableTicketTypeColumn
{
    public static function make(bool $individualSearch = false): TextColumn
    {
        $column = TextColumn::make('ticket_type')
            ->label('Tipo de ticket')
            ->icon('heroicon-m-ticket')
            ->formatStateUsing(fn (?string $state): string => mb_strtoupper(HelpdeskTicketType::label($state)))
            ->badge()
            ->color(fn (?string $state): string => HelpdeskTicketType::filamentColor($state))
            ->sortable();

        if ($individualSearch) {
            return $column->searchable(isIndividual: true);
        }

        return $column->searchable();
    }
}
